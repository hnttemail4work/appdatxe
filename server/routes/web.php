<?php

use App\Http\Controllers\Web\AdminController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\DriverController;
use App\Http\Controllers\Web\DriverWalletController;
use App\Http\Controllers\Web\GuestBookingController;
use App\Http\Controllers\Web\LiveSyncController;
use App\Http\Controllers\Web\OperatorController;
use App\Support\RoleDashboard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
Route::get('/', function () {
    return view('home');
})->name('home');

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
        return redirect()->route('booking.index', $request->query());
    }

    return match (auth()->user()->role) {
        'driver'   => redirect()->route('driver.dashboard'),
        'operator' => redirect()->route('operator.dashboard'),
        'admin'    => redirect()->route('admin.dashboard'),
        default    => redirect()->route('booking.index', $request->query()),
    };
})->name('trips.search');

// ── Guest booking (public) ─────────────────────────────────────────────────
Route::get('dat-xe', [GuestBookingController::class, 'index'])->name('booking.index');
Route::get('dat-xe/live-sync', [GuestBookingController::class, 'liveSync'])->name('booking.liveSync');
Route::get('dat-xe/available-drivers', [GuestBookingController::class, 'availableDrivers'])->name('booking.availableDrivers');
Route::get('dat-xe/seat-availability', [GuestBookingController::class, 'seatAvailability'])->name('booking.seatAvailability');
Route::get('dat-xe/quote-price', [GuestBookingController::class, 'quotePrice'])->name('booking.quotePrice');
Route::post('dat-xe/bookings', [GuestBookingController::class, 'store'])->name('booking.store');

// ── Driver ────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:driver'])->group(function () {
    Route::get('driver/dashboard', [DriverController::class, 'myDashboard'])->name('driver.dashboard');
    Route::get('driver/live-sync', [LiveSyncController::class, 'driver'])->name('driver.liveSync');
    Route::post('driver/trip-requests/{driverTripRequest}/accept', [DriverController::class, 'acceptTripRequest'])->name('driver.tripRequests.accept');
    Route::post('driver/trip-requests/{driverTripRequest}/reject', [DriverController::class, 'rejectTripRequest'])->name('driver.tripRequests.reject');
    Route::get('driver/profile', [DriverController::class, 'myProfile'])->name('driver.profile');
    Route::patch('driver/availability', [DriverController::class, 'updateAvailability'])->name('driver.availability.update');
    Route::patch('driver/profile', [DriverController::class, 'updateMyProfile'])->name('driver.profile.update');
    Route::post('driver/photos', [DriverController::class, 'uploadMyPhotos'])->name('driver.photos.upload');
    Route::post('driver/bookings/{booking}/complete', [DriverController::class, 'completeTrip'])->name('driver.bookings.complete');
    Route::post('driver/schedules/{schedule}/complete', [DriverController::class, 'completeSchedule'])->name('driver.schedules.complete');
    Route::post('driver/settlements/{settlement}/settle', [DriverWalletController::class, 'settle'])->name('driver.settlements.settle');
    Route::post('driver/settlements/{settlement}/confirm-transfer', [DriverWalletController::class, 'confirmSettlementTransfer'])->name('driver.settlements.confirmTransfer');
    Route::post('driver/wallet/deposit', [DriverWalletController::class, 'deposit'])->name('driver.wallet.deposit');
    Route::redirect('driver/wallet', '/driver/dashboard?tab=trips')->name('driver.wallet');
});

// ── Operator ───────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:operator'])->group(function () {
    Route::get('operator/dashboard',                   [OperatorController::class, 'dashboard'])->name('operator.dashboard');
    Route::get('operator/live-sync',                   [LiveSyncController::class, 'operator'])->name('operator.liveSync');
    Route::post('operator/bookings/{booking}/assign', [OperatorController::class, 'confirmAndAssignBooking'])->name('operator.bookings.assign');

    Route::get('operator/drivers',                     [DriverController::class, 'index'])->name('operator.drivers');
    Route::get('operator/drivers/{driverProfile}/edit', [DriverController::class, 'edit'])->name('operator.drivers.edit');
    Route::patch('operator/drivers/{driverProfile}',   [DriverController::class, 'update'])->name('operator.drivers.update');
    Route::post('operator/drivers/{driverProfile}/approve', [DriverController::class, 'approve'])->name('operator.drivers.approve');
    Route::post('operator/drivers/{driverProfile}/reject', [DriverController::class, 'reject'])->name('operator.drivers.reject');
    Route::post('operator/drivers/{driverProfile}/unlock', [DriverController::class, 'unlock'])->name('operator.drivers.unlock');
    Route::post('operator/drivers/{driverProfile}/photos', [DriverController::class, 'uploadPhotos'])->name('operator.drivers.photos');
    Route::delete('operator/drivers/{driverProfile}',  [DriverController::class, 'destroy'])->name('operator.drivers.destroy');

    Route::get('operator/driver-wallet', [OperatorController::class, 'driverWallet'])->name('operator.driverWallet');
    Route::post('operator/settlements/{settlement}/issue-code', [OperatorController::class, 'issueSettlementCode'])->name('operator.settlements.issueCode');
    Route::post('operator/wallet-transactions/{transaction}/approve', [OperatorController::class, 'approveDeposit'])->name('operator.walletTransactions.approve');
});

// ── Admin ──────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('admin/dashboard',              [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::post('admin/operators',            [AdminController::class, 'storeOperator'])->name('admin.operators.store');
    Route::post('admin/fee-settings',         [AdminController::class, 'updateFeeSettings'])->name('admin.feeSettings.update');
    Route::patch('admin/users/{user}/status',  [AdminController::class, 'updateUserStatus'])->name('admin.users.status');
});
