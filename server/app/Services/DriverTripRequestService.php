<?php

namespace App\Services;

use App\Support\VehicleDisplay;
use App\Models\DriverProfile;
use App\Models\DriverTripRequest;
use App\Models\Schedule;
use App\Models\SeatReservation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DriverTripRequestService
{
    public const ACCEPT_WINDOW_DAYS = 1;

    public function __construct(private readonly DriverAvailabilityService $availability, private readonly DriverWalletService $wallets)
    {
    }

    public function expireStale(): void
    {
        $expired = DriverTripRequest::query()
            ->with(['schedule.route', 'schedule.vehicle'])
            ->where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($expired as $request) {
            $request->update([
                'status'       => 'expired',
                'responded_at' => now(),
            ]);

            $this->tryReassignAfterDecline($request);
        }
    }

    /** @deprecated Không khôi phục yêu cầu hết hạn — chuyển sang tài xế khác. */
    public function restoreExpiredForDriver(int $driverUserId): void
    {
    }

    /** @return Collection<int, DriverProfile> */
    public function suggestDrivers(?Schedule $schedule = null): Collection
    {
        $query = DriverProfile::query()
            ->operational()
            ->with(['user', 'operator'])
            ->orderByDesc('experience_years');

        if ($schedule?->vehicle?->operator_id) {
            $query->where('operator_id', $schedule->vehicle->operator_id);
        }

        return $query->get();
    }

    public function availabilityMeta(string $status): array
    {
        return match ($status) {
            'available' => ['label' => 'Sẵn sàng', 'color' => 'success', 'icon' => '🟢', 'suggested' => true],
            'on_trip'   => ['label' => 'Đang chạy', 'color' => 'primary', 'icon' => '🔵', 'suggested' => false],
            default     => ['label' => 'Nghỉ / Bận', 'color' => 'secondary', 'icon' => '⚫', 'suggested' => false],
        };
    }

    public function requestDriver(Schedule $schedule, string $driverCode, string $contactPhone): DriverTripRequest
    {
        $this->expireStale();

        $contactPhone = trim($contactPhone);
        if ($contactPhone === '') {
            throw new InvalidArgumentException('Thiếu số điện thoại liên hệ.');
        }

        if (! in_array($schedule->status, ['scheduled', 'running'], true)) {
            throw new InvalidArgumentException('Chuyến không còn mở để mời tài xế.');
        }

        $schedule->loadMissing('route');

        $profile = DriverProfile::query()
            ->operational()
            ->where('driver_code', strtoupper(trim($driverCode)))
            ->with('user')
            ->first();

        if (! $profile) {
            throw new InvalidArgumentException('Không tìm thấy tài xế với mã này.');
        }

        $alreadyOnTrip = $schedule->driver_id && (int) $schedule->driver_id === (int) $profile->user_id;

        if (! $alreadyOnTrip && $this->availability->isDriverBusyForSlot(
            (int) $profile->user_id,
            $schedule->route->departure,
            $schedule->route->destination,
            $schedule->departure_time,
        )) {
            throw new InvalidArgumentException('Tài xế đã full ghế khung giờ này. Vui lòng chọn tài xế khác.');
        }

        if ($schedule->driver_id) {
            if ((int) $schedule->driver_id === (int) $profile->user_id) {
                if ($schedule->bookedSeatsCount() >= $schedule->capacity()) {
                    throw new InvalidArgumentException('Chuyến này đã full ghế.');
                }

                return DriverTripRequest::query()->firstOrCreate(
                    [
                        'schedule_id'   => $schedule->id,
                        'contact_phone' => $contactPhone,
                        'driver_id'     => $profile->user_id,
                    ],
                    [
                        'status'       => 'accepted',
                        'responded_at' => now(),
                        'expires_at'   => null,
                    ],
                );
            }

            throw new InvalidArgumentException('Chuyến này đã có tài xế nhận. Chỉ có thể chọn tài xế đang phục vụ chuyến này.');
        }

        $existing = DriverTripRequest::query()
            ->where('schedule_id', $schedule->id)
            ->where('status', 'pending')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where('contact_phone', $contactPhone)
            ->first();

        if ($existing) {
            throw new InvalidArgumentException('Bạn đang chờ tài xế phản hồi cho chuyến này.');
        }

        $schedule->loadMissing('route');
        $expiresAt = now()->addDays(self::ACCEPT_WINDOW_DAYS);

        return DriverTripRequest::query()->create([
            'schedule_id'   => $schedule->id,
            'contact_phone' => $contactPhone,
            'driver_id'     => $profile->user_id,
            'status'        => 'pending',
            'expires_at'    => $expiresAt,
        ]);
    }

    public function accept(DriverTripRequest $request, int $driverUserId): void
    {
        $this->expireStale();

        if ($request->driver_id !== $driverUserId) {
            throw new InvalidArgumentException('Không có quyền xử lý yêu cầu này.');
        }

        if (! $request->isPending()) {
            throw new InvalidArgumentException('Yêu cầu không còn hiệu lực.');
        }

        $driver = DriverProfile::query()->where('user_id', $request->driver_id)->firstOrFail();
        $blockReason = $this->wallets->acceptBlockReason($driver);
        if ($blockReason) {
            throw new InvalidArgumentException($blockReason);
        }

        DB::transaction(function () use ($request): void {
            $schedule = Schedule::query()->lockForUpdate()->findOrFail($request->schedule_id);

            if ($schedule->driver_id && $schedule->driver_id !== $request->driver_id) {
                throw new InvalidArgumentException('Chuyến đã được tài xế khác nhận.');
            }

            $driver = DriverProfile::query()->where('user_id', $request->driver_id)->firstOrFail();
            $driverName = $driver->user->name;

            $schedule->update([
                'driver_id'   => $request->driver_id,
                'driver_name' => $driverName,
            ]);

            $request->update([
                'status'       => 'accepted',
                'responded_at' => now(),
            ]);

            DriverTripRequest::query()
                ->where('schedule_id', $schedule->id)
                ->where('id', '!=', $request->id)
                ->where('status', 'pending')
                ->update([
                    'status'       => 'cancelled',
                    'responded_at' => now(),
                ]);

            $this->anchorBookingForAcceptedRequest($schedule->fresh(), $request);
        });
    }

    /** Giữ vé còn hiệu lực sau khi tài xế nhận — tránh hết hạn 15 phút khi chờ phản hồi. */
    private function anchorBookingForAcceptedRequest(Schedule $schedule, DriverTripRequest $request): void
    {
        $holdUntil = $schedule->departure_time->isFuture()
            ? $schedule->departure_time->copy()->addHours(2)
            : now()->addHours(4);

        $booking = Booking::query()
            ->where('schedule_id', $schedule->id)
            ->get()
            ->first(fn (Booking $b) => $b->matchesContactPhone((string) $request->contact_phone));

        if (! $booking) {
            return;
        }

        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)) {
            if (! $booking->expired_at) {
                return;
            }

            $booking->update([
                'booking_status'  => 'pending',
                'trip_status'     => 'pending',
                'expired_at'      => null,
                'cancelled_at'    => null,
                'hold_expires_at' => $holdUntil,
            ]);

            foreach ((array) $booking->seat_numbers as $seat) {
                SeatReservation::query()->updateOrCreate(
                    [
                        'schedule_id' => $schedule->id,
                        'seat_number' => (string) $seat,
                        'booking_id'  => $booking->id,
                    ],
                    [
                        'status'            => 'held',
                        'expires_at'        => $holdUntil,
                        'reservation_token' => (string) Str::uuid(),
                    ],
                );
            }

            app(BookingWorkflowService::class)->syncScheduleAvailability($schedule);
            app(BookingWorkflowService::class)->confirmForDriverAccept($booking->fresh());

            return;
        }

        $booking->update(['hold_expires_at' => $holdUntil]);
        $booking->seatReservations()
            ->whereIn('status', ['held', 'booked'])
            ->update(['expires_at' => $holdUntil]);

        app(BookingWorkflowService::class)->confirmForDriverAccept($booking->fresh());
    }

    public function reject(DriverTripRequest $request, int $driverUserId): void
    {
        if ($request->driver_id !== $driverUserId) {
            throw new InvalidArgumentException('Không có quyền xử lý yêu cầu này.');
        }

        if (! $request->isPending()) {
            throw new InvalidArgumentException('Yêu cầu không còn hiệu lực.');
        }

        $request->update([
            'status'       => 'rejected',
            'responded_at' => now(),
        ]);

        $this->tryReassignAfterDecline($request);
    }

    private function tryReassignAfterDecline(DriverTripRequest $request): void
    {
        $request->loadMissing(['schedule.route', 'schedule.vehicle']);
        $schedule = $request->schedule;

        if (! $schedule || $schedule->driver_id || $schedule->departure_time <= now()) {
            return;
        }

        $triedDriverIds = DriverTripRequest::query()
            ->where('schedule_id', $schedule->id)
            ->where('contact_phone', $request->contact_phone)
            ->whereIn('status', ['expired', 'rejected', 'cancelled'])
            ->pluck('driver_id');

        $candidates = $this->suggestDrivers($schedule);

        foreach ($candidates as $profile) {
            if ($triedDriverIds->contains($profile->user_id)) {
                continue;
            }

            if ($this->availability->isDriverBusyForSlot(
                (int) $profile->user_id,
                $schedule->route->departure,
                $schedule->route->destination,
                $schedule->departure_time,
            )) {
                continue;
            }

            DriverTripRequest::query()->create([
                'schedule_id'   => $schedule->id,
                'contact_phone' => $request->contact_phone,
                'driver_id'     => $profile->user_id,
                'status'        => 'pending',
                'expires_at'    => now()->addDays(self::ACCEPT_WINDOW_DAYS),
            ]);

            return;
        }
    }

    public function cancelByContactPhone(DriverTripRequest $request, string $contactPhone): void
    {
        $stored = preg_replace('/\D+/', '', (string) $request->contact_phone);
        $given = preg_replace('/\D+/', '', $contactPhone);
        if ($stored === '' || $stored !== $given) {
            throw new InvalidArgumentException('Không có quyền hủy yêu cầu này.');
        }

        if (! $request->isPending()) {
            throw new InvalidArgumentException('Yêu cầu không còn hiệu lực.');
        }

        $request->update([
            'status'       => 'cancelled',
            'responded_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    public function serializeDriver(DriverProfile $profile): array
    {
        $meta = $this->availabilityMeta($profile->availability_status ?? 'off_duty');

        return [
            'code'                => $profile->driver_code,
            'name'                => $profile->user->name,
            'license_class'       => $profile->license_class,
            'experience_years'    => $profile->experience_years,
            'operator'            => $profile->operator?->name,
            'availability'        => $profile->availability_status,
            'availability_label'  => $meta['label'],
            'availability_color'  => $meta['color'],
            'availability_icon'   => $meta['icon'],
            'suggested'           => $meta['suggested'],
            'vehicle_type'        => $profile->vehicle_type,
            'vehicle_plate'       => $profile->vehicle_license_plate,
            'vehicle_seats'       => (int) ($profile->vehicle_seats ?? 0),
            'vehicle_label'       => self::vehicleLabel($profile),
        ];
    }

    public static function vehicleLabel(DriverProfile $profile): string
    {
        return VehicleDisplay::compactLabel(
            $profile->vehicle_type ? (string) $profile->vehicle_type : null,
            $profile->vehicle_license_plate,
            $profile->vehicle_seats ? (int) $profile->vehicle_seats : null,
        );
    }

    /** @return array<string, mixed>|null */
    public function serializeRequest(?DriverTripRequest $request): ?array
    {
        if (! $request) {
            return null;
        }

        return [
            'id'           => $request->id,
            'schedule_id'  => $request->schedule_id,
            'status'       => $request->status,
            'status_label' => $request->statusLabel(),
            'driver_name'  => $request->driver?->name,
            'driver_code'  => $request->driverProfile?->driver_code,
            'expires_at'   => $request->expires_at?->toIso8601String(),
            'is_pending'   => $request->isPending(),
        ];
    }
}
