<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\DriverTripSettlement;
use App\Models\TripLedger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CompanyRevenueService
{
    /** @return array{from: string, to: string, trip_count: int, cancelled_customer: int, cancelled_driver: int, total_trips: int, completion_rate: float, total_revenue: int, referral_cost: int, driver_revenue: int, net_revenue: int} */
    public function summary(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = ($from ?? now()->startOfMonth())->copy()->startOfDay();
        $to = ($to ?? now())->copy()->endOfDay();

        $bookings = Booking::query()
            ->with('appliedReferralCode')
            ->where('trip_status', 'completed')
            ->whereNotIn('booking_status', ['cancelled', 'rejected'])
            ->whereBetween('completed_at', [$from, $to])
            ->get();

        $totalRevenue = (int) $bookings->sum(fn (Booking $b): int => (int) round((float) $b->total_price, 0));

        $referralCost = (int) $bookings->sum(fn (Booking $b): int => $b->referralCommissionAmount());

        $driverRevenue = (int) DriverTripSettlement::query()
            ->where('status', 'completed')
            ->whereBetween('driver_settled_at', [$from, $to])
            ->sum('revenue_amount');

        $outcomeCounts = app(TripLedgerService::class)->countByOutcome($from, $to);
        $completed = $outcomeCounts['completed'];
        $totalTrips = $completed + $outcomeCounts['cancelled_customer'] + $outcomeCounts['cancelled_driver'];
        $completionRate = $totalTrips > 0 ? round($completed / $totalTrips * 100, 1) : 0.0;

        return [
            'from'               => $from->toDateString(),
            'to'                 => $to->toDateString(),
            'trip_count'         => $completed,
            'cancelled_customer' => $outcomeCounts['cancelled_customer'],
            'cancelled_driver'   => $outcomeCounts['cancelled_driver'],
            'total_trips'        => $totalTrips,
            'completion_rate'    => $completionRate,
            'total_revenue'      => $totalRevenue,
            'referral_cost'      => $referralCost,
            'driver_revenue'     => $driverRevenue,
            'net_revenue'        => max($totalRevenue - $referralCost - $driverRevenue, 0),
        ];
    }

    public function referralCostTrips(?Carbon $from = null, ?Carbon $to = null, int $perPage = 20): LengthAwarePaginator
    {
        $from = ($from ?? now()->startOfMonth())->copy()->startOfDay();
        $to = ($to ?? now())->copy()->endOfDay();

        return Booking::query()
            ->with(['appliedReferralCode', 'schedule.route'])
            ->where('trip_status', 'completed')
            ->whereNotIn('booking_status', ['cancelled', 'rejected'])
            ->whereNotNull('applied_referral_code_id')
            ->whereBetween('completed_at', [$from, $to])
            ->orderByDesc('completed_at')
            ->paginate($perPage, ['*'], 'referral_costs_page')
            ->withQueryString();
    }

    /** @return Collection<int, object{route_label: string, trips: int, revenue: int}> */
    public function routeBreakdown(?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $from = ($from ?? now()->startOfMonth())->copy()->startOfDay();
        $to = ($to ?? now())->copy()->endOfDay();

        return TripLedger::query()
            ->where('outcome', TripLedger::OUTCOME_COMPLETED)
            ->whereBetween('recorded_at', [$from, $to])
            ->whereNotNull('route_label')
            ->selectRaw('route_label, COUNT(*) as trips, COALESCE(SUM(amount), 0) as revenue')
            ->groupBy('route_label')
            ->orderByDesc('revenue')
            ->get();
    }
}
