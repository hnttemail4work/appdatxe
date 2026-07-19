<?php

namespace App\Services;

use App\Models\DriverProfile;
use App\Models\ScheduleTemplate;
use App\Support\DriverVehicleOptions;
use App\Support\VehicleCapacityOptions;
use App\Support\VehicleDisplay;
use App\Support\VehicleTypeIcons;
use App\Support\VehicleTypePricing;
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
     * Catalog loại xe cố định (icon + số chỗ) — không gắn ảnh/tài xế thật.
     * Giá tính theo tuyến ở client; tìm TX gần sau khi đặt.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function vehicleTypeCatalog(): Collection
    {
        $rows = [];

        foreach (VehicleTypePricing::priceableKeys() as $type) {
            $capacity = (int) (DriverVehicleOptions::seatsFor($type) ?: 0);
            if ($capacity < 1) {
                continue;
            }

            $typeLabel = VehicleDisplay::typeLabel($type);
            $capacityLabel = VehicleCapacityOptions::label($capacity);
            $offerLabel = collect([$typeLabel, $capacityLabel])
                ->filter(fn ($p) => filled($p) && $p !== '—')
                ->implode(' · ');

            $rows[] = [
                'capacity'       => $capacity,
                'capacity_label' => $capacityLabel,
                'vehicle_type'   => $type,
                'type_label'     => $typeLabel,
                'icon_key'       => VehicleTypeIcons::keyFor($type),
                'hint'           => VehicleTypeIcons::hintFor($type),
                'sample_photo'   => null,
                'offer_label'    => $offerLabel,
            ];
        }

        return collect($rows)
            ->sortBy(fn (array $row) => str_pad((string) $row['capacity'], 3, '0', STR_PAD_LEFT)
                .'-'.(string) ($row['vehicle_type'] ?? ''))
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
