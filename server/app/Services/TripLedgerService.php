<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\Schedule;
use App\Models\TripLedger;
use Illuminate\Support\Carbon;

class TripLedgerService
{
    /** @param array{route_label?: string, actor_label?: string, actor_code?: string, amount?: int} $context */
    public function recordForSchedule(Schedule $schedule, string $outcome, array $context = []): void
    {
        $schedule->loadMissing(['route', 'driver', 'bookings']);
        $code = $schedule->shortTripCode();
        if ($code === '') {
            return;
        }

        $payload = array_merge($this->buildScheduleContext($schedule, $outcome), $context);
        $this->record($code, $outcome, $payload);
    }

    /** @param array{route_label?: string, actor_label?: string, actor_code?: string, amount?: int} $context */
    public function record(string $tripCode, string $outcome, array $context = []): void
    {
        $tripCode = trim($tripCode);
        if ($tripCode === '') {
            return;
        }

        TripLedger::query()->updateOrCreate(
            ['trip_code' => $tripCode],
            [
                'outcome'     => $outcome,
                'route_label' => $context['route_label'] ?? null,
                'actor_label' => $context['actor_label'] ?? null,
                'actor_code'  => $context['actor_code'] ?? null,
                'amount'      => isset($context['amount']) ? (int) $context['amount'] : null,
                'recorded_at' => now(),
            ],
        );
    }

    /** @return array{route_label: string|null, actor_label: string|null, actor_code: string|null, amount: int|null} */
    private function buildScheduleContext(Schedule $schedule, string $outcome): array
    {
        $routeLabel = $this->routeLabel($schedule);

        if ($outcome === TripLedger::OUTCOME_COMPLETED) {
            return $this->completedContext($schedule, $routeLabel);
        }

        return ['route_label' => $routeLabel];
    }

    private function routeLabel(Schedule $schedule): ?string
    {
        $schedule->loadMissing(['route', 'bookings']);
        $booking = $schedule->driverRelevantBookings()->first()
            ?? $schedule->bookings->first();

        if ($booking) {
            $label = trim($booking->routeDetailLabel());

            return $label !== '' && $label !== '—' ? $label : null;
        }

        if (! $schedule->route) {
            return null;
        }

        $label = trim(($schedule->route->departure ?? '') . ' → ' . ($schedule->route->destination ?? ''));

        return $label !== '→' ? $label : null;
    }

    /** @return array{route_label: string|null, actor_label: string|null, actor_code: string|null, amount: int} */
    private function completedContext(Schedule $schedule, ?string $routeLabel): array
    {
        $profile = $schedule->driver_id
            ? DriverProfile::query()->with('user')->where('user_id', $schedule->driver_id)->first()
            : null;

        $amount = (int) $schedule->bookings
            ->filter(fn (Booking $b): bool => $b->trip_status === 'completed'
                && ! in_array($b->booking_status, ['cancelled', 'rejected'], true))
            ->sum(fn (Booking $b): int => (int) round((float) $b->total_price, 0));

        return [
            'route_label' => $routeLabel,
            'actor_label' => $profile?->user?->name ?? $schedule->driver_name ?? 'Tài xế',
            'actor_code'  => $profile?->driver_code,
            'amount'      => $amount,
        ];
    }

    /** @return array{completed: int, cancelled_customer: int, cancelled_driver: int} */
    public function countByOutcome(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = ($from ?? now()->startOfMonth())->copy()->startOfDay();
        $to = ($to ?? now())->copy()->endOfDay();

        $rows = TripLedger::query()
            ->selectRaw('outcome, COUNT(*) as total')
            ->whereBetween('recorded_at', [$from, $to])
            ->groupBy('outcome')
            ->pluck('total', 'outcome');

        return [
            'completed'          => (int) ($rows[TripLedger::OUTCOME_COMPLETED] ?? 0),
            'cancelled_customer' => (int) ($rows[TripLedger::OUTCOME_CANCELLED_CUSTOMER] ?? 0),
            'cancelled_driver'   => (int) ($rows[TripLedger::OUTCOME_CANCELLED_DRIVER] ?? 0),
        ];
    }
}
