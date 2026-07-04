<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\Schedule;
use App\Support\VehicleDisplay;

/**
 * Thông tin tài xế + khoảng cách/ETA cho khách theo dõi — đồng bộ với dashboard tài xế.
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

        $stage = $schedule->resolvedDriverStage();
        $distanceKm = $this->resolveLiveDistanceKm($booking, $profile);
        $distanceLabel = $distanceKm !== null
            ? DriverProximityService::formatDistanceLabel($distanceKm)
            : null;

        $vehicleName = trim(implode(' ', array_filter([
            $profile->vehicle_brand,
            $profile->vehicle_model,
        ])));

        $etaLabel = null;
        $proximityHint = null;

        if (in_array($stage, [Schedule::DRIVER_STAGE_ASSIGNED, Schedule::DRIVER_STAGE_AT_PICKUP], true)) {
            if ($stage === Schedule::DRIVER_STAGE_AT_PICKUP || ($distanceKm !== null && $distanceKm < 0.3)) {
                $proximityHint = 'Tài xế đã đến điểm đón';
            } else {
                $etaLabel = $this->latePickup->pickupEtaLabel($schedule, $booking);
                if ($distanceLabel && $etaLabel) {
                    $proximityHint = 'Còn ~' . $distanceLabel . ' · dự kiến ' . $etaLabel;
                } elseif ($distanceLabel) {
                    $proximityHint = 'Còn ~' . $distanceLabel;
                } elseif ($etaLabel) {
                    $proximityHint = 'Dự kiến ' . $etaLabel;
                }
            }
        } elseif (in_array($stage, [Schedule::DRIVER_STAGE_PICKED_UP, Schedule::DRIVER_STAGE_RUNNING], true)) {
            $proximityHint = 'Tài xế đang chở bạn trên chuyến';
        }

        return [
            'name'               => $profile->user->name ?? $schedule->driver_name,
            'code'               => $profile->driver_code,
            'vehicle_type'       => $profile->vehicle_type,
            'vehicle_type_label' => VehicleDisplay::typeLabel($profile->vehicle_type),
            'vehicle_plate'      => $profile->vehicle_license_plate,
            'vehicle_name'       => $vehicleName !== '' ? $vehicleName : null,
            'vehicle_label'      => DriverTripRequestService::vehicleLabel($profile),
            'stage'              => $stage,
            'stage_label'        => $schedule->driverStageLabel(),
            'distance_km'        => $distanceKm,
            'distance_label'     => $distanceLabel,
            'eta_label'          => $etaLabel,
            'proximity_hint'     => $proximityHint,
        ];
    }

    private function resolveLiveDistanceKm(Booking $booking, DriverProfile $profile): ?float
    {
        $live = $this->proximity->snapshotPickupDistance($booking, $profile);

        if ($live !== null) {
            if ((float) ($booking->driver_pickup_distance_km ?? 0) !== $live) {
                $booking->update(['driver_pickup_distance_km' => $live]);
            }

            return $live;
        }

        return $booking->driver_pickup_distance_km !== null
            ? (float) $booking->driver_pickup_distance_km
            : null;
    }
}
