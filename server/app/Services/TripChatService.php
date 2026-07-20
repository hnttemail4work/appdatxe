<?php

namespace App\Services;

use App\Http\Resources\TripMessageResource;
use App\Models\Booking;
use App\Models\Schedule;
use App\Models\TripMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class TripChatService
{
    public const DRIVER_THREAD_LIMIT = 10;

    public const CUSTOMER_THREAD_LIMIT = 10;

    /** Số tin tối đa giữ lại trong mỗi chuyến (cũ hơn sẽ bị xóa). */
    public const MESSAGE_LIMIT = 10;

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
        $this->pruneOldMessages($booking);

        $latest = TripMessage::query()
            ->where('booking_id', $booking->id)
            ->orderByDesc('id')
            ->limit(self::MESSAGE_LIMIT)
            ->get()
            ->sortBy('id')
            ->values();

        if ($afterId > 0) {
            return $latest->filter(fn (TripMessage $message): bool => (int) $message->id > $afterId)->values();
        }

        return $latest;
    }

    public function send(
        Booking $booking,
        string $role,
        string $body,
        ?User $sender,
        ?UploadedFile $image = null,
    ): TripMessage {
        if (! $this->isOpen($booking)) {
            throw new InvalidArgumentException($this->statusMessage($booking));
        }

        $body = trim($body);
        if ($body === '' && ! $image) {
            throw new InvalidArgumentException('Vui lòng nhập nội dung tin nhắn hoặc đính kèm ảnh.');
        }

        $imagePath = null;
        if ($image) {
            $dir = 'trip-chat/'.$booking->id;
            Storage::disk('public')->makeDirectory($dir);
            $imagePath = $image->store($dir, 'public');
        }

        $message = TripMessage::query()->create([
            'booking_id'     => $booking->id,
            'sender_user_id' => $sender?->id,
            'sender_role'    => $role,
            'body'           => $body,
            'image_path'     => $imagePath,
        ]);

        $this->pruneOldMessages($booking);

        return $message->fresh() ?? $message;
    }

    /**
     * Lưu lịch sử gọi app vào tin nhắn chuyến (cuộc gọi nhỡ / đã nhận).
     *
     * @param  'missed'|'answered'  $outcome
     */
    public function logDriverCall(Booking $booking, User $driver, string $outcome): TripMessage
    {
        $body = match ($outcome) {
            'answered' => '📞 Cuộc gọi app đã nhận',
            'missed'   => '📞 Cuộc gọi nhỡ',
            default    => throw new InvalidArgumentException('Kết quả cuộc gọi không hợp lệ.'),
        };

        return $this->send($booking, 'driver', $body, $driver);
    }

    /**
     * Lịch sử gọi app phía khách → tin nhắn chuyến.
     *
     * @param  'missed'|'answered'  $outcome
     */
    public function logCustomerCall(Booking $booking, User $customer, string $outcome): TripMessage
    {
        $body = match ($outcome) {
            'answered' => '📞 Cuộc gọi app đã nhận',
            'missed'   => '📞 Cuộc gọi nhỡ',
            default    => throw new InvalidArgumentException('Kết quả cuộc gọi không hợp lệ.'),
        };

        return $this->send($booking, 'customer', $body, $customer);
    }

    /** Giữ tối đa MESSAGE_LIMIT tin mới nhất; xóa tin cũ + file ảnh. */
    public function pruneOldMessages(Booking $booking): void
    {
        $keepIds = TripMessage::query()
            ->where('booking_id', $booking->id)
            ->orderByDesc('id')
            ->limit(self::MESSAGE_LIMIT)
            ->pluck('id');

        if ($keepIds->isEmpty()) {
            return;
        }

        $oldMessages = TripMessage::query()
            ->where('booking_id', $booking->id)
            ->whereNotIn('id', $keepIds)
            ->get();

        foreach ($oldMessages as $old) {
            $path = trim((string) ($old->image_path ?? ''));
            if ($path !== '' && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
            $old->delete();
        }
    }

    /** @return array<string, mixed> */
    public function serialize(TripMessage $message): array
    {
        return (new TripMessageResource($message))->resolve();
    }

    public function unreadCountForBooking(Booking $booking): int
    {
        $lastRead = (int) ($booking->driver_chat_last_read_id ?? 0);

        return (int) TripMessage::query()
            ->where('booking_id', $booking->id)
            ->where('sender_role', 'customer')
            ->when($lastRead > 0, fn ($query) => $query->where('id', '>', $lastRead))
            ->count();
    }

    public function unreadCountForCustomerBooking(Booking $booking): int
    {
        $lastRead = (int) ($booking->customer_chat_last_read_id ?? 0);

        return (int) TripMessage::query()
            ->where('booking_id', $booking->id)
            ->where('sender_role', 'driver')
            ->when($lastRead > 0, fn ($query) => $query->where('id', '>', $lastRead))
            ->count();
    }

    public function unreadCountForDriver(int $driverUserId): int
    {
        return (int) DB::table('trip_messages as m')
            ->join('bookings as b', 'b.id', '=', 'm.booking_id')
            ->leftJoin('schedules as s', 's.id', '=', 'b.schedule_id')
            ->where('m.sender_role', 'customer')
            ->where(function ($query) use ($driverUserId): void {
                $query->where('b.assigned_driver_id', $driverUserId)
                    ->orWhere('s.driver_id', $driverUserId);
            })
            ->where(function ($query): void {
                $query->whereNull('b.driver_chat_last_read_id')
                    ->orWhereColumn('m.id', '>', 'b.driver_chat_last_read_id');
            })
            ->count();
    }

    public function unreadCountForCustomer(int $customerUserId): int
    {
        return (int) DB::table('trip_messages as m')
            ->join('bookings as b', 'b.id', '=', 'm.booking_id')
            ->where('b.customer_id', $customerUserId)
            ->where('m.sender_role', 'driver')
            ->where(function ($query): void {
                $query->whereNull('b.customer_chat_last_read_id')
                    ->orWhereColumn('m.id', '>', 'b.customer_chat_last_read_id');
            })
            ->count();
    }

    /**
     * Gộp unread hệ thống + chat (badge dock / chuông) — tài xế.
     *
     * @param  array{info?: int, notice?: int, total?: int}  $inboxUnread
     * @return array{info: int, notice: int, chat: int, total: int}
     */
    public function mergeInboxUnread(array $inboxUnread, int $driverUserId): array
    {
        $info = (int) ($inboxUnread['info'] ?? 0);
        $notice = (int) ($inboxUnread['notice'] ?? 0);
        $chat = $this->unreadCountForDriver($driverUserId);

        return [
            'info'   => $info,
            'notice' => $notice,
            'chat'   => $chat,
            'total'  => $info + $notice + $chat,
        ];
    }

    /**
     * Gộp unread hệ thống + chat — khách.
     *
     * @param  array{info?: int, notice?: int, total?: int}  $inboxUnread
     * @return array{info: int, notice: int, chat: int, total: int}
     */
    public function mergeCustomerInboxUnread(array $inboxUnread, int $customerUserId): array
    {
        $info = (int) ($inboxUnread['info'] ?? 0);
        $notice = (int) ($inboxUnread['notice'] ?? 0);
        $chat = $this->unreadCountForCustomer($customerUserId);

        return [
            'info'   => $info,
            'notice' => $notice,
            'chat'   => $chat,
            'total'  => $info + $notice + $chat,
        ];
    }

    public function markDriverRead(Booking $booking): void
    {
        $this->markChatRead($booking, 'driver_chat_last_read_id');
    }

    public function markCustomerRead(Booking $booking): void
    {
        $this->markChatRead($booking, 'customer_chat_last_read_id');
    }

    private function markChatRead(Booking $booking, string $column): void
    {
        $maxId = (int) TripMessage::query()
            ->where('booking_id', $booking->id)
            ->max('id');

        if ($maxId < 1) {
            return;
        }

        if ((int) ($booking->{$column} ?? 0) >= $maxId) {
            return;
        }

        $booking->forceFill([$column => $maxId])->save();
    }

    /**
     * Tối đa 10 cuộc gần nhất có tin nhắn (kể cả chuyến đã xong).
     *
     * @return Collection<int, array{booking: Booking, unread: int, preview: string, open: bool, last_at: ?string}>
     */
    public function recentThreadsForDriver(int $driverUserId, ?int $limit = null): Collection
    {
        $limit ??= self::DRIVER_THREAD_LIMIT;

        $bookings = Booking::query()
            ->whereHas('tripMessages')
            ->where(function ($query) use ($driverUserId): void {
                $query->where('assigned_driver_id', $driverUserId)
                    ->orWhereHas('schedule', fn ($schedule) => $schedule->where('driver_id', $driverUserId));
            })
            ->with(['latestTripMessage', 'schedule'])
            ->withMax('tripMessages', 'created_at')
            ->orderByDesc('trip_messages_max_created_at')
            ->limit($limit)
            ->get();

        return $bookings->map(function (Booking $booking): array {
            return $this->mapThread($booking, $this->unreadCountForBooking($booking));
        });
    }

    /**
     * @return Collection<int, array{booking: Booking, unread: int, preview: string, open: bool, last_at: ?string}>
     */
    public function recentThreadsForCustomer(int $customerUserId, ?int $limit = null): Collection
    {
        $limit ??= self::CUSTOMER_THREAD_LIMIT;

        $bookings = Booking::query()
            ->where('customer_id', $customerUserId)
            ->whereHas('tripMessages')
            ->with(['latestTripMessage', 'schedule', 'assignedDriver'])
            ->withMax('tripMessages', 'created_at')
            ->orderByDesc('trip_messages_max_created_at')
            ->limit($limit)
            ->get();

        return $bookings->map(function (Booking $booking): array {
            return $this->mapThread($booking, $this->unreadCountForCustomerBooking($booking));
        });
    }

    /**
     * @return array{booking: Booking, unread: int, preview: string, open: bool, last_at: ?string}
     */
    private function mapThread(Booking $booking, int $unread): array
    {
        $last = $booking->latestTripMessage;
        $preview = $last?->previewText() ?? '';
        if (mb_strlen($preview) > 80) {
            $preview = mb_substr($preview, 0, 80).'…';
        }

        return [
            'booking' => $booking,
            'unread'  => $unread,
            'preview' => $preview,
            'open'    => $this->isOpen($booking),
            'last_at' => $last?->created_at?->format('d/m H:i'),
        ];
    }
}
