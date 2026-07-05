<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\ReferralCode;
use App\Support\PageList;
use App\Support\PlatformFees;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AdminRevenueService
{
    public function __construct(private readonly ReferralCodeService $referralCodes)
    {
    }

    /**
     * @return array{
     *   trips: int,
     *   revenue: int,
     *   app_fee: int,
     *   referral_commission: int,
     *   app_percent: float,
     *   referral_percent_default: float
     * }
     */
    public function completedTripsSummary(): array
    {
        $trips = 0;
        $revenue = 0;
        $appFee = 0;
        $referralCommission = 0;

        $this->completedTripsQuery()
            ->with('appliedReferralCode')
            ->chunkById(200, function ($bookings) use (&$trips, &$revenue, &$appFee, &$referralCommission): void {
                foreach ($bookings as $booking) {
                    $trips++;
                    $revenue += $booking->tripRevenueAmount();
                    $appFee += $booking->projectedPlatformFeeAmount();
                    $referralCommission += $booking->referrerCommissionAmount();
                }
            });

        return [
            'trips'                    => $trips,
            'revenue'                  => $revenue,
            'app_fee'                  => $appFee,
            'referral_commission'      => $referralCommission,
            'app_percent'              => PlatformFees::appCommissionPercent(),
            'referral_percent_default' => PlatformFees::referralCommissionFirstPercent(),
        ];
    }

    /** @return \Illuminate\Database\Eloquent\Builder<Booking> */
    public function completedTripsQuery()
    {
        return Booking::query()
            ->visibleOnOperatorDashboard()
            ->where('trip_status', 'completed')
            ->whereNotIn('booking_status', ['cancelled', 'rejected']);
    }

    public function paginatedCompletedTrips(Request $request): LengthAwarePaginator
    {
        return $this->completedTripsQuery()
            ->with([
                'schedule.route',
                'schedule.template',
                'schedule.assignedDriverProfile.user',
                'assignedDriver.driverProfile.user',
                'appliedReferralCode',
            ])
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->paginate(PageList::PER_PAGE)
            ->withQueryString();
    }

    /**
     * @return Collection<int, array{referral: ReferralCode, trips: int, revenue: int, commission: int}>
     */
    public function referrerSummaryRows(): Collection
    {
        $referrers = ReferralCode::query()
            ->where('type', ReferralCode::TYPE_REFERRER)
            ->orderBy('name')
            ->get();

        $stats = $this->referralCodes->commissionStatsForReferralIds(
            $referrers->pluck('id')->map(fn ($id) => (int) $id)->all(),
        );

        return $referrers
            ->map(fn (ReferralCode $referral) => [
                'referral'   => $referral,
                'trips'      => $stats[$referral->id]['trips'] ?? 0,
                'revenue'    => $stats[$referral->id]['revenue'] ?? 0,
                'commission' => $stats[$referral->id]['commission'] ?? 0,
            ])
            ->filter(fn (array $row): bool => $row['trips'] > 0)
            ->sortByDesc('revenue')
            ->values();
    }
}
