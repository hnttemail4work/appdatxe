<?php

use App\Http\Controllers\Web\AdminController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\CancellationReasonController;
use App\Http\Controllers\Web\DriverController;
use App\Http\Controllers\Web\DriverWalletController;
use App\Http\Controllers\Web\GeocodeController;
use App\Http\Controllers\Web\GuestBookingController;
use App\Http\Controllers\Web\GuestTripWatchController;
use App\Http\Controllers\Web\LiveSyncController;
use App\Http\Controllers\Web\OperatorController;
use App\Http\Controllers\Web\OperatorTripOfferController;
use App\Support\RoleDashboard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('csrf-token', function () {
    return response()->json(['token' => csrf_token()]);
});

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
        'driver'   => redirect()->route('driver.dashboard'),
        'operator' => redirect()->route('operator.dashboard'),
        'admin'    => redirect()->route('admin.dashboard'),
        default    => redirect()->route('home', $request->query()),
    };
})->name('trips.search');

// ── Đặt vé (trang chủ) ─────────────────────────────────────────────────────
Route::get('/', [GuestBookingController::class, 'index'])->name('home');
Route::get('live-sync', [GuestBookingController::class, 'liveSync'])->name('booking.liveSync');
Route::get('available-drivers', [GuestBookingController::class, 'availableDrivers'])->name('booking.availableDrivers');
Route::get('seat-availability', [GuestBookingController::class, 'seatAvailability'])->name('booking.seatAvailability');
Route::get('quote-price', [GuestBookingController::class, 'quotePrice'])->name('booking.quotePrice');
Route::post('bookings', [GuestBookingController::class, 'store'])->name('booking.store');
Route::get('bookings', fn () => redirect()->route('home'));
Route::get('cancellation-reasons', [CancellationReasonController::class, 'index'])->name('cancellationReasons.index');
Route::get('guest/trip-watch', [GuestTripWatchController::class, 'index'])->name('guest.tripWatch');
Route::post('guest/trip-reviews', [GuestTripWatchController::class, 'store'])->middleware('throttle:12,1')->name('guest.tripReviews.store');
Route::post('guest/bookings/cancel', [GuestTripWatchController::class, 'cancelBooking'])->middleware('throttle:12,1')->name('guest.bookings.cancel');
Route::get('geocode/reverse', [GeocodeController::class, 'reverse'])->name('geocode.reverse');
Route::get('geocode/search', [GeocodeController::class, 'search'])->name('geocode.search');

Route::permanentRedirect('dat-xe', '/');
Route::permanentRedirect('dat-xe/live-sync', '/live-sync');
Route::permanentRedirect('dat-xe/available-drivers', '/available-drivers');
Route::permanentRedirect('dat-xe/seat-availability', '/seat-availability');
Route::permanentRedirect('dat-xe/quote-price', '/quote-price');
Route::post('dat-xe/bookings', [GuestBookingController::class, 'store']);

// ── Driver ────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:driver'])->group(function () {
    Route::get('driver/dashboard', [DriverController::class, 'myDashboard'])->name('driver.dashboard');
    Route::post('driver/location', [DriverController::class, 'updateLocation'])->name('driver.location.update');
    Route::get('driver/live-sync', [LiveSyncController::class, 'driver'])->name('driver.liveSync');
    Route::post('driver/trip-requests/{driverTripRequest}/accept', [DriverController::class, 'acceptTripRequest'])->name('driver.tripRequests.accept');
    Route::post('driver/trip-requests/{driverTripRequest}/reject', [DriverController::class, 'rejectTripRequest'])->name('driver.tripRequests.reject');
    Route::redirect('driver/profile', '/driver/dashboard');
    Route::patch('driver/availability', [DriverController::class, 'updateAvailability'])->name('driver.availability.update');
    Route::post('driver/bookings/{booking}/complete', [DriverController::class, 'completeTrip'])->name('driver.bookings.complete');
    Route::post('driver/schedules/{schedule}/complete', [DriverController::class, 'completeSchedule'])->name('driver.schedules.complete');
    Route::post('driver/schedules/{schedule}/cancel', [DriverController::class, 'cancelSchedule'])->name('driver.schedules.cancel');
    Route::post('driver/wallet/deposit', [DriverWalletController::class, 'deposit'])->name('driver.wallet.deposit');
    Route::redirect('driver/wallet', '/driver/dashboard?tab=trips')->name('driver.wallet');
});

// ── Operator ───────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:operator'])->group(function () {
    Route::get('operator/dashboard',                   [OperatorController::class, 'dashboard'])->name('operator.dashboard');
    Route::post('operator/bookings/{booking}/assign', [OperatorController::class, 'confirmAndAssignBooking'])->name('operator.bookings.assign');
    Route::delete('operator/bookings/lo', [OperatorController::class, 'bulkDismissBookings'])->name('operator.bookings.bulkDismiss');
    Route::delete('operator/bookings/{booking}/dismiss-stuck', [OperatorController::class, 'dismissStuckBooking'])->name('operator.bookings.dismissStuck');

    Route::get('operator/drivers',                     [DriverController::class, 'index'])->name('operator.drivers');
    Route::get('operator/drivers/{driverProfile}/edit', [DriverController::class, 'edit'])->name('operator.drivers.edit');
    Route::patch('operator/drivers/{driverProfile}',   [DriverController::class, 'update'])->name('operator.drivers.update');
    Route::post('operator/drivers/{driverProfile}/approve', [DriverController::class, 'approve'])->name('operator.drivers.approve');
    Route::post('operator/drivers/{driverProfile}/reject', [DriverController::class, 'reject'])->name('operator.drivers.reject');
    Route::delete('operator/drivers/{driverProfile}/rejection-note', [DriverController::class, 'clearRejectionNote'])->name('operator.drivers.rejection-note.destroy');
    Route::post('operator/drivers/{driverProfile}/unlock', [DriverController::class, 'unlock'])->name('operator.drivers.unlock');
    Route::post('operator/drivers/{driverProfile}/photos', [DriverController::class, 'uploadPhotos'])->name('operator.drivers.photos');
    Route::delete('operator/drivers/{driverProfile}',  [DriverController::class, 'destroy'])->name('operator.drivers.destroy');

    Route::get('operator/dat-chuyen/quote', [OperatorTripOfferController::class, 'quote'])->name('operator.tripOffers.quote');
    Route::get('operator/dat-chuyen', [OperatorTripOfferController::class, 'create'])->name('operator.tripOffers.create');
    Route::post('operator/dat-chuyen/nhanh', [OperatorTripOfferController::class, 'bulkQuickCreate'])->name('operator.tripOffers.bulkQuick');
    Route::post('operator/dat-chuyen', [OperatorTripOfferController::class, 'store'])->name('operator.tripOffers.store');
    Route::get('operator/dat-chuyen/{scheduleTemplate}/chinh-sua', [OperatorTripOfferController::class, 'edit'])->name('operator.tripOffers.edit');
    Route::put('operator/dat-chuyen/{scheduleTemplate}', [OperatorTripOfferController::class, 'update'])->name('operator.tripOffers.update');
    Route::delete('operator/dat-chuyen/lo', [OperatorTripOfferController::class, 'bulkDestroy'])->name('operator.tripOffers.bulkDestroy');
    Route::delete('operator/dat-chuyen/{scheduleTemplate}', [OperatorTripOfferController::class, 'destroy'])->name('operator.tripOffers.destroy');

    Route::get('operator/driver-wallet', [OperatorController::class, 'driverWallet'])->name('operator.driverWallet');
    Route::post('operator/wallet-transactions/{transaction}/approve', [OperatorController::class, 'approveDeposit'])->name('operator.walletTransactions.approve');
});

// ── Admin ──────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('admin/dashboard',              [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::post('admin/operators',            [AdminController::class, 'storeOperator'])->name('admin.operators.store');
    Route::post('admin/referrers',           [AdminController::class, 'storeReferrer'])->name('admin.referrers.store');
    Route::patch('admin/referrers/{referralCode}', [AdminController::class, 'updateReferrer'])->name('admin.referrers.update');
    Route::delete('admin/referral-codes/{referralCode}', [AdminController::class, 'destroyReferralCode'])->name('admin.referralCodes.destroy');
    Route::post('admin/referrers/{referralCode}/hide', [AdminController::class, 'suspendReferrer'])->name('admin.referrers.hide');
    Route::post('admin/referrers/{referralCode}/show', [AdminController::class, 'showReferrer'])->name('admin.referrers.show');
    Route::post('admin/bank-settings',         [AdminController::class, 'updateBankSettings'])->name('admin.bankSettings.update');
    Route::post('admin/booking-banner',        [AdminController::class, 'updateBookingBanner'])->name('admin.bookingBanner.update');
    Route::delete('admin/booking-banner',      [AdminController::class, 'destroyBookingBanner'])->name('admin.bookingBanner.destroy');
    Route::post('admin/fee-settings',         [AdminController::class, 'updateFeeSettings'])->name('admin.feeSettings.update');
    Route::post('admin/route-distances',      [AdminController::class, 'updateRouteDistances'])->name('admin.routeDistances.update');
    Route::post('admin/destinations',         [AdminController::class, 'storeDestination'])->name('admin.destinations.store');
    Route::post('admin/destinations/{tripRoute}/show', [AdminController::class, 'showDestination'])->name('admin.destinations.show');
    Route::delete('admin/destinations/{tripRoute}', [AdminController::class, 'destroyDestination'])->name('admin.destinations.destroy');
    Route::post('admin/cancellation-reasons', [AdminController::class, 'storeCancellationReason'])->name('admin.cancellationReasons.store');
    Route::delete('admin/cancellation-reasons/{cancellationReason}', [AdminController::class, 'destroyCancellationReason'])->name('admin.cancellationReasons.destroy');
    Route::patch('admin/users/{user}/status',  [AdminController::class, 'updateUserStatus'])->name('admin.users.status');
});
