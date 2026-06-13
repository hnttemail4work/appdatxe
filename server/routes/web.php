<?php

use App\Http\Controllers\Web\AdminController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\CustomerController;
use App\Http\Controllers\Web\DriverController;
use App\Http\Controllers\Web\OperatorController;
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

// ── Customer ───────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:customer'])->group(function () {
    Route::get('customer/dashboard',                       [CustomerController::class, 'dashboard'])->name('customer.dashboard');
    Route::post('customer/bookings',                       [CustomerController::class, 'store'])->name('bookings.store');
    Route::post('customer/bookings/{booking}/mark-paid',   [CustomerController::class, 'markPaid'])->name('bookings.markPaid');
    Route::post('customer/bookings/{booking}/cancel',      [CustomerController::class, 'cancel'])->name('bookings.cancel');
});

// ── Driver ────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:driver'])->group(function () {
    Route::get('driver/dashboard', [DriverController::class, 'myDashboard'])->name('driver.dashboard');
    Route::patch('driver/availability', [DriverController::class, 'updateAvailability'])->name('driver.availability.update');
});

// ── Operator ───────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:operator,admin'])->group(function () {
    Route::get('operator/dashboard',                   [OperatorController::class, 'dashboard'])->name('operator.dashboard');
    Route::post('operator/vehicles',                   [OperatorController::class, 'storeVehicle'])->name('operator.vehicles.store');
    Route::post('operator/schedules',                  [OperatorController::class, 'storeSchedule'])->name('operator.schedules.store');
    Route::post('operator/bookings/{booking}/accept',  [OperatorController::class, 'acceptBooking'])->name('operator.bookings.accept');
    Route::post('operator/bookings/{booking}/reject',  [OperatorController::class, 'rejectBooking'])->name('operator.bookings.reject');

    Route::get('operator/drivers',                     [DriverController::class, 'index'])->name('operator.drivers');
    Route::post('operator/drivers',                    [DriverController::class, 'store'])->name('operator.drivers.store');
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
});
