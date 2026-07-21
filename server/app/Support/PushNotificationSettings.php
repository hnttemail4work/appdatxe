<?php

namespace App\Support;

use App\Models\PlatformSetting;

class PushNotificationSettings
{
    public const SETTING_KEY = 'push_notifications';

    public const VAPID_KEY = 'pwa_vapid';

    /** @return array<string, bool> */
    public static function defaultEvents(): array
    {
        return [
            'guest.driver_accepted'     => true,
            'guest.driver_en_route'     => true,
            'guest.driver_at_pickup'    => true,
            'guest.passenger_picked_up' => true,
            'guest.trip_completed'      => true,
            'guest.trip_cancelled'      => true,
            'guest.no_driver_found'     => true,
            'driver.new_trip_request'   => true,
            'driver.trip_cancelled'     => true,
            'driver.depart_reminder'    => true,
            'driver.pickup_urgent'      => true,
            'driver.late_pickup'        => true,
            'driver.movement_deadline'  => true,
            'driver.inbox_info'         => true,
            'driver.inbox_notice'       => true,
            'driver.nearby_search_nudge' => true,
        ];
    }

    /** @return array{enabled: bool, events: array<string, bool>} */
    public static function forAdmin(): array
    {
        $stored = PlatformSetting::getValue(self::SETTING_KEY, []);
        $events = is_array($stored['events'] ?? null) ? $stored['events'] : [];

        return [
            'enabled' => (bool) ($stored['enabled'] ?? true),
            'events'  => array_merge(self::defaultEvents(), $events),
        ];
    }

    public static function isEnabled(): bool
    {
        $stored = PlatformSetting::getValue(self::SETTING_KEY, []);

        return (bool) ($stored['enabled'] ?? true);
    }

    public static function isEventEnabled(string $eventKey): bool
    {
        if (! self::isEnabled()) {
            return false;
        }

        $events = self::forAdmin()['events'];

        return (bool) ($events[$eventKey] ?? false);
    }

    /** @param array<string, mixed> $validated */
    public static function saveFromAdmin(array $validated): void
    {
        $events = self::defaultEvents();
        foreach (array_keys($events) as $key) {
            $events[$key] = in_array($key, $validated['events'] ?? [], true);
        }

        PlatformSetting::setValue(self::SETTING_KEY, [
            'enabled' => (bool) ($validated['enabled'] ?? false),
            'events'  => $events,
        ], 'notifications');

        $public = trim((string) ($validated['vapid_public'] ?? ''));
        $private = trim((string) ($validated['vapid_private'] ?? ''));
        if ($public !== '' && $private !== '') {
            self::saveVapidKeys([
                'public'  => $public,
                'private' => $private,
                'subject' => trim((string) ($validated['vapid_subject'] ?? config('mail.from.address', 'mailto:admin@gozviet.local'))),
            ]);
        }
    }

    /** @return array{public: string, private: string, subject: string}|null */
    public static function vapidKeys(): ?array
    {
        $envPublic = trim((string) env('VAPID_PUBLIC_KEY', ''));
        $envPrivate = trim((string) env('VAPID_PRIVATE_KEY', ''));
        if ($envPublic !== '' && $envPrivate !== '') {
            return [
                'public'  => $envPublic,
                'private' => $envPrivate,
                'subject' => trim((string) env('VAPID_SUBJECT', config('mail.from.address', 'mailto:admin@gozviet.local'))),
            ];
        }

        $stored = PlatformSetting::getValue(self::VAPID_KEY, []);
        if (! is_array($stored)) {
            return null;
        }

        $public = trim((string) ($stored['public'] ?? ''));
        $private = trim((string) ($stored['private'] ?? ''));

        if ($public === '' || $private === '') {
            return null;
        }

        return [
            'public'  => $public,
            'private' => $private,
            'subject' => trim((string) ($stored['subject'] ?? config('mail.from.address', 'mailto:admin@gozviet.local'))),
        ];
    }

    /** @param array{public: string, private: string, subject?: string} $keys */
    public static function saveVapidKeys(array $keys): void
    {
        PlatformSetting::setValue(self::VAPID_KEY, [
            'public'  => $keys['public'],
            'private' => $keys['private'],
            'subject' => $keys['subject'] ?? 'mailto:admin@gozviet.local',
        ], 'notifications');
    }

    public static function eventLabels(): array
    {
        return [
            'guest.driver_accepted'     => 'Khách — Tài xế nhận chuyến',
            'guest.driver_en_route'     => 'Khách — Tài xế đang tới',
            'guest.driver_at_pickup'    => 'Khách — Tài xế đã đến điểm đón',
            'guest.passenger_picked_up' => 'Khách — Đã đón, bắt đầu chuyến',
            'guest.trip_completed'      => 'Khách — Thông tin chuyến (hoàn tất)',
            'guest.trip_cancelled'      => 'Khách — Thông tin chuyến (hủy)',
            'guest.no_driver_found'     => 'Khách — Không ghép được tài xế',
            'driver.new_trip_request'   => 'Tài xế — Cuốc mới chờ nhận',
            'driver.trip_cancelled'     => 'Tài xế — Chuyến bị hủy',
            'driver.depart_reminder'    => 'Tài xế — Nhắc xuất phát đón khách',
            'driver.pickup_urgent'      => 'Tài xế — Sát giờ đón, mở app',
            'driver.late_pickup'        => 'Tài xế — Đã đến giờ đón',
            'driver.movement_deadline'  => 'Tài xế — Hạn bấm Đến điểm đón',
            'driver.inbox_info'          => 'Tài xế — Thông tin (hộp thư)',
            'driver.inbox_notice'        => 'Tài xế — Thông báo (hộp thư)',
            'driver.nearby_search_nudge' => 'Tài xế — Khách gần đang tìm (tắt app)',
        ];
    }
}
