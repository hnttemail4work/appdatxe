<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\DriverTripRequest;
use App\Models\PushSubscription;
use App\Models\Schedule;
use App\Models\User;
use App\Support\AppBrandingSettings;
use App\Support\AuthIdentifier;
use App\Support\PushAudience;
use App\Support\PushNotificationSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushNotificationService
{
    public function resolveAudience(?User $user = null): string
    {
        return PushAudience::resolve($user);
    }

    /** @param array<string, mixed> $payload */
    public function subscribe(Request $request, array $payload): PushSubscription
    {
        if (! PushAudience::enabledFor($request->user())) {
            throw new \InvalidArgumentException('Tài khoản quản trị không dùng thông báo đẩy.');
        }

        $audience = $this->resolveAudience($request->user());
        $endpoint = trim((string) ($payload['endpoint'] ?? ''));
        $publicKey = trim((string) ($payload['keys']['p256dh'] ?? $payload['public_key'] ?? ''));
        $authToken = trim((string) ($payload['keys']['auth'] ?? $payload['auth_token'] ?? ''));

        if ($endpoint === '' || $publicKey === '' || $authToken === '') {
            throw new \InvalidArgumentException('Thiếu thông tin đăng ký thông báo.');
        }

        $browserId = trim((string) ($payload['browser_id'] ?? ''));
        $contactPhone = $this->normalizePhone((string) ($payload['contact_phone'] ?? ''));

        return PushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => hash('sha256', $endpoint)],
            [
                'endpoint'          => $endpoint,
                'audience'          => $audience,
                'user_id'           => $request->user()?->id,
                'browser_id'        => $browserId !== '' ? $browserId : null,
                'contact_phone'     => $contactPhone !== '' ? $contactPhone : null,
                'public_key'        => $publicKey,
                'auth_token'        => $authToken,
                'content_encoding'  => trim((string) ($payload['content_encoding'] ?? 'aesgcm')) ?: 'aesgcm',
                'user_agent'        => Str::limit((string) $request->userAgent(), 500, ''),
                'last_seen_at'      => now(),
            ],
        );
    }

    public function unsubscribe(string $endpoint): void
    {
        PushSubscription::query()->where('endpoint_hash', hash('sha256', $endpoint))->delete();
    }

    public function touchContactPhone(string $browserId, string $contactPhone): void
    {
        $browserId = trim($browserId);
        $phone = $this->normalizePhone($contactPhone);
        if ($browserId === '' || $phone === '') {
            return;
        }

        PushSubscription::query()
            ->where('audience', PushAudience::GUEST)
            ->where('browser_id', $browserId)
            ->update(['contact_phone' => $phone, 'last_seen_at' => now()]);
    }

    public function onDriverTripRequestCreated(DriverTripRequest $request): void
    {
        $this->notifyDriverTripRequest($request, 'driver:trip:' . $request->id . ':new');
    }

    /** Báo app TX thu hồi offer đã hết hạn/hủy (client_event → tab đang mở gỡ card). */
    public function onDriverTripRequestExpired(DriverTripRequest $request): void
    {
        $routeLabel = $this->tripRequestRouteLabel($request);

        $cancelledByGuest = $request->status === 'cancelled';
        $this->sendToDriver(
            (int) $request->driver_id,
            'driver.trip_cancelled',
            $cancelledByGuest ? 'Khách đã hủy chuyến' : 'Cuốc chờ nhận đã thu hồi',
            $cancelledByGuest
                ? ($routeLabel !== ''
                    ? 'Khách đã hủy chuyến: ' . $routeLabel
                    : 'Khách đã hủy chuyến.')
                : ($routeLabel !== ''
                    ? 'Yêu cầu nhận cuốc đã hết hạn: ' . $routeLabel
                    : 'Yêu cầu nhận cuốc đã hết hạn.'),
            '/driver/dashboard',
            'driver:trip:' . $request->id . ':expired:' . ($request->responded_at?->timestamp ?? now()->timestamp),
            [
                'client_event'     => 'driver_trip_request_expired',
                'driver_request_id' => (int) $request->id,
                'request_status'   => (string) $request->status,
            ],
        );
    }

    public function nudgeDriverTripRequest(DriverTripRequest $request): bool
    {
        if ($request->status !== 'pending') {
            return false;
        }

        return $this->notifyDriverTripRequest(
            $request,
            'driver:trip:' . $request->id . ':nudge:' . now()->format('YmdHi'),
        );
    }

    public function onDriverDepartReminder(Booking $booking, ?string $etaLabel = null): bool
    {
        $booking->loadMissing('schedule.route');
        $routeLabel = $this->routeLabel($booking);
        $body = 'Vui lòng di chuyển đến điểm đón khách';
        if ($etaLabel) {
            $body .= ' — dự kiến ' . $etaLabel;
        }
        if ($routeLabel !== '') {
            $body .= ' · ' . $routeLabel;
        }

        return $this->notifyDriverBooking(
            $booking,
            'driver.depart_reminder',
            'Đến lúc xuất phát đón khách',
            $body,
            $this->pickupDedupKey($booking, 'depart', now()->format('YmdH')),
        );
    }

    public function onDriverPickupUrgent(Booking $booking, int $minutesUntilPickup): bool
    {
        $bucket = match (true) {
            $minutesUntilPickup <= 5  => '5',
            $minutesUntilPickup <= 10 => '10',
            default                   => '15',
        };

        return $this->notifyDriverBooking(
            $booking,
            'driver.pickup_urgent',
            'Sát giờ đón khách',
            'Còn ' . $minutesUntilPickup . ' phút tới giờ đón — mở app Tài xế và chia sẻ vị trí GPS.',
            $this->pickupDedupKey($booking, 'warn', $bucket),
        );
    }

    public function onDriverLatePickupDue(Booking $booking): bool
    {
        return $this->notifyDriverBooking(
            $booking,
            'driver.late_pickup',
            'Đã đến giờ đón',
            'Vui lòng đến điểm đón khách ngay hoặc bấm «Tiếp tục» trên app Tài xế.',
            $this->pickupDedupKey($booking, 'late', 'due'),
        );
    }

    public function onDriverMovementDeadline(Booking $booking, int $minutesRemaining): bool
    {
        return $this->notifyDriverBooking(
            $booking,
            'driver.movement_deadline',
            'Hạn xác nhận di chuyển',
            'Còn ' . max(1, $minutesRemaining) . ' phút để bấm «Đến điểm đón» trên app Tài xế.',
            $this->pickupDedupKey($booking, 'movement', (string) max(1, $minutesRemaining)),
        );
    }

    /**
     * @return array{has_push: bool, sent_at: ?\Carbon\Carbon, sent_label: ?string}
     */
    public function pickupNotifyMetaForBooking(Booking $booking): array
    {
        $driverUserId = (int) ($booking->resolveAssignedDriverId() ?? 0);
        $hasPush = $driverUserId > 0
            && PushSubscription::query()
                ->where('audience', PushAudience::DRIVER)
                ->where('user_id', $driverUserId)
                ->exists();

        $prefix = $this->pickupDedupPrefix($booking);
        $row = $prefix === ''
            ? null
            : DB::table('push_notification_dedup')
                ->where('dedup_key', 'like', $prefix . '%')
                ->orderByDesc('sent_at')
                ->first();

        $sentAt = $row?->sent_at ? \Carbon\Carbon::parse($row->sent_at) : null;

        return [
            'has_push'   => $hasPush,
            'sent_at'    => $sentAt,
            'sent_label' => $sentAt ? ('Đã TB đến TX lúc ' . $sentAt->format('H:i')) : null,
        ];
    }

    protected function notifyDriverBooking(
        Booking $booking,
        string $eventKey,
        string $title,
        string $body,
        string $dedupKey,
    ): bool {
        $driverUserId = (int) ($booking->resolveAssignedDriverId() ?? 0);
        if ($driverUserId <= 0) {
            return false;
        }

        $subscriptions = PushSubscription::query()
            ->where('audience', PushAudience::DRIVER)
            ->where('user_id', $driverUserId)
            ->get();

        if ($subscriptions->isEmpty()) {
            return false;
        }

        $this->dispatch(
            $subscriptions,
            $eventKey,
            $title,
            $body,
            '/driver/dashboard',
            $dedupKey,
        );

        return DB::table('push_notification_dedup')->where('dedup_key', $dedupKey)->exists();
    }

    protected function pickupDedupPrefix(Booking $booking): string
    {
        return 'driver:booking:' . (int) $booking->id . ':pickup:';
    }

    protected function pickupDedupKey(Booking $booking, string $kind, string $bucket): string
    {
        return $this->pickupDedupPrefix($booking) . $kind . ':' . $bucket;
    }

    protected function routeLabel(Booking $booking): string
    {
        $label = trim($booking->routeDetailLabel());

        return $label !== '—' ? $label : '';
    }

    /** Địa điểm đón/trả chi tiết cho TB tài xế — không chỉ tỉnh/thành trên route. */
    protected function tripRequestRouteLabel(DriverTripRequest $request): string
    {
        $request->loadMissing('schedule.route', 'schedule.bookings');
        $booking = $request->relatedBooking();

        if ($booking) {
            return $this->routeLabel($booking);
        }

        $schedule = $request->schedule;
        $routeLabel = trim(($schedule?->route?->departure ?? '') . ' → ' . ($schedule?->route?->destination ?? ''));

        return $routeLabel !== '→' ? $routeLabel : '';
    }

    protected function notifyDriverTripRequest(DriverTripRequest $request, string $dedupKey): bool
    {
        if ($request->status !== 'pending') {
            return false;
        }

        $routeLabel = $this->tripRequestRouteLabel($request);

        $subscriptions = PushSubscription::query()
            ->where('audience', PushAudience::DRIVER)
            ->where('user_id', (int) $request->driver_id)
            ->get();

        if ($subscriptions->isEmpty()) {
            return false;
        }

        $this->dispatch(
            $subscriptions,
            'driver.new_trip_request',
            'Cuốc mới chờ nhận',
            $routeLabel !== '' ? $routeLabel : 'Có chuyến mới cần xác nhận.',
            '/driver/dashboard?tab=trips',
            $dedupKey,
            [
                'client_event'         => 'driver_trip_request_created',
                'event_key'            => 'driver.new_trip_request',
                'driver_request_id'    => (int) $request->id,
                'request_status'       => (string) $request->status,
                'require_interaction'  => true,
            ],
        );

        return true;
    }

    public function onDriverAcceptedBooking(Booking $booking): void
    {
        $booking->loadMissing('schedule');
        $driverName = $booking->schedule?->driver_name ?? 'Tài xế';

        $this->sendToGuestBooking(
            $booking,
            'guest.driver_accepted',
            'Tài xế đã nhận chuyến',
            $driverName . ' đã nhận chuyến ' . ($booking->booking_reference ?? '#' . $booking->id) . '.',
            '/chuyen',
            'guest:booking:' . $booking->id . ':accepted',
        );
    }

    public function onDriverStageAdvanced(Schedule $schedule, string $stage): void
    {
        $schedule->loadMissing('driverRelevantBookings');
        $bookings = $schedule->driverRelevantBookings();

        foreach ($bookings as $booking) {
            if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)
                || $booking->trip_status === 'cancelled') {
                continue;
            }

            if ($stage === Schedule::DRIVER_STAGE_AT_PICKUP) {
                $this->sendToGuestBooking(
                    $booking,
                    'guest.driver_at_pickup',
                    'Tài xế đã đến',
                    'Tài xế đang chờ bạn tại điểm đón.',
                    '/chuyen',
                    'guest:booking:' . $booking->id . ':at_pickup',
                );
            } elseif (in_array($stage, [Schedule::DRIVER_STAGE_PICKED_UP, Schedule::DRIVER_STAGE_RUNNING], true)) {
                $this->sendToGuestBooking(
                    $booking,
                    'guest.passenger_picked_up',
                    'Đang trong chuyến',
                    'Tài xế đã đón bạn — theo dõi hành trình trên ứng dụng.',
                    '/chuyen',
                    'guest:booking:' . $booking->id . ':picked_up',
                );
            }
        }
    }

    public function onDriverEnRoute(Booking $booking, ?string $distanceLabel = null): void
    {
        $body = $distanceLabel
            ? 'Tài xế cách bạn ' . $distanceLabel . '.'
            : 'Tài xế đang di chuyển tới điểm đón.';

        $this->sendToGuestBooking(
            $booking,
            'guest.driver_en_route',
            'Tài xế đang tới',
            $body,
            '/chuyen',
            'guest:booking:' . $booking->id . ':en_route',
        );
    }

    public function onTripCompleted(Booking $booking): void
    {
        $this->sendToGuestBooking(
            $booking,
            'guest.trip_completed',
            'Chuyến hoàn tất',
            'Cảm ơn bạn đã đi cùng ' . AppBrandingSettings::appName() . '. Hãy đánh giá chuyến đi.',
            '/chuyen',
            'guest:booking:' . $booking->id . ':completed',
        );
    }

    public function onTripCancelled(Booking $booking, ?string $reason = null, ?int $driverUserId = null): void
    {
        $body = 'Chuyến ' . ($booking->booking_reference ?? '#' . $booking->id) . ' đã hủy.';
        if ($reason) {
            $body .= ' ' . $reason;
        }

        $this->sendToGuestBooking(
            $booking,
            'guest.trip_cancelled',
            'Chuyến đã hủy',
            $body,
            '/chuyen',
            'guest:booking:' . $booking->id . ':cancelled',
        );

        // assigned_driver_id có thể đã clear — ưu tiên id truyền vào / property tạm / schedule.
        $driverId = (int) ($driverUserId
            ?: ($booking->cancelNotifyDriverId ?? 0)
            ?: $booking->assigned_driver_id
            ?: 0);
        if ($driverId < 1) {
            $booking->loadMissing('schedule');
            $driverId = (int) ($booking->schedule?->driver_id ?? 0);
        }
        if ($driverId > 0) {
            $this->sendToDriver(
                $driverId,
                'driver.trip_cancelled',
                'Chuyến bị hủy',
                'Chuyến ' . ($booking->booking_reference ?? '#' . $booking->id) . ' đã bị hủy.',
                '/driver/dashboard',
                'driver:booking:' . $booking->id . ':cancelled',
            );
        }
    }

    public function onNoDriverFound(Booking $booking): void
    {
        $reason = (string) ($booking->cancellation_reason_label ?? '');
        $body = str_contains($reason, '1 tiếng') || str_contains($reason, 'đặt lịch')
            ? 'Không tìm được tài xế trước giờ đón 1 tiếng. Chuyến đã hủy — bạn có thể đặt lại.'
            : (str_contains($reason, '10 phút')
                ? 'Không tìm được tài xế trong 10 phút. Chuyến đã hủy — bạn có thể đặt lại.'
                : 'Hệ thống chưa tìm được tài xế phù hợp. Chuyến đã hủy — bạn có thể đặt lại.');

        $this->sendToGuestBooking(
            $booking,
            'guest.no_driver_found',
            'Không tìm được tài xế',
            $body,
            '/',
            'guest:booking:' . $booking->id . ':no_driver',
        );
    }

  protected function sendToGuestBooking(
        Booking $booking,
        string $eventKey,
        string $title,
        string $body,
        string $url,
        string $dedupKey,
    ): void {
        $phone = $this->normalizePhone((string) $booking->contact_phone);
        $subscriptions = PushSubscription::query()
            ->where('audience', PushAudience::GUEST)
            ->where(function ($query) use ($phone): void {
                if ($phone !== '') {
                    $query->where('contact_phone', $phone);
                } else {
                    $query->whereRaw('0 = 1');
                }
            })
            ->get();

        $this->dispatch($subscriptions, $eventKey, $title, $body, $url, $dedupKey);
    }

    /**
     * Gửi Web Push tới tài xế (dùng chung cho cuốc + hộp thư).
     *
     * @param  array<string, mixed>  $extraData
     */
    public function notifyDriverUser(
        int $driverUserId,
        string $eventKey,
        string $title,
        string $body,
        string $url,
        string $dedupKey,
        array $extraData = [],
    ): void {
        $this->sendToDriver($driverUserId, $eventKey, $title, $body, $url, $dedupKey, $extraData);
    }

    protected function sendToDriver(
        int $driverUserId,
        string $eventKey,
        string $title,
        string $body,
        string $url,
        string $dedupKey,
        array $extraData = [],
    ): void {
        // Đã lưu hộp thư trước (có inbox_id) hoặc event inbox_* — không tạo tin trùng.
        $inboxAlreadyStored = str_starts_with($eventKey, 'driver.inbox_')
            || ! empty($extraData['inbox_id']);

        if (! $inboxAlreadyStored && $this->claimDedup('inbox:' . $dedupKey)) {
            try {
                app(DriverInboxService::class)->storeNoticeWithoutPush(
                    $driverUserId,
                    $title,
                    $body,
                    [
                        'type'      => 'push_event',
                        'event_key' => $eventKey,
                        'dedup_key' => $dedupKey,
                    ],
                );
            } catch (\Throwable $e) {
                Log::warning('driver_inbox_store_failed', [
                    'driver_user_id' => $driverUserId,
                    'event'          => $eventKey,
                    'error'          => $e->getMessage(),
                ]);
            }
        }

        if (! isset($extraData['unread_total'])) {
            try {
                $extraData['unread_total'] = app(DriverSystemNotificationService::class)
                    ->unreadBadgeTotal($driverUserId);
            } catch (\Throwable) {
            }
        }

        $subscriptions = PushSubscription::query()
            ->where('audience', PushAudience::DRIVER)
            ->where('user_id', $driverUserId)
            ->get();

        $this->dispatch($subscriptions, $eventKey, $title, $body, $url, $dedupKey, $extraData);
    }

    /** @param \Illuminate\Support\Collection<int, PushSubscription> $subscriptions */
    protected function dispatch(
        $subscriptions,
        string $eventKey,
        string $title,
        string $body,
        string $url,
        string $dedupKey,
        array $extraData = [],
    ): void {
        if (! PushNotificationSettings::isEventEnabled($eventKey)) {
            return;
        }

        if ($subscriptions->isEmpty()) {
            return;
        }

        if (! $this->claimDedup($dedupKey)) {
            return;
        }

        $vapid = PushNotificationSettings::vapidKeys();
        if (! $vapid) {
            return;
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject'    => $vapid['subject'],
                    'publicKey'  => $vapid['public'],
                    'privateKey' => $vapid['private'],
                ],
            ]);

            $unreadTotal = isset($extraData['unread_total'])
                ? (int) $extraData['unread_total']
                : null;

            $payload = json_encode(array_filter([
                'title'         => $title,
                'body'          => $body,
                'url'           => url($url),
                'tag'           => $dedupKey,
                'icon'          => asset('favicon.svg'),
                'unread_total'  => $unreadTotal,
                'client_event'  => $extraData['client_event'] ?? null,
                'event_key'     => $eventKey,
                'data'          => array_merge($extraData, [
                    'url'       => url($url),
                    'event_key' => $eventKey,
                ]),
            ], static fn ($v) => $v !== null), JSON_UNESCAPED_UNICODE);

            foreach ($subscriptions as $row) {
                $subscription = Subscription::create([
                    'endpoint' => $row->endpoint,
                    'keys'     => [
                        'p256dh' => $row->public_key,
                        'auth'   => $row->auth_token,
                    ],
                ]);
                $webPush->queueNotification($subscription, $payload, ['TTL' => 3600]);
            }

            foreach ($webPush->flush() as $report) {
                if ($report->isSuccess()) {
                    continue;
                }

                $endpoint = $report->getEndpoint();
                if ($endpoint) {
                    PushSubscription::query()->where('endpoint_hash', hash('sha256', $endpoint))->delete();
                }
            }
        } catch (\Throwable $e) {
            Log::warning('push_notification_failed', [
                'event' => $eventKey,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function claimDedup(string $dedupKey): bool
    {
        try {
            return (bool) DB::table('push_notification_dedup')->insert([
                'dedup_key' => Str::limit($dedupKey, 191, ''),
                'sent_at'   => now(),
            ]);
        } catch (\Throwable) {
            return false;
        }
    }

    protected function normalizePhone(string $phone): string
    {
        return AuthIdentifier::normalizePhone($phone);
    }
}
