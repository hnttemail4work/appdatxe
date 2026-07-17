<?php

namespace App\Services;

use App\Models\DriverProfile;
use App\Models\ScheduleTemplate;
use App\Support\VehicleCapacityOptions;
use App\Support\VehicleDisplay;
use Illuminate\Support\Collection;

class TripListingService
{
    public function __construct(
        private readonly DriverCatalogService $driverCatalog,
        private readonly DriverAvailabilityService $driverAvailability,
    ) {
    }

    public function activeTemplateForDriver(DriverProfile $profile): ?ScheduleTemplate
    {
        return ScheduleTemplate::query()
            ->where('driver_id', $profile->user_id)
            ->where('status', 'active')
            ->with(['vehicle', 'driver'])
            ->first();
    }

    /**
     * Danh sách loại xe khách có thể đặt — nhóm theo số chỗ + loại, KHÔNG gắn với một tài xế cụ thể.
     * Khách chọn loại xe, hệ thống tự ghép tài xế gần nhất khi tạo cuốc (auto-assign).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function vehicleTypeCatalog(): Collection
    {
        $drivers = DriverProfile::query()
            ->operational()
            ->where('approval_status', 'approved')
            ->whereNotNull('vehicle_license_plate')
            ->where('vehicle_license_plate', '!=', '')
            ->whereNotNull('vehicle_type')
            ->where('vehicle_seats', '>', 0)
            ->with('user')
            ->orderBy('id')
            ->get();

        $this->driverAvailability->syncCatalogDriverStates($drivers);
        $drivers = $drivers->map(fn (DriverProfile $profile) => $profile->fresh(['user']))->values();
        $drivers->each(fn (DriverProfile $profile) => $this->driverCatalog->syncCatalogForDriver($profile));

        return $drivers
            ->filter(fn (DriverProfile $profile) => $this->activeTemplateForDriver($profile) !== null)
            ->groupBy(fn (DriverProfile $profile) => ((int) $profile->vehicle_seats) . '|' . $profile->vehicle_type)
            ->map(function (Collection $group) {
                $bookableProfiles = $group->filter(fn (DriverProfile $p) => $this->driverAvailability->isBookableNow($p));
                /** @var DriverProfile $sample */
                $sample = $bookableProfiles->first() ?? $group->first();
                $capacity = (int) $sample->vehicle_seats;
                $bookableCount = $bookableProfiles->count();

                return [
                    'capacity'             => $capacity,
                    'capacity_label'       => VehicleCapacityOptions::label($capacity),
                    'vehicle_type'         => $sample->vehicle_type,
                    'type_label'           => VehicleDisplay::typeLabel($sample->vehicle_type),
                    'sample_photo'         => $sample->firstVehiclePhotoUrl(),
                    'available_count'      => $group->count(),
                    'bookable_now_count'   => $bookableCount,
                    'booking_action_label' => $bookableCount > 0 ? 'Đặt ngay' : 'Đặt sau',
                    'booking_action_tone'  => $bookableCount > 0 ? 'now' : 'later',
                ];
            })
            ->sortBy(fn (array $row) => ($row['bookable_now_count'] > 0 ? '0' : '1')
                . '-' . str_pad((string) $row['capacity'], 3, '0', STR_PAD_LEFT)
                . '-' . (string) ($row['vehicle_type'] ?? ''))
            ->values();
    }

    /**
     * Khách chọn loại xe (không chỉ định tài xế) — tìm 1 template khớp số chỗ/loại để lấy giá + tạo chuyến.
     * Tài xế thực tế nhận cuốc sẽ do auto-assign (gần nhất) quyết định, không nhất thiết là chủ template này.
     */
    public function resolveTemplateForCapacity(int $capacity, ?string $vehicleType = null): ?ScheduleTemplate
    {
        $candidates = ScheduleTemplate::query()
            ->where('status', 'active')
            ->whereNotNull('driver_id')
            ->whereHas('vehicle', function ($query) use ($capacity, $vehicleType): void {
                $query->where('capacity', $capacity);
                if ($vehicleType) {
                    $query->where('type', $vehicleType);
                }
            })
            ->with(['route', 'vehicle', 'driver'])
            ->get();

        // Không fallback âm thầm sang loại xe khác — nếu khách đã chọn loại xe cụ thể
        // và loại đó hết hàng, phải báo lỗi rõ ràng để khách chọn lại, tránh bị đổi
        // sản phẩm (và trải nghiệm) mà không biết.
        if ($candidates->isEmpty()) {
            return null;
        }

        $bookableNow = $candidates->first(function (ScheduleTemplate $template) {
            $profile = DriverProfile::query()->where('user_id', $template->driver_id)->first();

            return $profile && $this->driverAvailability->isBookableNow($profile);
        });

        return $bookableNow ?? $candidates->first();
    }
}
