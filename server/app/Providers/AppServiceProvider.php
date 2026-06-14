<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Đảm bảo ảnh upload hiển thị được (storage/app/public → public/storage)
        if (! file_exists(public_path('storage')) && $this->app->environment('local')) {
            try {
                \Illuminate\Support\Facades\Artisan::call('storage:link');
            } catch (\Throwable) {
                // Bỏ qua nếu không tạo được symlink — chạy thủ công: php artisan storage:link
            }
        }
    }
}
