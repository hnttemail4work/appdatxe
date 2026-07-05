<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\Schedule;
use App\Support\VehicleDisplay;

/**
 * Thông tin tài xế + khoảng cách/ETA cho khách theo dõi — chỉ sau khi tài xế chia sẻ vị trí.
 */
class GuestBookingDriverStatusService
{
    public function __construct(
        private readonly DriverProximityService $proximity,
        private readonly DriverLatePickupService $latePickup,
    ) {
    }

    /** @return array<string, mixed>|null */
    public function build(Booking $booking): ?array
    {
        $booking->loadMissing('schedule.route');
        $schedule = $booking->schedule;

        if (! $schedule || ! $booking->hasDriverAccepted() || ! $schedule->driver_id) {
            return null;
        }

        $profile = DriverProfile::query()
            ->where('user_id', $schedule->driver_id)
            ->with('user')
            ->first();

        if (! $profile) {
            return null;
        }

        $schedule->loadMissing('vehicle');

        $stage = $schedule->resolvedDriverStage();
        $hasLiveLocation = $profile->hasFreshLocation();
        $distanceKm = $hasLiveLocation
            ? $this->resolveLiveDistanceKm($booking, $profile)
            : null;
        $distanceLabel = $distanceKm !== null
            ? DriverProximityService::formatDistanceLabel($distanceKm)
            : null;

        $vehicleName = trim(implode(' ', array_filter([
            $profile->vehicle_brand,
            $profile->vehicle_model,
        ])));
        $vehiclePhotoUrl = $profile->firstVehiclePhotoUrl()
            ?: VehicleDisplay::photoFromVehicle($schedule->vehicle);

        $etaLabel = null;
        $statusLine = null;
        $distanceLine = null;
        $etaLine = null;

        if ($stage === Schedule::DRIVER_STAGE_AT_PICKUP) {
            $statusLine = 'Tài xế đã đến điểm đón';
        } elseif (in_array($stage, [Schedule::DRIVER_STAGE_PICKED_UP, Schedule::DRIVER_STAGE_RUNNING], true)) {
            $statusLine = 'Tài xế đang chở bạn trên chuyến';
        } elseif ($stage === Schedule::DRIVER_STAGE_ASSIGNED) {
            $statusLine = 'Tài xế đã nhận chuyến';

            if ($hasLiveLocation && $distanceKm !== null) {
                $etaLabel = $this->latePickup->pickupEtaLabel($schedule, $booking);

                if ($distanceLabel) {
                    $distanceLine = 'Tài xế cách bạn ' . $distanceLabel;
                }
                if ($etaLabel) {
                    $etaLine = 'Dự kiến ' . $etaLabel;
                }
            }
        }

        $proximityHint = implode("\n", array_filter([$statusLine, $distanceLine, $etaLine])) ?: null;

        return [
            'name'               => $profile->user->name ?? $schedule->driver_name,
            'code'               => $profile->driver_code,
            'vehicle_type'       => $profile->vehicle_type,
            'vehicle_type_label' => VehicleDisplay::typeLabel($profile->vehicle_type),
            'vehicle_plate'      => $profile->vehicle_license_plate,
            'vehicle_name'       => $vehicleName !== '' ? $vehicleName : null,
            'vehicle_photo_url'  => $vehiclePhotoUrl,
            'vehicle_label'      => DriverTripRequestService::vehicleLabel($profile),
            'stage'              => $stage,
            'stage_label'        => $schedule->driverStageLabel(),
            'location_shared'    => $hasLiveLocation,
            'distance_km'        => $distanceKm,
            'distance_label'     => $distanceLabel,
            'eta_label'          => $etaLabel,
            'status_line'        => $statusLine,
            'distance_line'      => $distanceLine,
            'eta_line'           => $etaLine,
            'proximity_hint'     => $proximityHint,
        ];
    }

    private function resolveLiveDistanceKm(Booking $booking, DriverProfile $profile): ?float
    {
        $live = $this->proximity->snapshotPickupDistance($booking, $profile);

        if ($live === null) {
            return null;
        }

        if ((float) ($booking->driver_pickup_distance_km ?? 0) !== $live) {
            $booking->update(['driver_pickup_distance_km' => $live]);
        }

        return $live;
    }
}
