<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\DriverTripSettlement;
use App\Models\TripLedger;
use App\Support\PlatformFees;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CompanyRevenueService
{
    /** @return array{from: string, to: string, trip_count: int, cancelled_customer: int, cancelled_driver: int, total_trips: int, completion_rate: float, avg_revenue_per_trip: int, gross_revenue: int, platform_fee: int, referral_commission: int, net_estimate: int} */
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

        $gross = (int) $bookings->sum(fn (Booking $b): int => (int) round((float) $b->total_price, 0));

        $platformFee = (int) DriverTripSettlement::query()
            ->whereBetween('created_at', [$from, $to])
            ->sum('platform_fee_amount');

        if ($platformFee <= 0 && $gross > 0) {
            $platformFee = (int) round($gross * PlatformFees::appCommissionPercent() / 100, 0);
        }

        $referralCommission = (int) $bookings
            ->filter(fn (Booking $b): bool => $b->applied_referral_code_id !== null && $b->appliedReferralCode)
            ->sum(fn (Booking $b): int => (int) round(
                (float) $b->total_price * $b->appliedReferralCode->commissionPercent() / 100,
                0,
            ));

        $outcomeCounts = app(TripLedgerService::class)->countByOutcome($from, $to);
        $completed = $outcomeCounts['completed'];
        $totalTrips = $completed + $outcomeCounts['cancelled_customer'] + $outcomeCounts['cancelled_driver'];
        $completionRate = $totalTrips > 0 ? round($completed / $totalTrips * 100, 1) : 0.0;
        $avgRevenue = $completed > 0 ? (int) round($gross / $completed, 0) : 0;

        return [
            'from'                => $from->toDateString(),
            'to'                  => $to->toDateString(),
            'trip_count'          => $completed,
            'cancelled_customer'  => $outcomeCounts['cancelled_customer'],
            'cancelled_driver'    => $outcomeCounts['cancelled_driver'],
            'total_trips'         => $totalTrips,
            'completion_rate'     => $completionRate,
            'avg_revenue_per_trip'=> $avgRevenue,
            'gross_revenue'       => $gross,
            'platform_fee'        => $platformFee,
            'referral_commission' => $referralCommission,
            'net_estimate'        => max($gross - $referralCommission, 0),
        ];
    }

    /** @return Collection<int, object{actor_code: string|null, actor_label: string|null, trips: int, revenue: int}> */
    public function actorBreakdown(string $outcome, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $from = ($from ?? now()->startOfMonth())->copy()->startOfDay();
        $to = ($to ?? now())->copy()->endOfDay();

        return TripLedger::query()
            ->where('outcome', $outcome)
            ->whereBetween('recorded_at', [$from, $to])
            ->selectRaw('actor_code, actor_label, COUNT(*) as trips, COALESCE(SUM(amount), 0) as revenue')
            ->groupBy('actor_code', 'actor_label')
            ->orderByDesc('trips')
            ->orderBy('actor_label')
            ->get();
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
