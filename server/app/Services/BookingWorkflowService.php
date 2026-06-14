<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingAudit;
use App\Models\PaymentTransaction;
use App\Models\Schedule;
use App\Models\SeatReservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class BookingWorkflowService
{
    public function createBooking(
        Schedule $schedule,
        int $customerId,
        array $seatNumbers,
        ?string $pickupAddress = null,
        ?string $dropoffAddress = null,
        ?string $notes = null,
    ): Booking {
        return DB::transaction(function () use ($schedule, $customerId, $seatNumbers, $pickupAddress, $dropoffAddress, $notes): Booking {
            $schedule = Schedule::query()
                ->with(['route', 'vehicle', 'seatReservations'])
                ->lockForUpdate()
                ->findOrFail($schedule->id);

            if (! in_array($schedule->status, ['scheduled'], true)) {
                abort(422, 'Chuyến không còn mở đặt vé (đang chạy hoặc đã kết thúc).');
            }

            if ($schedule->departure_time <= now()) {
                abort(422, 'Chuyến đã đến giờ khởi hành, không thể đặt thêm vé.');
            }

            $this->assertSeatsAvailable($schedule, $seatNumbers);

            $totalPrice  = round((float) $schedule->route->base_price * count($seatNumbers), 2);
            $holdExpires = now()->addMinutes(15);

            $booking = Booking::query()->create([
                'customer_id'       => $customerId,
                'schedule_id'       => $schedule->id,
                'seat_numbers'      => $seatNumbers,
                'ticket_code'       => 'TCK-' . Str::upper(Str::random(10)),
                'booking_reference' => 'BK-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(6)),
                'total_price'       => $totalPrice,
                'payment_status'    => 'unpaid',
                'trip_status'       => 'pending',
                'booking_status'    => 'pending',
                'pickup_address'    => $pickupAddress,
                'dropoff_address'   => $dropoffAddress,
                'notes'             => $notes,
                'hold_expires_at'   => $holdExpires,
            ]);

            foreach ($seatNumbers as $seat) {
                SeatReservation::query()->create([
                    'schedule_id'       => $schedule->id,
                    'booking_id'        => $booking->id,
                    'customer_id'       => $customerId,
                    'seat_number'       => $seat,
                    'reservation_token' => (string) Str::uuid(),
                    'status'            => 'held',
                    'expires_at'        => $holdExpires,
                ]);
            }

            $this->syncScheduleAvailability($schedule);
            $this->audit($booking, $customerId, 'booking_created', null, $booking->toArray());

            return $booking;
        });
    }

    /** Khách báo đã chuyển khoản — KHÔNG tự xác nhận thanh toán. */
    public function customerClaimPayment(Booking $booking, int $customerId): void
    {
        $this->assertCustomerOwns($booking, $customerId);
        $this->assertNotTerminal($booking);

        if ($booking->payment_status === 'paid') {
            throw new InvalidArgumentException('Vé đã được quản lý xác nhận thanh toán.');
        }

        if ($booking->paymentTransactions()->where('status', 'pending')->exists()) {
            throw new InvalidArgumentException('Đã gửi yêu cầu xác nhận thanh toán. Vui lòng chờ quản lý duyệt.');
        }

        DB::transaction(function () use ($booking, $customerId): void {
            PaymentTransaction::query()->create([
                'booking_id'       => $booking->id,
                'provider'         => 'bank_transfer',
                'amount'           => $booking->total_price,
                'currency'         => 'VND',
                'status'           => 'pending',
                'transaction_ref'  => 'CLM-' . Str::upper(Str::random(12)),
                'payload'          => ['claimed_by_customer' => true, 'claimed_at' => now()->toIso8601String()],
            ]);

            $this->audit($booking, $customerId, 'payment_claimed', $booking->toArray(), $booking->fresh()->toArray());
        });
    }

    /** Chỉ quản lý / admin xác nhận đã nhận tiền. */
    public function confirmPayment(Booking $booking, int $actorId, string $provider = 'manual'): void
    {
        $this->assertOperatorOrAdmin($actorId);
        $this->assertNotTerminal($booking);

        if ($booking->payment_status === 'paid') {
            throw new InvalidArgumentException('Thanh toán đã được xác nhận trước đó.');
        }

        DB::transaction(function () use ($booking, $actorId, $provider): void {
            $before = $booking->toArray();

            $booking->update(['payment_status' => 'paid']);

            $pending = $booking->paymentTransactions()->where('status', 'pending')->latest()->first();

            if ($pending) {
                $pending->update([
                    'status'   => 'succeeded',
                    'provider' => $provider,
                    'paid_at'  => now(),
                ]);
            } else {
                PaymentTransaction::query()->create([
                    'booking_id'      => $booking->id,
                    'provider'        => $provider,
                    'amount'          => $booking->total_price,
                    'currency'        => 'VND',
                    'status'          => 'succeeded',
                    'transaction_ref' => 'PAY-' . Str::upper(Str::random(12)),
                    'paid_at'         => now(),
                    'payload'         => ['confirmed_by' => $actorId],
                ]);
            }

            $booking->seatReservations()->update(['status' => 'booked', 'expires_at' => null]);
            $this->syncScheduleAvailability($booking->schedule()->with(['vehicle', 'seatReservations'])->first());
            $this->audit($booking, $actorId, 'payment_confirmed', $before, $booking->fresh()->toArray());
        });
    }

    /** Chỉ quản lý / admin duyệt chuyến — tài xế mới thấy thông tin hành khách. */
    public function acceptBooking(Booking $booking, int $actorId): void
    {
        $this->assertOperatorOrAdmin($actorId);
        $this->assertNotTerminal($booking);

        if ($booking->payment_status !== 'paid') {
            throw new InvalidArgumentException('Cần xác nhận thanh toán trước khi duyệt chuyến.');
        }

        if ($booking->booking_status === 'confirmed') {
            throw new InvalidArgumentException('Chuyến đã được duyệt.');
        }

        DB::transaction(function () use ($booking, $actorId): void {
            $before = $booking->toArray();

            $booking->update([
                'booking_status' => 'confirmed',
                'trip_status'    => 'confirmed',
                'confirmed_at'   => now(),
            ]);

            $booking->seatReservations()->update(['status' => 'booked', 'expires_at' => null]);
            $this->syncScheduleAvailability($booking->schedule()->with(['vehicle', 'seatReservations'])->first());
            $this->audit($booking, $actorId, 'booking_accepted', $before, $booking->fresh()->toArray());
        });
    }

    /** Tài xế báo đã chạy xong — chờ khách xác nhận hoàn chuyến. */
    public function driverCompleteTrip(Booking $booking, int $driverUserId): void
    {
        $this->assertDriverAssigned($booking, $driverUserId);
        $this->assertNotTerminal($booking);

        if ($booking->booking_status !== 'confirmed') {
            throw new InvalidArgumentException('Chuyến chưa được quản lý duyệt.');
        }

        if ($booking->trip_status === 'completed') {
            throw new InvalidArgumentException('Chuyến đã hoàn tất.');
        }

        if ($booking->trip_status === 'awaiting_completion') {
            throw new InvalidArgumentException('Đã báo hoàn thành — chờ khách xác nhận.');
        }

        DB::transaction(function () use ($booking, $driverUserId): void {
            $before = $booking->toArray();

            $booking->update(['trip_status' => 'awaiting_completion']);

            $this->audit($booking, $driverUserId, 'driver_trip_completed', $before, $booking->fresh()->toArray());
        });
    }

    /** Khách hàng xác nhận hoàn chuyến. */
    public function customerConfirmTripComplete(Booking $booking, int $customerId): void
    {
        $this->assertCustomerOwns($booking, $customerId);

        if ($booking->trip_status !== 'awaiting_completion') {
            throw new InvalidArgumentException('Chuyến chưa sẵn sàng để xác nhận hoàn tất. Tài xế cần báo hoàn thành trước.');
        }

        DB::transaction(function () use ($booking, $customerId): void {
            $before = $booking->toArray();

            $booking->update([
                'trip_status'  => 'completed',
                'completed_at' => now(),
            ]);

            $this->audit($booking, $customerId, 'trip_completed_by_customer', $before, $booking->fresh()->toArray());
        });
    }

    public function rejectBooking(Booking $booking, int $actorId): void
    {
        $this->assertOperatorOrAdmin($actorId);
        $this->assertNotTerminal($booking);

        DB::transaction(function () use ($booking, $actorId): void {
            $before = $booking->toArray();

            $booking->update([
                'booking_status' => 'rejected',
                'trip_status'    => 'cancelled',
                'payment_status' => $booking->payment_status === 'paid' ? 'refunded' : $booking->payment_status,
                'cancelled_at'   => now(),
            ]);

            $booking->seatReservations()->update(['status' => 'released', 'expires_at' => now()]);
            $booking->paymentTransactions()->where('status', 'pending')->update(['status' => 'failed']);
            $this->syncScheduleAvailability($booking->schedule()->with(['vehicle', 'seatReservations'])->first());
            $this->audit($booking, $actorId, 'booking_rejected', $before, $booking->fresh()->toArray());
        });
    }

    public function cancelByCustomer(Booking $booking, int $customerId): void
    {
        $this->assertCustomerOwns($booking, $customerId);

        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)) {
            throw new InvalidArgumentException('Vé này đã hủy rồi.');
        }

        if ($booking->trip_status === 'completed') {
            throw new InvalidArgumentException('Chuyến đã hoàn tất, không thể hủy.');
        }

        DB::transaction(function () use ($booking, $customerId): void {
            $before = $booking->toArray();

            $booking->update([
                'booking_status' => 'cancelled',
                'trip_status'    => 'cancelled',
                'payment_status' => $booking->payment_status === 'paid' ? 'refunded' : 'unpaid',
                'cancelled_at'   => now(),
            ]);

            $booking->seatReservations()->update(['status' => 'released', 'expires_at' => now()]);
            $booking->paymentTransactions()->where('status', 'pending')->update(['status' => 'failed']);
            $this->syncScheduleAvailability($booking->schedule()->with(['vehicle', 'seatReservations'])->first());
            $this->audit($booking, $customerId, 'booking_cancelled', $before, $booking->fresh()->toArray());
        });
    }

    public function assertSeatsAvailable(Schedule $schedule, array $seats): void
    {
        $capacity = (int) $schedule->vehicle->capacity;

        foreach ($seats as $seat) {
            if (! is_numeric($seat) || (int) $seat < 1 || (int) $seat > $capacity) {
                abort(422, 'Số ghế không hợp lệ: ' . $seat);
            }
        }

        $taken = SeatReservation::query()
            ->where('schedule_id', $schedule->id)
            ->whereIn('seat_number', $seats)
            ->whereIn('status', ['held', 'booked'])
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();

        if ($taken) {
            abort(422, 'Một hoặc nhiều ghế đã được đặt trước.');
        }
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

    private function assertCustomerOwns(Booking $booking, int $customerId): void
    {
        if ($booking->customer_id !== $customerId) {
            abort(403);
        }
    }

    private function assertOperatorOrAdmin(int $actorId): void
    {
        $role = User::query()->whereKey($actorId)->value('role');

        if (! in_array($role, ['operator', 'admin'], true)) {
            abort(403, 'Chỉ quản lý hoặc admin mới được thực hiện thao tác này.');
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
