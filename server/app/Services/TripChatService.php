<?php

namespace App\Services;

use App\Http\Resources\TripMessageResource;
use App\Models\Booking;
use App\Models\Schedule;
use App\Models\TripMessage;
use App\Models\User;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class TripChatService
{
    public function isOpen(Booking $booking): bool
    {
        $booking->loadMissing('schedule');
        $schedule = $booking->schedule;

        if (! $schedule || ! $schedule->driver_id) {
            return false;
        }

        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)
            || in_array($booking->trip_status, ['completed', 'cancelled'], true)) {
            return false;
        }

        $stage = $schedule->resolvedDriverStage();

        if ($stage === Schedule::DRIVER_STAGE_ASSIGNED) {
            return $schedule->driver_movement_confirmed_at !== null;
        }

        return in_array($stage, [
            Schedule::DRIVER_STAGE_AT_PICKUP,
            Schedule::DRIVER_STAGE_PICKED_UP,
            Schedule::DRIVER_STAGE_RUNNING,
        ], true);
    }

    public function statusMessage(Booking $booking): string
    {
        if ($this->isOpen($booking)) {
            return 'Bạn có thể nhắn tin trong thời gian tài xế đi đón và thực hiện chuyến.';
        }

        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)
            || in_array($booking->trip_status, ['completed', 'cancelled'], true)) {
            return 'Cuộc trò chuyện đã đóng vì chuyến đi đã kết thúc.';
        }

        return 'Chat sẽ mở khi tài xế xác nhận bắt đầu đi đón.';
    }

    public function driverCanAccess(Booking $booking, User $driver): bool
    {
        $booking->loadMissing('schedule');

        if ($driver->role !== 'driver' || ! $booking->schedule) {
            return false;
        }

        if ((int) $booking->schedule->driver_id !== (int) $driver->id) {
            return false;
        }

        if ($booking->assigned_driver_id
            && (int) $booking->assigned_driver_id !== (int) $driver->id) {
            return false;
        }

        return true;
    }

    /** @return Collection<int, TripMessage> */
    public function messages(Booking $booking, int $afterId = 0): Collection
    {
        return TripMessage::query()
            ->where('booking_id', $booking->id)
            ->when($afterId > 0, fn ($query) => $query->where('id', '>', $afterId))
            ->orderBy('id')
            ->limit(100)
            ->get();
    }

    public function send(Booking $booking, string $role, string $body, ?User $sender): TripMessage
    {
        if (! $this->isOpen($booking)) {
            throw new InvalidArgumentException($this->statusMessage($booking));
        }

        $body = trim($body);
        if ($body === '') {
            throw new InvalidArgumentException('Vui lòng nhập nội dung tin nhắn.');
        }

        return TripMessage::query()->create([
            'booking_id'    => $booking->id,
            'sender_user_id'=> $sender?->id,
            'sender_role'   => $role,
            'body'          => $body,
        ]);
    }

    /** @return array<string, mixed> */
    public function serialize(TripMessage $message): array
    {
        return (new TripMessageResource($message))->resolve();
    }
}
