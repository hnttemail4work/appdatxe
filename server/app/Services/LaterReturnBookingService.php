<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\ScheduleTemplate;
use App\Support\DeparturePlan;
use App\Support\ServiceDate;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Tạo chuyến đón khách về từ đơn hẹn sau đã hoàn thành. */
class LaterReturnBookingService
{
    public function __construct(
        private readonly BookingWorkflowService $workflow,
        private readonly DriverTripRequestService $tripRequests,
    ) {
    }

    public function dispatchReturnPickup(Booking $source): Booking
    {
        $source->loadMissing(['schedule.template', 'schedule.route', 'schedule.vehicle']);

        if (! $source->isLaterDeparturePlan()) {
            throw new InvalidArgumentException('Chuyến này không phải đơn hẹn sau.');
        }

        if ($source->trip_status !== 'completed') {
            throw new InvalidArgumentException('Chuyến chưa hoàn thành — chưa thể tạo chuyến đón về.');
        }

        if ($source->later_pickup_dispatched_at !== null) {
            throw new InvalidArgumentException('Đã tạo chuyến đón khách về cho đơn này.');
        }

        if (! $source->showsLaterPickupReminder()) {
            throw new InvalidArgumentException('Chưa đến ngày đón khách về.');
        }

        $template = $this->resolveTemplate($source);
        if (! $template) {
            throw new InvalidArgumentException('Không tìm thấy xe/tài xế gốc để tạo chuyến về.');
        }

        $serviceDate = ServiceDate::today()->toDateString();
        $driverUserId = $source->resolveAssignedDriverId($source->schedule);

        return DB::transaction(function () use ($source, $template, $serviceDate, $driverUserId): Booking {
            $returnBooking = $this->workflow->createBookingFromTemplate(
                $template,
                (string) $source->contact_phone,
                (string) $source->passenger_name,
                $serviceDate,
                null,
                $source->dropoff_address,
                $source->dropoff_detail,
                $source->pickup_address,
                $source->pickup_detail,
                $source->notes,
                $source->applied_referral_code_id,
                (string) ($source->passenger_gender ?? 'male'),
                $source->passenger_age,
                $source->dropoff_lat,
                $source->dropoff_lng,
                $source->pickup_lat,
                $source->pickup_lng,
                DeparturePlan::TODAY,
            );

            $returnBooking = $returnBooking->fresh(['schedule.route', 'schedule.vehicle']);

            if ($driverUserId) {
                $profile = DriverProfile::query()
                    ->where('user_id', $driverUserId)
                    ->operational()
                    ->first();

                if ($profile?->driver_code) {
                    try {
                        $this->tripRequests->requestDriver(
                            $returnBooking->schedule->fresh(['route']),
                            $profile->driver_code,
                            (string) $returnBooking->contact_phone,
                        );
                    } catch (InvalidArgumentException) {
                        // Admin có thể gán tài xế thủ công sau.
                    }
                }
            }

            $source->update([
                'later_return_booking_id'   => $returnBooking->id,
                'later_pickup_dispatched_at' => now(),
            ]);

            return $returnBooking->fresh(['schedule.route', 'schedule.vehicle']);
        });
    }

    private function resolveTemplate(Booking $source): ?ScheduleTemplate
    {
        $schedule = $source->schedule;
        if (! $schedule) {
            return null;
        }

        if ($schedule->template) {
            return $schedule->template;
        }

        if ($schedule->template_id) {
            return ScheduleTemplate::query()->find($schedule->template_id);
        }

        return null;
    }
}
