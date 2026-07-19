<?php

use App\Http\Controllers\Web\PublicStorageController;
use App\Http\Controllers\Web\PwaController;
use App\Http\Controllers\Web\AdminAlertController;
use App\Http\Controllers\Web\AdminBookingController;
use App\Http\Controllers\Web\AdminReferralController;
use App\Http\Controllers\Web\AdminRevenueController;
use App\Http\Controllers\Web\AdminPricingController;
use App\Http\Controllers\Web\AdminSettingsController;
use App\Http\Controllers\Web\AdminUserController;
use App\Http\Controllers\Web\AdminWalletController;
use App\Http\Controllers\Web\AdminAuthCodesController;
use App\Http\Controllers\Web\AdminAuthController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\CancellationReasonController;
use App\Http\Controllers\Web\AdminDriverInboxController;
use App\Http\Controllers\Web\PasswordResetController;
use App\Http\Controllers\Web\RegisterOtpController;
use App\Http\Controllers\Web\DriverController;
use App\Http\Controllers\Web\DriverInboxController;
use App\Http\Controllers\Web\DriverSettingsController;
use App\Http\Controllers\Web\DriverWalletController;
use App\Http\Controllers\Web\GeocodeController;
use App\Http\Controllers\Web\GuestBookingController;
use App\Http\Controllers\Web\CustomerAuthController;
use App\Http\Controllers\Web\CustomerController;
use App\Http\Controllers\Web\CustomerInboxController;
use App\Http\Controllers\Web\CustomerWalletController;
use App\Http\Controllers\Web\TripChatController;
use App\Support\RoleDashboard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('csrf-token', function () {
    return response()->json(['token' => csrf_token()]);
});

Route::get('storage/{path}', [PublicStorageController::class, 'show'])
    ->where('path', '.*')
    ->name('storage.public');

Route::get('pwa/manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('pwa/push/vapid-public-key', [PwaController::class, 'vapidPublicKey'])->name('pwa.vapid');
Route::post('pwa/push/subscribe', [PwaController::class, 'subscribe'])->name('pwa.subscribe');
Route::post('pwa/push/unsubscribe', [PwaController::class, 'unsubscribe'])->name('pwa.unsubscribe');
Route::post('pwa/push/touch-contact', [PwaController::class, 'touchContact'])->name('pwa.touchContact');

Route::post('register', [AuthController::class, 'register'])->name('register.submit');

Route::middleware('guest')->group(function () {
    Route::get('login',    [AuthController::class, 'showLogin'])->name('login');
    Route::post('login',   [AuthController::class, 'login']);
    Route::post('login/check-phone', [AuthController::class, 'checkPhone'])->name('login.checkPhone');
    // Đăng nhập / đăng ký tài xế — cùng xử lý login user, form đăng ký TX (nhiều field hơn).
    Route::get('tai-xe/dang-nhap', [AuthController::class, 'showLogin'])->name('driver.login');
    Route::get('tai-xe/dang-ky', [AuthController::class, 'showRegister'])->name('driver.register');
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::get('admin/login',  [AdminAuthController::class, 'showLogin'])->name('admin.login');
    Route::post('admin/login', [AdminAuthController::class, 'login']);
    Route::get('dang-ky', [CustomerAuthController::class, 'showRegister'])->name('customer.register');
    Route::post('dang-ky', [CustomerAuthController::class, 'register']);
});

Route::get('quen-mat-khau', [PasswordResetController::class, 'showRequestForm'])->name('password.reset.request');
Route::post('quen-mat-khau', [PasswordResetController::class, 'requestReset']);
Route::get('quen-mat-khau/ma', [PasswordResetController::class, 'showCodeForm'])->name('password.reset.code');
Route::post('quen-mat-khau/ma', [PasswordResetController::class, 'verifyCode']);
Route::get('quen-mat-khau/pin', [PasswordResetController::class, 'showNewPinForm'])->name('password.reset.pin');
Route::post('quen-mat-khau/pin', [PasswordResetController::class, 'storeNewPin']);

Route::get('dang-ky/otp', [RegisterOtpController::class, 'show'])->name('auth.register.otp');
Route::post('dang-ky/otp', [RegisterOtpController::class, 'verify'])->name('auth.register.otp.verify');
Route::post('dang-ky/otp/gui-lai', [RegisterOtpController::class, 'resend'])->name('auth.register.otp.resend');

Route::post('logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->get('dashboard', function () {
    return redirect(RoleDashboard::route(auth()->user()->role));
})->name('dashboard');

Route::get('trips/search', function (Request $request) {
    if (! auth()->check()) {
        return redirect()->route('home', $request->query());
    }

    return match (auth()->user()->role) {
        'driver' => redirect()->route('driver.dashboard'),
        'admin'  => redirect()->route('admin.bookings'),
        'customer' => redirect()->route('customer.account'),
        default  => redirect()->route('home', $request->query()),
    };
})->name('trips.search');

// ── Đặt vé (trang chủ) ─────────────────────────────────────────────────────
Route::get('/', [GuestBookingController::class, 'index'])->name('home');
Route::get('chuyen', [GuestBookingController::class, 'trips'])->name('booking.trips');
Route::get('chuyen/status', [GuestBookingController::class, 'tripStatus'])->name('booking.tripStatus');
Route::post('chuyen/review', [GuestBookingController::class, 'storeTripReview'])->name('booking.tripReview');
Route::post('chuyen/cancel', [GuestBookingController::class, 'cancelTrip'])->name('booking.tripCancel');
Route::redirect('dat-chuyen', '/chuyen');
Route::get('gioi-thieu', [GuestBookingController::class, 'about'])->name('about');

Route::middleware(['auth', 'role:customer'])->group(function () {
    Route::get('tai-khoan', [CustomerController::class, 'account'])->name('customer.account');
    Route::post('tai-khoan/cap-nhat', [CustomerController::class, 'updateProfile'])->name('customer.profile.update');
    Route::post('tai-khoan/thong-tin', [CustomerController::class, 'updateInfo'])->name('customer.info.update');
    Route::get('tai-khoan/hop-thu/poll', [CustomerInboxController::class, 'poll'])->name('customer.inbox.poll');
    Route::post('tai-khoan/hop-thu/doc', [CustomerInboxController::class, 'markRead'])->name('customer.inbox.read');
    Route::post('tai-khoan/vi/nap', [CustomerWalletController::class, 'deposit'])->name('customer.wallet.deposit');
});
Route::middleware(['auth', 'role:customer'])->group(function () {
    Route::get('dat-xe/bat-dau', fn () => redirect()->route('home'))->name('booking.start');
    Route::post('bookings', [GuestBookingController::class, 'store'])->name('booking.store');
    Route::post('dat-xe/bookings', [GuestBookingController::class, 'store']);
    Route::get('chuyen/chat', [TripChatController::class, 'customerMessages'])->name('booking.chat.messages');
    Route::post('chuyen/chat', [TripChatController::class, 'customerSend'])
        ->middleware('throttle:30,1')
        ->name('booking.chat.send');
});
Route::get('booking/check-duplicate', [GuestBookingController::class, 'checkDuplicateBooking'])->name('booking.checkDuplicate');
Route::get('booking/driver-offers', [GuestBookingController::class, 'driverOffers'])->name('booking.driverOffers');
Route::get('quote-price', [GuestBookingController::class, 'quotePrice'])->name('booking.quotePrice');
Route::get('bookings', fn () => redirect()->route('home'));
Route::get('geocode/reverse', [GeocodeController::class, 'reverse'])->name('geocode.reverse');
Route::get('geocode/search', [GeocodeController::class, 'search'])->name('geocode.search');
Route::get('geocode/resolve', [GeocodeController::class, 'resolve'])->name('geocode.resolve');
Route::get('geocode/direction', [GeocodeController::class, 'direction'])->name('geocode.direction');
Route::get('geocode/nearby', [GeocodeController::class, 'nearby'])->name('geocode.nearby');
Route::get('cancellation-reasons', [CancellationReasonController::class, 'index'])->name('cancellationReasons.index');

Route::permanentRedirect('dat-xe', '/');
Route::redirect('guest/orders', '/chuyen');

// ── Driver ────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:driver', 'driver.password'])->group(function () {
    Route::get('driver/dashboard', [DriverController::class, 'myDashboard'])->name('driver.dashboard');
    Route::get('driver/dashboard/poll', [DriverController::class, 'dashboardPoll'])->name('driver.dashboard.poll');
    Route::post('driver/location', [DriverController::class, 'updateLocation'])->name('driver.location.update');
    Route::post('driver/trip-requests/{driverTripRequest}/accept', [DriverController::class, 'acceptTripRequest'])->name('driver.tripRequests.accept');
    Route::post('driver/trip-requests/{driverTripRequest}/reject', [DriverController::class, 'rejectTripRequest'])->name('driver.tripRequests.reject');
    Route::patch('driver/availability', [DriverController::class, 'updateAvailability'])->name('driver.availability.update');
    Route::patch('driver/password', [DriverController::class, 'updatePassword'])->name('driver.password.update');
    Route::post('driver/bookings/{booking}/complete', [DriverController::class, 'completeTrip'])->name('driver.bookings.complete');
    Route::get('driver/bookings/{booking}/chat', [TripChatController::class, 'driverMessages'])->name('driver.bookings.chat.messages');
    Route::post('driver/bookings/{booking}/chat', [TripChatController::class, 'driverSend'])
        ->middleware('throttle:30,1')
        ->name('driver.bookings.chat.send');
    Route::post('driver/schedules/{schedule}/advance', [DriverController::class, 'advanceSchedule'])->name('driver.schedules.advance');
    Route::post('driver/schedules/{schedule}/confirm-movement', [DriverController::class, 'confirmMovement'])->name('driver.schedules.confirmMovement');
    Route::post('driver/schedules/{schedule}/complete', [DriverController::class, 'completeSchedule'])->name('driver.schedules.complete');
    Route::post('driver/schedules/{schedule}/cancel', [DriverController::class, 'cancelSchedule'])->name('driver.schedules.cancel');
    Route::post('driver/schedules/{schedule}/late-pickup-continue', [DriverController::class, 'latePickupContinue'])->name('driver.schedules.latePickupContinue');
    Route::post('driver/wallet/deposit', [DriverWalletController::class, 'deposit'])->name('driver.wallet.deposit');
    Route::patch('driver/settings', [DriverSettingsController::class, 'updateSettings'])->name('driver.settings.update');
    Route::post('driver/settings/documents', [DriverSettingsController::class, 'submitDocuments'])->name('driver.settings.documents');
    Route::post('driver/inbox/read', [DriverInboxController::class, 'markRead'])->name('driver.inbox.read');
});

// ── Admin ──────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('admin/referrals',              [AdminReferralController::class, 'referrals'])->name('admin.referrals');
    Route::get('admin/dashboard',              [AdminSettingsController::class, 'dashboard'])->name('admin.dashboard');
    Route::post('admin/referrers',           [AdminReferralController::class, 'storeReferrer'])->name('admin.referrers.store');
    Route::patch('admin/referrers/{referralCode}', [AdminReferralController::class, 'updateReferrer'])->name('admin.referrers.update');
    Route::delete('admin/referral-codes/{referralCode}', [AdminReferralController::class, 'destroyReferralCode'])->name('admin.referralCodes.destroy');
    Route::post('admin/referrers/{referralCode}/hide', [AdminReferralController::class, 'suspendReferrer'])->name('admin.referrers.hide');
    Route::post('admin/referrers/{referralCode}/show', [AdminReferralController::class, 'showReferrer'])->name('admin.referrers.show');
    Route::post('admin/referrers/{referralCode}/assign', [AdminReferralController::class, 'assignReferrer'])->name('admin.referrers.assign');
    Route::post('admin/referrers/{referralCode}/revoke', [AdminReferralController::class, 'revokeReferrer'])->name('admin.referrers.revoke');
    Route::post('admin/bank-settings',         [AdminSettingsController::class, 'updateBankSettings'])->name('admin.bankSettings.update');
    Route::patch('admin/password',            [AdminSettingsController::class, 'updatePassword'])->name('admin.password.update');
    Route::post('admin/booking-page-settings', [AdminSettingsController::class, 'updateBookingPageSettings'])->name('admin.bookingPageSettings.update');
    Route::post('admin/branding-settings', [AdminSettingsController::class, 'updateBrandingSettings'])->name('admin.brandingSettings.update');
    Route::post('admin/push-settings', [AdminSettingsController::class, 'updatePushSettings'])->name('admin.pushSettings.update');
    Route::post('admin/sound-settings', [AdminSettingsController::class, 'updateSoundSettings'])->name('admin.soundSettings.update');
    Route::post('admin/pricing-settings', [AdminPricingController::class, 'updateSettings'])->name('admin.pricingSettings.update');
    Route::post('admin/vehicle-types', [AdminPricingController::class, 'storeVehicleType'])->name('admin.vehicleTypes.store');
    Route::patch('admin/vehicle-types/{vehicleType}', [AdminPricingController::class, 'updateVehicleType'])->name('admin.vehicleTypes.update');
    Route::delete('admin/vehicle-types/{vehicleType}', [AdminPricingController::class, 'destroyVehicleType'])->name('admin.vehicleTypes.destroy');
    Route::post('admin/pricing-surcharges', [AdminPricingController::class, 'storeSurcharge'])->name('admin.pricingSurcharges.store');
    Route::patch('admin/pricing-surcharges/{surcharge}', [AdminPricingController::class, 'updateSurcharge'])->name('admin.pricingSurcharges.update');
    Route::delete('admin/pricing-surcharges/{surcharge}', [AdminPricingController::class, 'destroySurcharge'])->name('admin.pricingSurcharges.destroy');
    Route::post('admin/pricing-tolls', [AdminPricingController::class, 'storeToll'])->name('admin.pricingTolls.store');
    Route::patch('admin/pricing-tolls/{toll}', [AdminPricingController::class, 'updateToll'])->name('admin.pricingTolls.update');
    Route::delete('admin/pricing-tolls/{toll}', [AdminPricingController::class, 'destroyToll'])->name('admin.pricingTolls.destroy');
    Route::post('admin/route-distances',      [AdminSettingsController::class, 'updateRouteDistances'])->name('admin.routeDistances.update');
    Route::post('admin/destinations',         [AdminSettingsController::class, 'storeDestination'])->name('admin.destinations.store');
    Route::post('admin/destinations/{tripRoute}/show', [AdminSettingsController::class, 'showDestination'])->name('admin.destinations.show');
    Route::delete('admin/destinations/{tripRoute}', [AdminSettingsController::class, 'destroyDestination'])->name('admin.destinations.destroy');

    Route::get('admin/alerts/poll', [AdminAlertController::class, 'poll'])->name('admin.alerts.poll');

    Route::get('admin/bookings',                   [AdminBookingController::class, 'bookings'])->name('admin.bookings');
    Route::get('admin/bookings/sync',              [AdminBookingController::class, 'bookingsSync'])->name('admin.bookings.sync');
    Route::post('admin/bookings/{booking}/assign', [AdminBookingController::class, 'confirmAndAssignBooking'])->name('admin.bookings.assign');
    Route::post('admin/bookings/{booking}/nudge-driver', [AdminBookingController::class, 'nudgeDriverBooking'])->name('admin.bookings.nudgeDriver');
    Route::post('admin/bookings/{booking}/cancel', [AdminBookingController::class, 'cancelBooking'])->name('admin.bookings.cancel');
    Route::delete('admin/bookings/lo', [AdminBookingController::class, 'bulkDismissBookings'])->name('admin.bookings.bulkDismiss');

    Route::get('admin/revenue', [AdminRevenueController::class, 'revenueReport'])->name('admin.revenue');

    Route::get('admin/users', [AdminUserController::class, 'index'])->name('admin.users');
    Route::delete('admin/users/bulk', [AdminUserController::class, 'bulkDestroy'])->name('admin.users.bulkDestroy');
    Route::get('admin/users/{user}/edit', [AdminUserController::class, 'edit'])->name('admin.users.edit');
    Route::patch('admin/users/{user}', [AdminUserController::class, 'update'])->name('admin.users.update');
    Route::post('admin/users/{user}/photos', [AdminUserController::class, 'uploadPhotos'])->name('admin.users.photos');
    Route::post('admin/users/{user}/deactivate', [AdminUserController::class, 'deactivate'])->name('admin.users.deactivate');
    Route::post('admin/users/{user}/activate', [AdminUserController::class, 'activate'])->name('admin.users.activate');
    Route::post('admin/users/{user}/reject', [AdminUserController::class, 'reject'])->name('admin.users.reject');
    Route::delete('admin/users/{user}/rejection-note', [AdminUserController::class, 'clearRejectionNote'])->name('admin.users.rejection-note.destroy');
    Route::post('admin/users/{user}/reset-password', [AdminUserController::class, 'resetPassword'])->name('admin.users.resetPassword');
    Route::post('admin/users/changes/{change}/approve', [AdminUserController::class, 'approveChange'])->name('admin.users.changes.approve');
    Route::post('admin/users/changes/{change}/reject', [AdminUserController::class, 'rejectChange'])->name('admin.users.changes.reject');

    Route::get('admin/auth-codes', [AdminAuthCodesController::class, 'index'])->name('admin.authCodes');
    Route::post('admin/auth-codes/{code}/issue-reset', [AdminAuthCodesController::class, 'issueReset'])->name('admin.authCodes.issueReset');

    Route::get('admin/driver-inbox', [AdminDriverInboxController::class, 'index'])->name('admin.driverInbox');
    Route::post('admin/driver-inbox', [AdminDriverInboxController::class, 'send'])->name('admin.driverInbox.send');

    Route::get('admin/drivers',                     [DriverController::class, 'index'])->name('admin.drivers');
    Route::delete('admin/drivers/bulk',             [DriverController::class, 'bulkDestroy'])->name('admin.drivers.bulkDestroy');
    Route::get('admin/drivers/{driverProfile}/edit', [DriverController::class, 'edit'])->name('admin.drivers.edit');
    Route::patch('admin/drivers/{driverProfile}',   [DriverController::class, 'update'])->name('admin.drivers.update');
    Route::post('admin/drivers/{driverProfile}/invite', [DriverController::class, 'storeInvite'])->name('admin.drivers.invite.store');
    Route::patch('admin/drivers/{driverProfile}/invite', [DriverController::class, 'updateInvite'])->name('admin.drivers.invite.update');
    Route::post('admin/drivers/{driverProfile}/invite/suspend', [DriverController::class, 'destroyInvite'])->name('admin.drivers.invite.suspend');
    Route::post('admin/drivers/{driverProfile}/invite/restore', [DriverController::class, 'restoreInvite'])->name('admin.drivers.invite.restore');
    Route::delete('admin/drivers/{driverProfile}/invite', [DriverController::class, 'destroyInvite'])->name('admin.drivers.invite.destroy');
    Route::post('admin/drivers/{driverProfile}/approve', [DriverController::class, 'approve'])->name('admin.drivers.approve');
    Route::post('admin/drivers/{driverProfile}/reject', [DriverController::class, 'reject'])->name('admin.drivers.reject');
    Route::delete('admin/drivers/{driverProfile}/rejection-note', [DriverController::class, 'clearRejectionNote'])->name('admin.drivers.rejection-note.destroy');
    Route::post('admin/drivers/{driverProfile}/unlock', [DriverController::class, 'unlock'])->name('admin.drivers.unlock');
    Route::post('admin/drivers/{driverProfile}/reset-cancel-rate', [DriverController::class, 'resetCancelRate'])->name('admin.drivers.resetCancelRate');
    Route::post('admin/drivers/{driverProfile}/reset-password', [DriverController::class, 'resetPassword'])->name('admin.drivers.resetPassword');
    Route::post('admin/drivers/{driverProfile}/photos', [DriverController::class, 'uploadPhotos'])->name('admin.drivers.photos');
    Route::post('admin/drivers/{driverProfile}/activate', [DriverController::class, 'activate'])->name('admin.drivers.activate');
    Route::delete('admin/drivers/{driverProfile}',  [DriverController::class, 'destroy'])->name('admin.drivers.destroy');
    Route::post('admin/drivers/{driverProfile}/changes/{changeRequest}/approve', [DriverSettingsController::class, 'approveChange'])
        ->name('admin.drivers.changes.approve');
    Route::post('admin/drivers/{driverProfile}/changes/{changeRequest}/reject', [DriverSettingsController::class, 'rejectChange'])
        ->name('admin.drivers.changes.reject');

    Route::get('admin/nap-vi', [AdminWalletController::class, 'index'])->name('admin.walletDeposits');
    Route::get('admin/driver-wallet', [AdminWalletController::class, 'driverWallet'])->name('admin.driverWallet');
    Route::get('admin/customer-wallet', [AdminWalletController::class, 'customerWallet'])->name('admin.customerWallet');
    Route::post('admin/wallet-transactions/{transaction}/approve', [AdminWalletController::class, 'approveDeposit'])->name('admin.walletTransactions.approve');
    Route::post('admin/wallet-transactions/{transaction}/reject', [AdminWalletController::class, 'rejectDeposit'])->name('admin.walletTransactions.reject');
    Route::post('admin/wallet-transactions/approve-bulk', [AdminWalletController::class, 'approveDepositsBulk'])->name('admin.walletTransactions.approveBulk');
    Route::post('admin/customer-wallet-transactions/{transaction}/approve', [AdminWalletController::class, 'approveCustomerDeposit'])->name('admin.customerWallet.approve');
    Route::post('admin/customer-wallet-transactions/{transaction}/reject', [AdminWalletController::class, 'rejectCustomerDeposit'])->name('admin.customerWallet.reject');
});
