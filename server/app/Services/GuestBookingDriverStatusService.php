<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\Schedule;
use App\Support\VehicleCapacityOptions;
use App\Support\VehicleDisplay;
use Illuminate\Support\Facades\Cache;

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

        // Chỉ hiện thông tin tài xế sau khi TX xác nhận nhận cuốc.
        if ($acceptance !== 'accepted' || ! $schedule?->driver_id) {
            return null;
        }

        $profile = $this->resolveGuestDriverProfile($booking);
        if (! $profile) {
            return null;
        }

        $stage = $schedule->resolvedDriverStage();
        $hasLiveLocation = $profile->hasFreshLocation();
        $movementConfirmed = $schedule->driverHasConfirmedMovement();

        $distanceKm = $this->latePickup->pickupDistanceKmForProfile($profile, $booking);
        if ($distanceKm !== null && $hasLiveLocation) {
            $distanceKm = $this->resolveLiveDistanceKm($booking, $profile) ?? $distanceKm;
        }

        $distanceLabel = $distanceKm !== null
            ? DriverProximityService::formatDistanceLabel($distanceKm)
            : null;

        $vehicleName = trim(implode(' ', array_filter([
            $profile->vehicle_brand,
            $profile->vehicle_model,
        ])));
        $vehiclePhotoUrls = $profile->vehiclePhotoUrls();
        $fallbackVehiclePhoto = VehicleDisplay::photoFromVehicle($schedule->vehicle);
        if ($vehiclePhotoUrls === [] && $fallbackVehiclePhoto) {
            $vehiclePhotoUrls = [$fallbackVehiclePhoto];
        }
        $vehiclePhotoUrl = $vehiclePhotoUrls[0] ?? null;

        $etaLabel = null;
        $etaDurationLabel = null;
        $statusLine = null;
        $distanceLine = null;
        $etaLine = null;
        $proximitySummary = null;

        if ($stage === Schedule::DRIVER_STAGE_AT_PICKUP) {
            $statusLine = 'Tài xế đã đến điểm đón';
            $proximitySummary = $statusLine;
        } elseif (in_array($stage, [Schedule::DRIVER_STAGE_PICKED_UP, Schedule::DRIVER_STAGE_RUNNING], true)) {
            $statusLine = 'Đang trong chuyến';
            $proximitySummary = $statusLine;
        } elseif ($stage === Schedule::DRIVER_STAGE_ASSIGNED) {
            $statusLine = $movementConfirmed ? 'Tài xế đang đi đón' : 'Đã nhận chuyến';

            if ($distanceLabel) {
                $distanceLine = 'Tài xế cách bạn ' . $distanceLabel;
            }

            if ($movementConfirmed && $hasLiveLocation && $distanceKm !== null) {
                $etaMinutesRaw = $this->latePickup->pickupEtaMinutes($schedule, $booking);
                if ($etaMinutesRaw !== null && $etaMinutesRaw > 0) {
                    $etaDurationLabel = $this->latePickup->formatArrivalDurationLabel($etaMinutesRaw);
                    $etaLabel = $etaDurationLabel;
                    $etaLine = 'Dự kiến đến trong ' . $etaDurationLabel;
                }
            }

            if ($distanceLabel && $etaDurationLabel) {
                $proximitySummary = 'Tài xế cách bạn ' . $distanceLabel . ' — dự kiến đến trong ' . $etaDurationLabel;
            } elseif ($distanceLabel) {
                $proximitySummary = $distanceLine;
            } else {
                $proximitySummary = $statusLine;
            }
        } else {
            $proximitySummary = $statusLine;
        }

        $vehicleSeats = VehicleDisplay::capacityFromDriverProfile($profile);
        if ($vehicleSeats <= 0 && $schedule->vehicle) {
            $vehicleSeats = max(0, (int) ($schedule->vehicle->capacity ?? 0));
        }

        $proximityHint = implode("\n", array_filter([$statusLine, $distanceLine, $etaLine])) ?: null;

        $lat = $hasLiveLocation ? $profile->last_lat : null;
        $lng = $hasLiveLocation ? $profile->last_lng : null;
        $heading = $hasLiveLocation && $profile->last_heading !== null
            ? (float) $profile->last_heading
            : null;

        $etaMinutes = ($stage === Schedule::DRIVER_STAGE_ASSIGNED && $movementConfirmed && $hasLiveLocation && $distanceKm !== null)
            ? $this->latePickup->pickupEtaMinutes($schedule, $booking)
            : null;

        [$rating, $ratingLabel] = $this->cachedRating($profile);

        $phoneRaw = trim((string) ($profile->user?->phone ?? ''));
        $phoneTel = $phoneRaw !== '' ? (string) preg_replace('/[^\d+]/', '', $phoneRaw) : '';

        return [
            'name'                 => $profile->user->name ?? $schedule->driver_name,
            'phone'                => $phoneRaw !== '' ? $phoneRaw : null,
            'phone_tel'            => $phoneTel !== '' ? $phoneTel : null,
            'code'                 => $profile->driver_code,
            'vehicle_type'         => $profile->vehicle_type,
            'vehicle_type_label'   => VehicleDisplay::typeLabel($profile->vehicle_type),
            'vehicle_plate'        => $profile->vehicle_license_plate,
            'vehicle_seats'        => $vehicleSeats,
            'vehicle_seats_label'  => $vehicleSeats > 0 ? VehicleCapacityOptions::label($vehicleSeats) : null,
            'vehicle_name'         => $vehicleName !== '' ? $vehicleName : null,
            'vehicle_color'        => $profile->vehicle_color,
            'vehicle_photo_url'    => $vehiclePhotoUrl,
            'vehicle_photo_urls'   => array_values($vehiclePhotoUrls),
            'vehicle_label'        => DriverTripRequestService::vehicleLabel($profile),
            'stage'                => $stage,
            'stage_label'          => $schedule->driverStageLabel(),
            'stage_step'           => $this->stageStep($stage),
            'location_shared'      => $hasLiveLocation,
            'movement_confirmed'   => $movementConfirmed,
            'lat'                  => $lat !== null ? (float) $lat : null,
            'lng'                  => $lng !== null ? (float) $lng : null,
            'heading'              => $heading,
            'distance_km'          => $distanceKm,
            'distance_label'       => $distanceLabel,
            'eta_label'            => $etaLabel,
            'eta_duration_label'   => $etaDurationLabel,
            'eta_minutes'          => $etaMinutes,
            'rating'               => $rating,
            'rating_label'         => $ratingLabel,
            'status_line'          => $statusLine,
            'distance_line'        => $distanceLine,
            'eta_line'             => $etaLine,
            'proximity_hint'       => $proximityHint,
            'proximity_summary'    => $proximitySummary,
        ];
    }

    /** 1=đã nhận, 2=đến điểm đón, 3=đang chạy — dùng cho stepper phía khách. */
    private function stageStep(string $stage): int
    {
        return match ($stage) {
            Schedule::DRIVER_STAGE_AT_PICKUP => 2,
            Schedule::DRIVER_STAGE_PICKED_UP, Schedule::DRIVER_STAGE_RUNNING, Schedule::DRIVER_STAGE_COMPLETED => 3,
            default => 1,
        };
    }

    /** Rating tài xế đổi chậm — cache ngắn để tránh query TripReview mỗi lần khách poll. */
    private function cachedRating(DriverProfile $profile): array
    {
        $key = 'guest_driver_rating_' . $profile->user_id;

        return Cache::remember($key, 60, function () use ($profile) {
            return [$profile->starRating(), $profile->starRatingLabel()];
        });
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
