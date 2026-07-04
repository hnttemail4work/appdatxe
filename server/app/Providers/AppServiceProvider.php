<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole() && request()->hasHeader('Host')) {
            URL::forceRootUrl(request()->getSchemeAndHttpHost());
        }

        if ($this->app->environment('local')) {
            config([
                'session.secure' => false,
                'session.same_site' => 'lax',
            ]);
        }

        if (! file_exists(public_path('storage')) && $this->app->environment('local')) {
            try {
                \Illuminate\Support\Facades\Artisan::call('storage:link');
            } catch (\Throwable) {
            }
        }
    }
}
