<?php

use App\Http\Controllers\Web\AdminController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\CustomerController;
use App\Http\Controllers\Web\DriverController;
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

Route::post('logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->get('dashboard', function () {
    return redirect(RoleDashboard::route(auth()->user()->role));
})->name('dashboard');

Route::get('trips/search', function (Request $request) {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    return match (auth()->user()->role) {
        'customer' => redirect()->route('customer.dashboard', $request->query()),
        'driver'   => redirect()->route('driver.dashboard'),
        'operator' => redirect()->route('operator.dashboard'),
        'admin'    => redirect()->route('admin.dashboard'),
        default    => redirect()->route('home'),
    };
})->name('trips.search');

// ── Customer ───────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:customer'])->group(function () {
    Route::get('customer/dashboard',                       [CustomerController::class, 'dashboard'])->name('customer.dashboard');
    Route::get('customer/live-sync',                      [LiveSyncController::class, 'customer'])->name('customer.liveSync');
    Route::post('customer/driver-requests/{driverTripRequest}/cancel', [CustomerController::class, 'cancelDriverRequest'])->name('customer.driverRequests.cancel');
    Route::post('customer/bookings',                       [CustomerController::class, 'store'])->name('bookings.store');
    Route::post('customer/bookings/{booking}/claim-payment', [CustomerController::class, 'claimPayment'])->name('bookings.claimPayment');
    Route::post('customer/bookings/{booking}/confirm-complete', [CustomerController::class, 'confirmTripComplete'])->name('bookings.confirmComplete');
    Route::post('customer/bookings/{booking}/cancel',      [CustomerController::class, 'cancel'])->name('bookings.cancel');
});

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
});

// ── Operator ───────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:operator,admin'])->group(function () {
    Route::get('operator/dashboard',                   [OperatorController::class, 'dashboard'])->name('operator.dashboard');
    Route::get('operator/live-sync',                   [LiveSyncController::class, 'operator'])->name('operator.liveSync');
    Route::post('operator/vehicles',                   [OperatorController::class, 'storeVehicle'])->name('operator.vehicles.store');
    Route::post('operator/schedules',                  [OperatorController::class, 'storeSchedule'])->name('operator.schedules.store');
    Route::post('operator/bookings/{booking}/confirm-payment', [OperatorController::class, 'confirmPayment'])->name('operator.bookings.confirmPayment');
    Route::post('operator/bookings/{booking}/accept',  [OperatorController::class, 'acceptBooking'])->name('operator.bookings.accept');
    Route::post('operator/bookings/{booking}/reject',  [OperatorController::class, 'rejectBooking'])->name('operator.bookings.reject');

    Route::get('operator/drivers',                     [DriverController::class, 'index'])->name('operator.drivers');
    Route::get('operator/drivers/create',              [DriverController::class, 'create'])->name('operator.drivers.create');
    Route::post('operator/drivers',                    [DriverController::class, 'store'])->name('operator.drivers.store');
    Route::get('operator/drivers/{driverProfile}/edit', [DriverController::class, 'edit'])->name('operator.drivers.edit');
    Route::patch('operator/drivers/{driverProfile}',   [DriverController::class, 'update'])->name('operator.drivers.update');
    Route::post('operator/drivers/{driverProfile}/photos', [DriverController::class, 'uploadPhotos'])->name('operator.drivers.photos');
    Route::delete('operator/drivers/{driverProfile}',  [DriverController::class, 'destroy'])->name('operator.drivers.destroy');
});

// ── Admin ──────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('admin/dashboard',                                        [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::post('admin/commission',                                      [AdminController::class, 'updateCommission'])->name('admin.commission.update');
    // Operator (merchant) management
    Route::post('admin/merchants/{merchantProfile}/approve',             [AdminController::class, 'approveMerchant'])->name('admin.merchants.approve');
    Route::post('admin/merchants/{merchantProfile}/suspend',             [AdminController::class, 'suspendMerchant'])->name('admin.merchants.suspend');
    Route::post('admin/merchants/{merchantProfile}/reject',              [AdminController::class, 'rejectMerchant'])->name('admin.merchants.reject');
    // User management
    Route::patch('admin/users/{user}/status',                            [AdminController::class, 'updateUserStatus'])->name('admin.users.status');
    Route::patch('admin/users/{user}',                                   [AdminController::class, 'updateUser'])->name('admin.users.update');
    // Driver profile management
    Route::patch('admin/drivers/{driverProfile}',                        [AdminController::class, 'updateDriverProfile'])->name('admin.drivers.update');
    // Booking management
    Route::get('admin/bookings',                                         [AdminController::class, 'bookings'])->name('admin.bookings');
    Route::post('admin/bookings/{booking}/confirm-payment',             [AdminController::class, 'confirmPayment'])->name('admin.bookings.confirmPayment');
    Route::post('admin/bookings/{booking}/accept',                       [AdminController::class, 'acceptBooking'])->name('admin.bookings.accept');
    Route::post('admin/bookings/{booking}/reject',                       [AdminController::class, 'rejectBooking'])->name('admin.bookings.reject');
});
