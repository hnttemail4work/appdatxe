<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingAudit;
use App\Models\PaymentTransaction;
use App\Models\Schedule;
use App\Models\ScheduleTemplate;
use App\Models\SeatReservation;
use App\Services\TripPricingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class BookingWorkflowService
{
    public function __construct(
        private readonly ScheduleLifecycleService $scheduleLifecycle,
        private readonly DriverWalletService $driverWallet,
        private readonly TripPricingService $pricing,
    ) {
    }

    public function createBookingFromTemplate(
        ScheduleTemplate $template,
        string $contactPhone,
        string $passengerName,
        array $seatNumbers,
        string $serviceDate,
        ?string $preferredTime,
        ?string $pickupAddress,
        ?string $pickupDetail,
        ?string $dropoffAddress,
        ?string $dropoffDetail,
        ?string $notes,
        string $tripType = 'one_way',
        string $bookingMode = 'shared',
    ): Booking {
        $template->loadMissing(['route', 'vehicle']);

        $schedule = $this->scheduleLifecycle->resolveScheduleForBooking(
            $template,
            $serviceDate,
            $preferredTime,
        );

        $pickup = $pickupAddress ?: $template->route->departure;
        $dropoff = $dropoffAddress ?: $template->route->destination;

        $existing = $this->findReusablePendingBooking($schedule, $contactPhone, $seatNumbers);
        if ($existing) {
            return $this->refreshPendingBooking(
                $existing,
                $passengerName,
                $pickup,
                $pickupDetail,
                $dropoff,
                $dropoffDetail,
                $notes,
                $tripType,
                $bookingMode,
            );
        }

        return $this->createBooking(
            $schedule,
            $contactPhone,
            $passengerName,
            $seatNumbers,
            $pickup,
            $pickupDetail,
            $dropoff,
            $dropoffDetail,
            $notes,
            $tripType,
            $bookingMode,
        );
    }

    public function createBooking(
        Schedule $schedule,
        string $contactPhone,
        string $passengerName,
        array $seatNumbers,
        ?string $pickupAddress = null,
        ?string $pickupDetail = null,
        ?string $dropoffAddress = null,
        ?string $dropoffDetail = null,
        ?string $notes = null,
        string $tripType = 'one_way',
        string $bookingMode = 'shared',
    ): Booking {
        return DB::transaction(function () use ($schedule, $contactPhone, $passengerName, $seatNumbers, $pickupAddress, $pickupDetail, $dropoffAddress, $dropoffDetail, $notes, $tripType, $bookingMode): Booking {
            $this->scheduleLifecycle->sync();

            $schedule = Schedule::query()
                ->with(['route', 'vehicle', 'seatReservations'])
                ->lockForUpdate()
                ->findOrFail($schedule->id);

            if (! in_array($schedule->status, ['scheduled'], true)) {
                throw new InvalidArgumentException('Chuyến không còn mở đặt vé (đang chạy hoặc đã kết thúc).');
            }

            if ($schedule->departure_time <= now()) {
                throw new InvalidArgumentException('Chuyến đã đến giờ khởi hành, không thể đặt thêm vé.');
            }

            $seatNumbers = array_map(fn ($seat): string => (string) $seat, $seatNumbers);

            $this->scheduleLifecycle->purgeInactiveSeatReservations($schedule, $seatNumbers);
            $this->assertSeatsAvailable($schedule, $seatNumbers);

            $totalPrice = $this->pricing->bookingTotal(
                $schedule,
                $tripType,
                $bookingMode,
                count($seatNumbers),
                $pickupAddress,
                $dropoffAddress,
            );
            $holdExpires = null;

            $booking = Booking::query()->create([
                'contact_phone'     => trim($contactPhone),
                'passenger_name'    => trim($passengerName),
                'schedule_id'       => $schedule->id,
                'seat_numbers'      => $seatNumbers,
                'trip_type'         => $tripType,
                'booking_mode'      => $bookingMode,
                'booking_reference' => 'BK-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(6)),
                'total_price'       => $totalPrice,
                'payment_status'    => 'unpaid',
                'trip_status'       => 'pending',
                'booking_status'    => 'pending',
                'pickup_address'    => $pickupAddress,
                'pickup_detail'     => $pickupDetail ? trim($pickupDetail) : null,
                'dropoff_address'   => $dropoffAddress,
                'dropoff_detail'    => $dropoffDetail ? trim($dropoffDetail) : null,
                'notes'             => $notes,
                'hold_expires_at'   => $holdExpires,
            ]);

            foreach ($seatNumbers as $seat) {
                SeatReservation::query()->create([
                    'schedule_id'       => $schedule->id,
                    'booking_id'        => $booking->id,
                    'seat_number'       => $seat,
                    'reservation_token' => (string) Str::uuid(),
                    'status'            => 'held',
                    'expires_at'        => $holdExpires,
                ]);
            }

            $this->syncScheduleAvailability($schedule);
            $this->audit($booking, null, 'booking_created', null, $booking->toArray());

            return $booking;
        });
    }

    public function cancelByPhone(Booking $booking, string $contactPhone): void
    {
        $this->assertContactPhone($booking, $contactPhone);

        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)) {
            throw new InvalidArgumentException('Vé này đã hủy rồi.');
        }

        if ($booking->trip_status === 'completed') {
            throw new InvalidArgumentException('Chuyến đã hoàn tất, không thể hủy.');
        }

        DB::transaction(function () use ($booking): void {
            $before = $booking->toArray();

            $booking->update([
                'booking_status' => 'cancelled',
                'trip_status'    => 'cancelled',
                'payment_status' => $booking->payment_status === 'paid' ? 'refunded' : 'unpaid',
                'cancelled_at'   => now(),
            ]);

            $booking->seatReservations()->delete();
            $booking->paymentTransactions()->where('status', 'pending')->update(['status' => 'failed']);
            $this->syncScheduleAvailability($booking->schedule()->with(['vehicle', 'seatReservations'])->first());
            $this->audit($booking, null, 'booking_cancelled', $before, $booking->fresh()->toArray());
        });
    }

    /** Tài xế nhận cuốc — khách trả trực tiếp, không cần QL xác nhận thanh toán. */
    public function confirmForDriverAccept(Booking $booking): void
    {
        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)) {
            return;
        }

        if ($booking->booking_status === 'confirmed' && $booking->trip_status === 'confirmed') {
            return;
        }

        DB::transaction(function () use ($booking): void {
            $before = $booking->toArray();

            $booking->update([
                'booking_status' => 'confirmed',
                'trip_status'    => 'confirmed',
                'payment_status' => 'paid',
                'confirmed_at'   => now(),
            ]);

            $booking->seatReservations()->update(['status' => 'booked', 'expires_at' => null]);
            $this->syncScheduleAvailability($booking->schedule()->with(['vehicle', 'seatReservations'])->first());
            $this->audit($booking, null, 'driver_accept_confirmed', $before, $booking->fresh()->toArray());
        });
    }

    public function driverCompleteTrip(Booking $booking, int $driverUserId): int
    {
        $booking->loadMissing('schedule');

        return $this->driverCompleteSchedule($booking->schedule, $driverUserId);
    }

    /** Hoàn thành tất cả vé còn lại trên cùng một chuyến xe. */
    public function driverCompleteSchedule(Schedule $schedule, int $driverUserId): int
    {
        if ((int) $schedule->driver_id !== $driverUserId) {
            abort(403, 'Bạn không được phân công cho chuyến này.');
        }

        $completed = 0;
        $completedBookings = collect();

        DB::transaction(function () use ($schedule, $driverUserId, &$completed, &$completedBookings): void {
            $bookings = Booking::query()
                ->where('schedule_id', $schedule->id)
                ->whereNotIn('booking_status', ['cancelled', 'rejected'])
                ->where('trip_status', '!=', 'completed')
                ->lockForUpdate()
                ->get();

            if ($bookings->isEmpty()) {
                throw new InvalidArgumentException('Không còn vé nào cần hoàn thành trên chuyến này.');
            }

            foreach ($bookings as $booking) {
                $this->completeSingleDriverTrip($booking, $driverUserId);
                $completedBookings->push($booking);
                $completed++;
            }
        });

        $this->driverWallet->onScheduleCompleted($schedule->fresh(['route', 'bookings']));

        return $completed;
    }

    private function completeSingleDriverTrip(Booking $booking, int $driverUserId): void
    {
        $this->assertNotTerminal($booking);

        if ($booking->trip_status === 'completed') {
            return;
        }

        $before = $booking->toArray();

        $booking->update([
            'trip_status'  => 'completed',
            'completed_at' => now(),
        ]);

        $this->audit($booking, $driverUserId, 'driver_trip_completed', $before, $booking->fresh()->toArray());
    }

    public function assertSeatsAvailable(Schedule $schedule, array $seats): void
    {
        $capacity = (int) $schedule->vehicle->capacity;

        foreach ($seats as $seat) {
            if (! is_numeric($seat) || (int) $seat < 1 || (int) $seat > $capacity) {
                throw new InvalidArgumentException('Số ghế không hợp lệ: ' . $seat);
            }
        }

        $taken = SeatReservation::query()
            ->where('schedule_id', $schedule->id)
            ->whereIn('seat_number', array_map(fn ($seat): string => (string) $seat, $seats))
            ->whereIn('status', ['held', 'booked'])
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->pluck('seat_number')
            ->all();

        if ($taken !== []) {
            throw new InvalidArgumentException(
                'Ghế ' . implode(', ', $taken) . ' đã có người đặt. Vui lòng chọn ghế khác hoặc đợi vài phút nếu bạn vừa đặt dở.'
            );
        }
    }

    public function findReusablePendingBooking(
        Schedule $schedule,
        string $contactPhone,
        array $seatNumbers,
    ): ?Booking {
        $targetSeats = collect($seatNumbers)->map(fn ($seat): string => (string) $seat)->sort()->values()->all();

        return Booking::query()
            ->where('schedule_id', $schedule->id)
            ->where('booking_status', 'pending')
            ->where(fn ($q) => $q->whereNull('hold_expires_at')->orWhere('hold_expires_at', '>', now()))
            ->get()
            ->first(function (Booking $booking) use ($contactPhone, $targetSeats): bool {
                if (! $booking->matchesContactPhone($contactPhone)) {
                    return false;
                }

                $seats = collect($booking->seat_numbers)->map(fn ($seat): string => (string) $seat)->sort()->values()->all();

                return $seats === $targetSeats;
            });
    }

    private function refreshPendingBooking(
        Booking $booking,
        string $passengerName,
        ?string $pickupAddress,
        ?string $pickupDetail,
        ?string $dropoffAddress,
        ?string $dropoffDetail,
        ?string $notes,
        string $tripType = 'one_way',
        string $bookingMode = 'shared',
    ): Booking {
        $booking->loadMissing('schedule.route');
        $seatCount = count($booking->seat_numbers ?? []);
        $totalPrice = $this->pricing->bookingTotal(
            $booking->schedule,
            $tripType,
            $bookingMode,
            max($seatCount, 1),
            $pickupAddress,
            $dropoffAddress,
        );

        $booking->update([
            'passenger_name'  => trim($passengerName),
            'pickup_address'  => $pickupAddress,
            'pickup_detail'   => $pickupDetail ? trim($pickupDetail) : null,
            'dropoff_address' => $dropoffAddress,
            'dropoff_detail'  => $dropoffDetail ? trim($dropoffDetail) : null,
            'notes'           => $notes,
            'booking_mode'    => $bookingMode,
            'trip_type'       => $tripType,
            'total_price'     => $totalPrice,
            'hold_expires_at' => null,
        ]);

        $booking->seatReservations()
            ->where('status', 'held')
            ->update(['expires_at' => null]);

        return $booking->fresh(['schedule']);
    }

    public function syncScheduleAvailability(Schedule $schedule): void
    {
        if (! $schedule) {
            return;
        }

        $schedule->loadMissing(['vehicle', 'seatReservations']);

        $active = $schedule->seatReservations
            ->filter(fn ($r) => in_array($r->status, ['held', 'booked'], true)
                && (! $r->expires_at || $r->expires_at->isFuture()))
            ->count();

        $schedule->forceFill(['available_seats' => max((int) $schedule->vehicle->capacity - $active, 0)])->save();
    }

    public function finalizeTripsAfterScheduleEnd(Schedule $schedule): void
    {
        Booking::query()
            ->where('schedule_id', $schedule->id)
            ->where('booking_status', 'confirmed')
            ->where('trip_status', 'confirmed')
            ->each(function (Booking $booking): void {
                $before = $booking->toArray();

                $booking->update([
                    'trip_status'  => 'awaiting_completion',
                    'completed_at' => now(),
                ]);

                $this->audit($booking, null, 'trip_auto_finalized', $before, $booking->fresh()->toArray());
            });
    }

    public function expireStaleBookings(): void
    {
    }

    private function assertContactPhone(Booking $booking, string $phone): void
    {
        if (! $booking->matchesContactPhone($phone)) {
            abort(403, 'Số điện thoại không khớp với vé.');
        }
    }

    private function assertDriverAssigned(Booking $booking, int $driverUserId): void
    {
        $schedule = $booking->schedule()->first();

        if (! $schedule || (int) $schedule->driver_id !== $driverUserId) {
            abort(403, 'Bạn không được phân công cho chuyến này.');
        }
    }

    private function assertNotTerminal(Booking $booking): void
    {
        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)) {
            throw new InvalidArgumentException('Vé đã bị hủy hoặc từ chối.');
        }
    }

    private function assertNotExpired(Booking $booking): void
    {
        if ($booking->isExpired()) {
            throw new InvalidArgumentException('Vé đã hết hạn, không thể thực hiện thao tác.');
        }
    }

    private function audit(Booking $booking, ?int $actor, string $action, ?array $before, ?array $after): void
    {
        BookingAudit::query()->create([
            'booking_id'   => $booking->id,
            'actor_id'     => $actor,
            'action'       => $action,
            'before_state' => $before,
            'after_state'  => $after,
        ]);
    }
}
