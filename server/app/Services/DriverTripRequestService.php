<?php

namespace App\Services;

use App\Models\DriverProfile;
use App\Models\DriverTripRequest;
use App\Models\Schedule;
use App\Models\ScheduleTemplate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DriverTripRequestService
{
    public const REQUEST_TTL_MINUTES = 5;

    public function expireStale(): void
    {
        DriverTripRequest::query()
            ->where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update([
                'status'       => 'expired',
                'responded_at' => now(),
            ]);
    }

    /** @return Collection<int, DriverProfile> */
    public function suggestDrivers(?Schedule $schedule = null): Collection
    {
        $query = DriverProfile::query()
            ->where('status', 'active')
            ->with(['user', 'operator'])
            ->orderByRaw("FIELD(availability_status, 'available', 'off_duty', 'on_trip')")
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

    public function requestDriver(Schedule $schedule, int $customerId, string $driverCode): DriverTripRequest
    {
        $this->expireStale();

        if ($schedule->driver_id) {
            throw new InvalidArgumentException('Chuyến này đã có tài xế.');
        }

        if (! in_array($schedule->status, ['scheduled', 'running'], true)) {
            throw new InvalidArgumentException('Chuyến không còn mở để mời tài xế.');
        }

        $profile = DriverProfile::query()
            ->where('driver_code', strtoupper(trim($driverCode)))
            ->where('status', 'active')
            ->with('user')
            ->first();

        if (! $profile) {
            throw new InvalidArgumentException('Không tìm thấy tài xế với mã này.');
        }

        if ($profile->availability_status === 'on_trip') {
            throw new InvalidArgumentException('Tài xế đang chạy chuyến khác. Vui lòng chọn tài xế khác.');
        }

        $existing = DriverTripRequest::query()
            ->where('schedule_id', $schedule->id)
            ->where('customer_id', $customerId)
            ->where('status', 'pending')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();

        if ($existing) {
            throw new InvalidArgumentException('Bạn đang chờ tài xế phản hồi cho chuyến này.');
        }

        return DriverTripRequest::query()->create([
            'schedule_id' => $schedule->id,
            'customer_id' => $customerId,
            'driver_id'   => $profile->user_id,
            'status'      => 'pending',
            'expires_at'  => now()->addMinutes(self::REQUEST_TTL_MINUTES),
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

            if ($schedule->template_id) {
                ScheduleTemplate::query()
                    ->whereKey($schedule->template_id)
                    ->update([
                        'driver_id'   => $request->driver_id,
                        'driver_name' => $driverName,
                    ]);
            }

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

            $driver->update([
                'availability_status' => $schedule->status === 'running' ? 'on_trip' : 'available',
            ]);
        });
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
    }

    public function cancelByCustomer(DriverTripRequest $request, int $customerId): void
    {
        if ($request->customer_id !== $customerId) {
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
        ];
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
