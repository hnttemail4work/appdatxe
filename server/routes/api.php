<?php

use App\Http\Controllers\Api\Admin\CommissionSettingController;
use App\Http\Controllers\Api\Admin\MerchantApprovalController;
use App\Http\Controllers\Api\Admin\OrderAuditController;
use App\Http\Controllers\Api\Admin\RevenueAnalyticsController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Customer\BookingController;
use App\Http\Controllers\Api\Customer\TripSearchController;
use App\Http\Controllers\Api\Operator\PassengerController;
use App\Http\Controllers\Api\Operator\ScheduleController;
use App\Http\Controllers\Api\Operator\VehicleController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::middleware(['auth:sanctum', 'role:customer'])->prefix('customer')->group(function (): void {
    Route::get('trips/search', [TripSearchController::class, 'index']);
    Route::get('trips/{schedule}', [TripSearchController::class, 'show']);

    Route::get('bookings', [BookingController::class, 'index']);
    Route::post('bookings', [BookingController::class, 'store']);
    Route::get('bookings/{booking}', [BookingController::class, 'show']);
    Route::patch('bookings/{booking}', [BookingController::class, 'update']);
    Route::delete('bookings/{booking}', [BookingController::class, 'destroy']);
    Route::get('bookings/{booking}/deposit-payload', [BookingController::class, 'depositPayload']);
    Route::post('bookings/{booking}/confirm-payment', [BookingController::class, 'confirmPayment']);
});

Route::middleware(['auth:sanctum', 'role:operator,admin'])->prefix('operator')->group(function (): void {
    Route::apiResource('vehicles', VehicleController::class);
    Route::apiResource('schedules', ScheduleController::class);
    Route::get('schedules/{schedule}/seat-grid', [ScheduleController::class, 'seatGrid']);
    Route::get('passengers', [PassengerController::class, 'index']);

    Route::post('bookings/{booking}/accept', [BookingController::class, 'accept']);
    Route::post('bookings/{booking}/reject', [BookingController::class, 'reject']);
});

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function (): void {
    Route::get('merchants', [MerchantApprovalController::class, 'index']);
    Route::get('merchants/{merchantProfile}', [MerchantApprovalController::class, 'show']);
    Route::patch('merchants/{merchantProfile}', [MerchantApprovalController::class, 'update']);
    Route::post('merchants/{merchantProfile}/approve', [MerchantApprovalController::class, 'approve']);
    Route::post('merchants/{merchantProfile}/suspend', [MerchantApprovalController::class, 'suspend']);
    Route::post('merchants/{merchantProfile}/reject', [MerchantApprovalController::class, 'reject']);

    Route::get('commission-settings', [CommissionSettingController::class, 'show']);
    Route::put('commission-settings', [CommissionSettingController::class, 'update']);

    Route::get('orders', [OrderAuditController::class, 'index']);
    Route::get('orders/{booking}', [OrderAuditController::class, 'show']);

    Route::get('analytics/revenue', [RevenueAnalyticsController::class, 'index']);
});
