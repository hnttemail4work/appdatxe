<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\PlatformSetting;
use App\Models\Payout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RevenueAnalyticsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $startDate = isset($validated['start_date']) ? Carbon::parse($validated['start_date'])->startOfDay() : now()->startOfMonth();
        $endDate = isset($validated['end_date']) ? Carbon::parse($validated['end_date'])->endOfDay() : now()->endOfDay();
        $commissionPercentage = (float) data_get(PlatformSetting::getValue('commission_percentage', ['value' => 10]), 'value', 10);

        $grossRevenue = (float) Booking::query()
            ->whereBetween(DB::raw('COALESCE(confirmed_at, created_at)'), [$startDate, $endDate])
            ->where('payment_status', 'paid')
            ->whereIn('trip_status', ['confirmed', 'completed'])
            ->sum('total_price');

        $commissionAmount = round($grossRevenue * $commissionPercentage / 100, 2);
        $operatorPayoutsTotal = round($grossRevenue - $commissionAmount, 2);

        $payouts = Payout::query()
            ->with('operator')
            ->whereBetween('generated_at', [$startDate, $endDate])
            ->orderByDesc('generated_at')
            ->get();

        $operatorBreakdown = Booking::query()
            ->selectRaw('vehicles.operator_id as operator_id, users.name as operator_name, SUM(bookings.total_price) as gross_amount')
            ->join('schedules', 'bookings.schedule_id', '=', 'schedules.id')
            ->join('vehicles', 'schedules.vehicle_id', '=', 'vehicles.id')
            ->join('users', 'vehicles.operator_id', '=', 'users.id')
            ->whereBetween(DB::raw('COALESCE(bookings.confirmed_at, bookings.created_at)'), [$startDate, $endDate])
            ->where('bookings.payment_status', 'paid')
            ->whereIn('bookings.trip_status', ['confirmed', 'completed'])
            ->groupBy('vehicles.operator_id', 'users.name')
            ->get()
            ->map(function ($row) use ($commissionPercentage): array {
                $grossAmount = (float) $row->gross_amount;
                $commissionAmount = round($grossAmount * $commissionPercentage / 100, 2);

                return [
                    'operator_id' => (int) $row->operator_id,
                    'operator_name' => $row->operator_name,
                    'gross_amount' => $grossAmount,
                    'commission_amount' => $commissionAmount,
                    'net_amount' => round($grossAmount - $commissionAmount, 2),
                ];
            });

        return response()->json([
            'data' => [
                'range' => [
                    'start_date' => $startDate->toDateTimeString(),
                    'end_date' => $endDate->toDateTimeString(),
                ],
                'gross_revenue' => round($grossRevenue, 2),
                'platform_commission_rate' => $commissionPercentage,
                'platform_commission_earned' => round($commissionAmount, 2),
                'operator_payouts_total' => $operatorPayoutsTotal,
                'payout_records' => $payouts,
                'operator_breakdown' => $operatorBreakdown,
            ],
        ]);
    }
}
