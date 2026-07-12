<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\Schedule;
use App\Support\VehicleCapacityOptions;
use App\Support\VehicleDisplay;

/**
 * Thông tin tài xế + khoảng cách/ETA cho khách theo dõi.
 * Khoảng cách: sau khi TX nhận chuyến. ETA: sau khi TX bấm «Xác nhận».
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
        $booking->loadMissing('schedule.route', 'schedule.template', 'schedule.vehicle');
        $schedule = $booking->schedule;
        $acceptance = $booking->driverAcceptanceState();
        $profile = $this->resolveGuestDriverProfile($booking);

        if (! $schedule || ! $profile) {
            return null;
        }

        if ($acceptance === 'none' && ! $booking->catalogChosenDriverProfile()) {
            return null;
        }

        $stage = $schedule->driver_id
            ? $schedule->resolvedDriverStage()
            : Schedule::DRIVER_STAGE_ASSIGNED;
        $hasLiveLocation = $profile->hasFreshLocation();
        $movementConfirmed = $acceptance === 'accepted' && $schedule->driverHasConfirmedMovement();

        $distanceKm = null;
        if ($acceptance === 'accepted' && $schedule->driver_id) {
            $distanceKm = $this->latePickup->pickupDistanceKmForProfile($profile, $booking);
            if ($distanceKm !== null && $hasLiveLocation) {
                $distanceKm = $this->resolveLiveDistanceKm($booking, $profile) ?? $distanceKm;
            }
        }

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

        if ($acceptance === 'pending') {
            $statusLine = 'Chờ tài xế';
        } elseif ($acceptance === 'none' && $booking->catalogChosenDriverProfile()) {
            $statusLine = 'Chờ tài xế';
        } elseif ($stage === Schedule::DRIVER_STAGE_AT_PICKUP) {
            $statusLine = 'Tài xế đã đến điểm đón';
        } elseif (in_array($stage, [Schedule::DRIVER_STAGE_PICKED_UP, Schedule::DRIVER_STAGE_RUNNING], true)) {
            $statusLine = 'Tài xế đang chở bạn trên chuyến';
        } elseif ($stage === Schedule::DRIVER_STAGE_ASSIGNED) {
            $statusLine = $movementConfirmed ? 'Tài xế đang đi đón' : 'Đã nhận';

            if ($distanceLabel) {
                $distanceLine = 'Tài xế cách bạn ' . $distanceLabel;
            }

            if ($movementConfirmed && $hasLiveLocation && $distanceKm !== null) {
                $etaLabel = $this->latePickup->pickupEtaLabel($schedule, $booking);
                if ($etaLabel) {
                    $etaLine = 'Dự kiến ' . $etaLabel;
                }
            }
        }

        $vehicleSeats = VehicleDisplay::capacityFromDriverProfile($profile);
        if ($vehicleSeats <= 0 && $schedule->vehicle) {
            $vehicleSeats = max(0, (int) ($schedule->vehicle->capacity ?? 0));
        }

        $proximityHint = implode("\n", array_filter([$statusLine, $distanceLine, $etaLine])) ?: null;

        return [
            'name'                 => $profile->user->name ?? $schedule->driver_name,
            'code'                 => $profile->driver_code,
            'vehicle_type'         => $profile->vehicle_type,
            'vehicle_type_label'   => VehicleDisplay::typeLabel($profile->vehicle_type),
            'vehicle_plate'        => $profile->vehicle_license_plate,
            'vehicle_seats'        => $vehicleSeats,
            'vehicle_seats_label'  => $vehicleSeats > 0 ? VehicleCapacityOptions::label($vehicleSeats) : null,
            'vehicle_name'         => $vehicleName !== '' ? $vehicleName : null,
            'vehicle_photo_url'    => $vehiclePhotoUrl,
            'vehicle_label'        => DriverTripRequestService::vehicleLabel($profile),
            'stage'                => $stage,
            'stage_label'          => $schedule->driverStageLabel(),
            'location_shared'      => $hasLiveLocation,
            'movement_confirmed'   => $movementConfirmed,
            'distance_km'          => $distanceKm,
            'distance_label'       => $distanceLabel,
            'eta_label'            => $etaLabel,
            'status_line'          => $statusLine,
            'distance_line'        => $distanceLine,
            'eta_line'             => $etaLine,
            'proximity_hint'       => $proximityHint,
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

    private function resolveGuestDriverProfile(Booking $booking): ?DriverProfile
    {
        $profile = $booking->guestDriverProfile();
        if ($profile) {
            return $profile;
        }

        if ($booking->driverAcceptanceState() === 'pending') {
            $pending = $booking->eligiblePendingDriverTripRequest();
            if ($pending) {
                return DriverProfile::query()
                    ->where('user_id', $pending->driver_id)
                    ->with('user')
                    ->first();
            }
        }

        return $booking->catalogChosenDriverProfile();
    }
}
