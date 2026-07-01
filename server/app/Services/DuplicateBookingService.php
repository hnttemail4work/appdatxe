<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\ScheduleTemplate;
use App\Models\TripRoute;

class DuplicateBookingService
{
    /** Đơn cùng tuyến + SĐT còn active (chưa hủy / chưa hoàn tất). */
    public function findActiveSameRoute(string $contactPhone, TripRoute $route, ?string $excludeReference = null): ?Booking
    {
        $departure = trim($route->departure);
        $destination = trim($route->destination);

        if ($departure === '' || $destination === '') {
            return null;
        }

        return Booking::query()
            ->with(['schedule.route'])
            ->whereNotIn('booking_status', ['cancelled', 'rejected'])
            ->where('trip_status', '!=', 'completed')
            ->whereHas('schedule.route', function ($query) use ($departure, $destination): void {
                $query->where('departure', $departure)
                    ->where('destination', $destination);
            })
            ->when($excludeReference, fn ($q) => $q->where('booking_reference', '!=', $excludeReference))
            ->orderByDesc('created_at')
            ->get()
            ->first(fn (Booking $booking): bool => $booking->matchesContactPhone($contactPhone));
    }

    public function findActiveSameRouteForTemplate(string $contactPhone, ScheduleTemplate $template, ?string $excludeReference = null): ?Booking
    {
        $template->loadMissing('route');

        if (! $template->route) {
            return null;
        }

        return $this->findActiveSameRoute($contactPhone, $template->route, $excludeReference);
    }

    /** @return array<string, mixed>|null */
    public function serializeDuplicate(Booking $booking): array
    {
        $booking->loadMissing('schedule.route');

        return [
            'booking_reference' => $booking->booking_reference,
            'trip_code'         => $booking->schedule?->shortTripCode() ?? '—',
            'route'             => $booking->schedule?->route
                ? $booking->schedule->route->departure . ' → ' . $booking->schedule->route->destination
                : '—',
            'service_date'      => $booking->schedule?->departure_time?->format('d/m/Y H:i'),
            'vehicle_capacity'  => $booking->vehicle_capacity,
            'vehicle_count'     => (int) ($booking->vehicle_count ?? 1),
            'booking_mode'      => $booking->booking_mode,
        ];
    }
}
