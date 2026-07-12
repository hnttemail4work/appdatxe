<?php

use App\Http\Controllers\Web\PublicStorageController;
use App\Http\Controllers\Web\PwaController;
use App\Http\Controllers\Web\AdminController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\CancellationReasonController;
use App\Http\Controllers\Web\DriverController;
use App\Http\Controllers\Web\DriverWalletController;
use App\Http\Controllers\Web\GeocodeController;
use App\Http\Controllers\Web\GuestBookingController;
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

Route::middleware('guest')->group(function () {
    Route::get('login',    [AuthController::class, 'showLogin'])->name('login');
    Route::post('login',   [AuthController::class, 'login']);
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register',[AuthController::class, 'register']);
});

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
Route::get('booking/check-duplicate', [GuestBookingController::class, 'checkDuplicateBooking'])->name('booking.checkDuplicate');
Route::get('booking/driver-offers', [GuestBookingController::class, 'driverOffers'])->name('booking.driverOffers');
Route::get('quote-price', [GuestBookingController::class, 'quotePrice'])->name('booking.quotePrice');
Route::post('bookings', [GuestBookingController::class, 'store'])->name('booking.store');
Route::get('bookings', fn () => redirect()->route('home'));
Route::get('geocode/reverse', [GeocodeController::class, 'reverse'])->name('geocode.reverse');
Route::get('geocode/search', [GeocodeController::class, 'search'])->name('geocode.search');
Route::get('geocode/resolve', [GeocodeController::class, 'resolve'])->name('geocode.resolve');
Route::get('cancellation-reasons', [CancellationReasonController::class, 'index'])->name('cancellationReasons.index');

Route::permanentRedirect('dat-xe', '/');
Route::redirect('guest/orders', '/chuyen');
Route::post('dat-xe/bookings', [GuestBookingController::class, 'store']);

// ── Driver ────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:driver'])->group(function () {
    Route::get('driver/dashboard', [DriverController::class, 'myDashboard'])->name('driver.dashboard');
    Route::get('driver/dashboard/poll', [DriverController::class, 'dashboardPoll'])->name('driver.dashboard.poll');
    Route::post('driver/location', [DriverController::class, 'updateLocation'])->name('driver.location.update');
    Route::post('driver/trip-requests/{driverTripRequest}/accept', [DriverController::class, 'acceptTripRequest'])->name('driver.tripRequests.accept');
    Route::post('driver/trip-requests/{driverTripRequest}/reject', [DriverController::class, 'rejectTripRequest'])->name('driver.tripRequests.reject');
    Route::patch('driver/availability', [DriverController::class, 'updateAvailability'])->name('driver.availability.update');
    Route::patch('driver/password', [DriverController::class, 'updatePassword'])->name('driver.password.update');
    Route::post('driver/bookings/{booking}/complete', [DriverController::class, 'completeTrip'])->name('driver.bookings.complete');
    Route::post('driver/schedules/{schedule}/advance', [DriverController::class, 'advanceSchedule'])->name('driver.schedules.advance');
    Route::post('driver/schedules/{schedule}/confirm-movement', [DriverController::class, 'confirmMovement'])->name('driver.schedules.confirmMovement');
    Route::post('driver/schedules/{schedule}/complete', [DriverController::class, 'completeSchedule'])->name('driver.schedules.complete');
    Route::post('driver/schedules/{schedule}/cancel', [DriverController::class, 'cancelSchedule'])->name('driver.schedules.cancel');
    Route::post('driver/schedules/{schedule}/late-pickup-continue', [DriverController::class, 'latePickupContinue'])->name('driver.schedules.latePickupContinue');
    Route::post('driver/wallet/deposit', [DriverWalletController::class, 'deposit'])->name('driver.wallet.deposit');
});

// ── Admin ──────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('admin/referrals',              [AdminController::class, 'referrals'])->name('admin.referrals');
    Route::get('admin/dashboard',              [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::post('admin/referrers',           [AdminController::class, 'storeReferrer'])->name('admin.referrers.store');
    Route::patch('admin/referrers/{referralCode}', [AdminController::class, 'updateReferrer'])->name('admin.referrers.update');
    Route::delete('admin/referral-codes/{referralCode}', [AdminController::class, 'destroyReferralCode'])->name('admin.referralCodes.destroy');
    Route::post('admin/referrers/{referralCode}/hide', [AdminController::class, 'suspendReferrer'])->name('admin.referrers.hide');
    Route::post('admin/referrers/{referralCode}/show', [AdminController::class, 'showReferrer'])->name('admin.referrers.show');
    Route::post('admin/bank-settings',         [AdminController::class, 'updateBankSettings'])->name('admin.bankSettings.update');
    Route::patch('admin/password',            [AdminController::class, 'updatePassword'])->name('admin.password.update');
    Route::post('admin/booking-page-settings', [AdminController::class, 'updateBookingPageSettings'])->name('admin.bookingPageSettings.update');
    Route::post('admin/branding-settings', [AdminController::class, 'updateBrandingSettings'])->name('admin.brandingSettings.update');
    Route::post('admin/push-settings', [AdminController::class, 'updatePushSettings'])->name('admin.pushSettings.update');
    Route::post('admin/fee-settings',         [AdminController::class, 'updateFeeSettings'])->name('admin.feeSettings.update');
    Route::post('admin/route-distances',      [AdminController::class, 'updateRouteDistances'])->name('admin.routeDistances.update');
    Route::post('admin/destinations',         [AdminController::class, 'storeDestination'])->name('admin.destinations.store');
    Route::post('admin/destinations/{tripRoute}/show', [AdminController::class, 'showDestination'])->name('admin.destinations.show');
    Route::delete('admin/destinations/{tripRoute}', [AdminController::class, 'destroyDestination'])->name('admin.destinations.destroy');

    Route::get('admin/bookings',                   [AdminController::class, 'bookings'])->name('admin.bookings');
    Route::get('admin/bookings/sync',              [AdminController::class, 'bookingsSync'])->name('admin.bookings.sync');
    Route::post('admin/bookings/{booking}/assign', [AdminController::class, 'confirmAndAssignBooking'])->name('admin.bookings.assign');
    Route::post('admin/bookings/{booking}/nudge-driver', [AdminController::class, 'nudgeDriverBooking'])->name('admin.bookings.nudgeDriver');
    Route::post('admin/bookings/{booking}/cancel', [AdminController::class, 'cancelBooking'])->name('admin.bookings.cancel');
    Route::post('admin/bookings/{booking}/later-pickup', [AdminController::class, 'dispatchLaterReturnPickup'])->name('admin.bookings.laterPickup');
    Route::delete('admin/bookings/lo', [AdminController::class, 'bulkDismissBookings'])->name('admin.bookings.bulkDismiss');

    Route::get('admin/revenue', [AdminController::class, 'revenueReport'])->name('admin.revenue');

    Route::get('admin/drivers',                     [DriverController::class, 'index'])->name('admin.drivers');
    Route::get('admin/drivers/{driverProfile}/edit', [DriverController::class, 'edit'])->name('admin.drivers.edit');
    Route::patch('admin/drivers/{driverProfile}',   [DriverController::class, 'update'])->name('admin.drivers.update');
    Route::post('admin/drivers/{driverProfile}/approve', [DriverController::class, 'approve'])->name('admin.drivers.approve');
    Route::post('admin/drivers/{driverProfile}/reject', [DriverController::class, 'reject'])->name('admin.drivers.reject');
    Route::delete('admin/drivers/{driverProfile}/rejection-note', [DriverController::class, 'clearRejectionNote'])->name('admin.drivers.rejection-note.destroy');
    Route::post('admin/drivers/{driverProfile}/unlock', [DriverController::class, 'unlock'])->name('admin.drivers.unlock');
    Route::post('admin/drivers/{driverProfile}/reset-cancel-rate', [DriverController::class, 'resetCancelRate'])->name('admin.drivers.resetCancelRate');
    Route::post('admin/drivers/{driverProfile}/reset-password', [DriverController::class, 'resetPassword'])->name('admin.drivers.resetPassword');
    Route::post('admin/drivers/{driverProfile}/photos', [DriverController::class, 'uploadPhotos'])->name('admin.drivers.photos');
    Route::delete('admin/drivers/{driverProfile}',  [DriverController::class, 'destroy'])->name('admin.drivers.destroy');

    Route::get('admin/driver-wallet', [AdminController::class, 'driverWallet'])->name('admin.driverWallet');
    Route::post('admin/wallet-transactions/{transaction}/approve', [AdminController::class, 'approveDeposit'])->name('admin.walletTransactions.approve');
    Route::post('admin/wallet-transactions/{transaction}/reject', [AdminController::class, 'rejectDeposit'])->name('admin.walletTransactions.reject');
    Route::post('admin/wallet-transactions/approve-bulk', [AdminController::class, 'approveDepositsBulk'])->name('admin.walletTransactions.approveBulk');
});
