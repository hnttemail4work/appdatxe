<?php

use App\Http\Controllers\Api\Admin\OrderAuditController;
use App\Http\Controllers\Api\Admin\RevenueAnalyticsController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\BookingActionController;
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

Route::middleware(['auth:sanctum', 'role:operator'])->prefix('operator')->group(function (): void {
    Route::apiResource('vehicles', VehicleController::class);
    Route::apiResource('schedules', ScheduleController::class);
    Route::get('schedules/{schedule}/seat-grid', [ScheduleController::class, 'seatGrid']);
    Route::get('passengers', [PassengerController::class, 'index']);
});

Route::middleware(['auth:sanctum', 'role:driver'])->prefix('driver')->group(function (): void {
    Route::post('bookings/{booking}/complete', [BookingActionController::class, 'driverCompleteTrip']);
});

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function (): void {
    Route::get('orders', [OrderAuditController::class, 'index']);
    Route::get('orders/{booking}', [OrderAuditController::class, 'show']);
    Route::get('analytics/revenue', [RevenueAnalyticsController::class, 'index']);
});
