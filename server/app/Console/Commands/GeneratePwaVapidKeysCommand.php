<?php

namespace App\Console\Commands;

use App\Support\PushNotificationSettings;
use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GeneratePwaVapidKeysCommand extends Command
{
    protected $signature = 'pwa:vapid-keys {--force : Ghi đè khóa hiện có}';

    protected $description = 'Tạo cặp khóa VAPID cho Web Push và lưu vào cài đặt nền tảng';

    public function handle(): int
    {
        if (PushNotificationSettings::vapidKeys() && ! $this->option('force')) {
            $this->warn('Đã có khóa VAPID. Dùng --force để tạo lại.');

            return self::SUCCESS;
        }

        try {
            $keys = VAPID::createVapidKeys();
        } catch (\Throwable $e) {
            $this->error('Không tạo được khóa trên máy này (OpenSSL EC).');
            $this->line('Trên server Linux chạy lại lệnh này, hoặc dán khóa VAPID trong Admin → Cài đặt → Thông báo đẩy.');
            $this->line('Hoặc đặt VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY trong file .env');

            return self::FAILURE;
        }

        PushNotificationSettings::saveVapidKeys([
            'public'  => $keys['publicKey'],
            'private' => $keys['privateKey'],
            'subject' => 'mailto:' . (config('mail.from.address') ?: 'admin@gozviet.local'),
        ]);

        $this->info('Đã lưu khóa VAPID mới.');
        $this->line('Public: ' . $keys['publicKey']);

        return self::SUCCESS;
    }
}
