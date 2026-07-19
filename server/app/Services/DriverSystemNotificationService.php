<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\DriverInboxMessage;
use App\Models\DriverProfile;
use App\Support\ProvinceCenters;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Cổng thống nhất cho thông báo hệ thống tự động tới tài xế
 * (hộp thư + Web Push + badge), dễ chỉnh copy / loại sau này.
 */
class DriverSystemNotificationService
{
    public const TYPE_NEARBY_SEARCH = 'nearby_search_nudge';

    public const NEARBY_SEARCH_TITLE = 'Khách gần bạn đang tìm chuyến';

    public const NEARBY_SEARCH_BODY = 'Khách gần bạn đang tìm chuyến, bật app ngay để nhận chuyến bạn nhé';

    /** Bán kính “gần bạn” khi TX đã tắt app (km). */
    public const NEARBY_SEARCH_RADIUS_KM = 20.0;

    /** Giữ vị trí / phiên offline để nudge (phút). */
    public const OFFLINE_SESSION_TTL_MINUTES = 120;

    public function __construct(
        private readonly DriverInboxService $inbox,
        private readonly DriverAvailabilityService $availability,
        private readonly DriverProximityService $proximity,
        private readonly TripChatService $tripChat,
    ) {
    }

    /**
     * Gửi thông báo hệ thống (1 lối vào chung).
     *
     * @param  array<string, mixed>  $meta
     */
    public function dispatch(
        int $userId,
        string $type,
        string $title,
        string $body,
        array $meta = [],
        ?string $eventKey = null,
        ?string $url = null,
        ?string $dedupKey = null,
        string $category = DriverInboxMessage::CATEGORY_NOTICE,
    ): ?DriverInboxMessage {
        if ($userId < 1) {
            return null;
        }

        $eventKey ??= 'driver.system_' . $type;
        $url ??= '/driver/dashboard';
        $dedupKey ??= 'driver-system:' . $type . ':' . $userId . ':' . Str::uuid()->toString();
        $meta = array_merge(['type' => $type, 'system' => true], $meta);

        try {
            $message = $this->inbox->notify(
                $userId,
                $category,
                $title,
                $body,
                $meta,
                push: true,
                eventKey: $eventKey,
                url: $url,
                dedupKey: $dedupKey,
            );

            return $message;
        } catch (\Throwable $e) {
            Log::warning('driver_system_notification_failed', [
                'user_id' => $userId,
                'type'    => $type,
                'error'   => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** TX vừa tắt app / mất hiện diện — mở phiên offline + giữ vị trí để nudge. */
    public function armOfflineSession(DriverProfile $profile): void
    {
        $userId = (int) $profile->user_id;
        if ($userId < 1) {
            return;
        }

        $ttl = now()->addMinutes(self::OFFLINE_SESSION_TTL_MINUTES);
        $sessionKey = $this->offlineSessionKey($userId);

        // Chỉ tạo phiên mới khi chưa có — tránh reset cờ đã gửi nếu markOffDuty gọi lại.
        if (! Cache::has($sessionKey)) {
            Cache::put($sessionKey, (string) Str::uuid(), $ttl);
            Cache::forget($this->nearbyNudgeSentKey($userId));
        } else {
            Cache::put($sessionKey, Cache::get($sessionKey), $ttl);
        }

        $lat = $profile->last_lat;
        $lng = $profile->last_lng;
        if ($lat !== null && $lng !== null) {
            Cache::put($this->offlineLocationKey($userId), [
                'lat' => (float) $lat,
                'lng' => (float) $lng,
                'at'  => optional($profile->last_location_at)?->toIso8601String(),
            ], $ttl);
        }
    }

    /** TX bật lại app / sẵn sàng — cho phép nudge lại sau khi tắt app lần sau. */
    public function clearOfflineSession(int $driverUserId): void
    {
        if ($driverUserId < 1) {
            return;
        }

        Cache::forget($this->offlineSessionKey($driverUserId));
        Cache::forget($this->nearbyNudgeSentKey($driverUserId));
        Cache::forget($this->offlineLocationKey($driverUserId));
    }

    public function unreadBadgeTotal(int $driverUserId): int
    {
        return (int) ($this->tripChat->mergeInboxUnread(
            $this->inbox->unreadCounts($driverUserId),
            $driverUserId,
        )['total'] ?? 0);
    }

    /**
     * Khi có khách đang tìm gần TX đã tắt app — tối đa 1 lần / phiên offline.
     *
     * @return int số TX được nudge
     */
    public function notifyNearbySearchForBooking(Booking $booking): int
    {
        $booking->loadMissing('schedule');

        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)
            || in_array($booking->trip_status, ['completed', 'cancelled'], true)
            || $booking->schedule?->driver_id
            || $booking->hasDriverAccepted()) {
            return 0;
        }

        $pickup = $this->proximity->pickupCoordinates($booking);
        if ($pickup === null) {
            return 0;
        }

        $sent = 0;

        foreach ($this->offlineDriverCandidates() as $profile) {
            if ($this->tryNearbySearchNudge($profile, $pickup, $booking)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * @param  array{lat: float, lng: float}  $pickup
     */
    private function tryNearbySearchNudge(DriverProfile $profile, array $pickup, Booking $booking): bool
    {
        $userId = (int) $profile->user_id;
        if ($userId < 1) {
            return false;
        }

        // App đang mở / còn hiện diện → không nudge kiểu “bật app”.
        if ($this->availability->isDriverAppActiveForAdmin($profile)
            || $this->availability->hasWebPresence($userId)) {
            return false;
        }

        $sessionId = Cache::get($this->offlineSessionKey($userId));
        if (! is_string($sessionId) || $sessionId === '') {
            return false;
        }

        if (Cache::has($this->nearbyNudgeSentKey($userId))) {
            return false;
        }

        $location = Cache::get($this->offlineLocationKey($userId));
        if (! is_array($location) || ! isset($location['lat'], $location['lng'])) {
            return false;
        }

        $distance = ProvinceCenters::distanceKm(
            (float) $location['lat'],
            (float) $location['lng'],
            (float) $pickup['lat'],
            (float) $pickup['lng'],
        );

        if ($distance > self::NEARBY_SEARCH_RADIUS_KM) {
            return false;
        }

        // Khóa phiên trước khi gửi — tránh race khi nhiều khách tìm cùng lúc.
        $ttl = now()->addMinutes(self::OFFLINE_SESSION_TTL_MINUTES);
        if (! Cache::add($this->nearbyNudgeSentKey($userId), $sessionId, $ttl)) {
            return false;
        }

        $message = $this->dispatch(
            $userId,
            self::TYPE_NEARBY_SEARCH,
            self::NEARBY_SEARCH_TITLE,
            self::NEARBY_SEARCH_BODY,
            [
                'booking_reference' => $booking->booking_reference,
                'distance_km'       => round($distance, 1),
                'offline_session'   => $sessionId,
            ],
            eventKey: 'driver.nearby_search_nudge',
            url: '/driver/dashboard',
            // Tag ổn định theo phiên — OS thay thế, không chồng thông báo lặp.
            dedupKey: 'driver:nearby-search:' . $userId . ':' . $sessionId,
        );

        if (! $message) {
            Cache::forget($this->nearbyNudgeSentKey($userId));

            return false;
        }

        return true;
    }

    /** @return \Illuminate\Support\Collection<int, DriverProfile> */
    private function offlineDriverCandidates()
    {
        return DriverProfile::query()
            ->operational()
            ->where('approval_status', 'approved')
            ->where(function ($q): void {
                $q->where('availability_status', 'off_duty')
                    ->orWhereNull('availability_status');
            })
            ->with('user')
            ->get();
    }

    private function offlineSessionKey(int $userId): string
    {
        return 'driver_offline_session:' . $userId;
    }

    private function nearbyNudgeSentKey(int $userId): string
    {
        return 'driver_nearby_search_nudge_sent:' . $userId;
    }

    private function offlineLocationKey(int $userId): string
    {
        return 'driver_offline_location:' . $userId;
    }
}
