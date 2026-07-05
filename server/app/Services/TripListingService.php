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
    ) {
    }

    /** @return Collection<int, DriverProfile> */
    public function listBookableOffers(): Collection
    {
        $drivers = DriverProfile::query()
            ->operational()
            ->where('approval_status', 'approved')
            ->whereNotNull('vehicle_license_plate')
            ->where('vehicle_license_plate', '!=', '')
            ->whereNotNull('vehicle_type')
            ->where('vehicle_seats', '>', 0)
            ->with('user')
            ->orderBy('driver_code')
            ->orderBy('id')
            ->get();

        return $drivers
            ->each(fn (DriverProfile $profile) => $this->driverCatalog->syncCatalogForDriver($profile))
            ->filter(fn (DriverProfile $profile) => $this->activeTemplateForDriver($profile) !== null)
            ->sortBy(fn (DriverProfile $profile) => $this->bookingActionSortKey($profile))
            ->values();
    }

    private function bookingActionSortKey(DriverProfile $profile): string
    {
        $status = $profile->availability_status ?? 'off_duty';

        return ($status === 'available' ? '0' : '1') . '-' . ($profile->driver_code ?? $profile->id);
    }

    public function bookingActionLabel(DriverProfile $profile): string
    {
        return match ($profile->availability_status ?? 'off_duty') {
            'available' => 'Đặt ngay',
            default     => 'Đặt sau',
        };
    }

    public function bookingActionTone(DriverProfile $profile): string
    {
        return ($profile->availability_status ?? 'off_duty') === 'available' ? 'success' : 'pending';
    }

    public function activeTemplateForDriver(DriverProfile $profile): ?ScheduleTemplate
    {
        return ScheduleTemplate::query()
            ->where('driver_id', $profile->user_id)
            ->where('status', 'active')
            ->with(['vehicle', 'driver'])
            ->first();
    }

    public function activeTemplateForDriverProfileId(int $driverProfileId): ?ScheduleTemplate
    {
        $profile = DriverProfile::query()->with('user')->find($driverProfileId);

        return $profile ? $this->activeTemplateForDriver($profile) : null;
    }

    public function activeTemplateForVehicle(int $vehicleId): ?ScheduleTemplate
    {
        return ScheduleTemplate::query()
            ->where('vehicle_id', $vehicleId)
            ->where('status', 'active')
            ->whereNotNull('driver_id')
            ->with(['vehicle', 'driver'])
            ->orderBy('id')
            ->first();
    }

    public function resolveTemplate(
        ?int $driverProfileId = null,
        ?int $vehicleId = null,
        ?int $templateId = null,
    ): ?ScheduleTemplate {
        if ($driverProfileId) {
            $template = $this->activeTemplateForDriverProfileId($driverProfileId);
            if ($template) {
                return $template;
            }
        }

        if ($vehicleId) {
            $template = $this->activeTemplateForVehicle($vehicleId);
            if ($template) {
                return $template;
            }
        }

        if ($templateId) {
            return ScheduleTemplate::query()
                ->where('status', 'active')
                ->whereNotNull('driver_id')
                ->with(['route', 'vehicle', 'driver'])
                ->find($templateId);
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function serializeOffer(DriverProfile $profile): array
    {
        $profile->loadMissing('user');
        $template = $this->activeTemplateForDriver($profile);
        $vehicle = $template?->vehicle;
        $capacity = (int) ($profile->vehicle_seats ?? $vehicle?->capacity ?? 0);
        $driverName = $profile->user->name ?? '—';
        $vehiclePhoto = $profile->firstVehiclePhotoUrl() ?: VehicleDisplay::photoFromVehicle($vehicle);
        $typeLabel = VehicleDisplay::typeLabel($profile->vehicle_type ?? $vehicle?->type);
        $capacityLabel = $capacity > 0 ? VehicleCapacityOptions::label($capacity) : '—';

        return [
            'driver_profile_id'           => $profile->id,
            'driver_user_id'              => $profile->user_id,
            'vehicle_id'                  => $vehicle?->id,
            'template_id'                 => $template?->id,
            'license_plate'               => $profile->vehicle_license_plate,
            'capacity'                    => $capacity,
            'capacity_label'              => $capacityLabel,
            'vehicle_type'                => $profile->vehicle_type ?? $vehicle?->type ?? 'sedan',
            'type_label'                  => $typeLabel,
            'vehicle_photo'               => $vehiclePhoto,
            'offer_label'                 => collect([$driverName, $profile->vehicle_license_plate, $typeLabel, $capacityLabel])
                ->filter(fn ($part) => filled($part) && $part !== '—')
                ->implode(' - '),
            'driver_name'                 => $driverName,
            'driver_code'                 => $profile->driver_code,
            'driver_photo_url'            => $profile->photoUrl('photo_portrait'),
            'driver_initial'              => mb_substr($driverName, 0, 1),
            'driver_availability'         => $profile->effectiveAvailabilityStatus(),
            'driver_availability_label'   => $profile->availabilityLabel(),
            'driver_availability_tone'    => match ($profile->effectiveAvailabilityStatus()) {
                'available' => 'success',
                'on_trip'   => 'warning',
                default     => 'neutral',
            },
            'booking_action_label'        => $this->bookingActionLabel($profile),
            'booking_action_tone'         => $this->bookingActionTone($profile),
        ];
    }
}
