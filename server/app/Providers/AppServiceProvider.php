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

        $this->ensurePublicStorageLink();
    }

    private function ensurePublicStorageLink(): void
    {
        $link = public_path('storage');
        $target = storage_path('app/public');

        if ($this->publicStorageLinkIsHealthy($link, $target)) {
            return;
        }

        if (is_dir($link) && ! $this->publicStorageLinkIsHealthy($link, $target) && $this->directoryIsEmpty($link)) {
            try {
                @rmdir($link);
            } catch (\Throwable) {
            }
        }

        if (! file_exists($link)) {
            try {
                \Illuminate\Support\Facades\Artisan::call('storage:link');
            } catch (\Throwable) {
            }
        }
    }

    private function publicStorageLinkIsHealthy(string $link, string $target): bool
    {
        if (! file_exists($link)) {
            return false;
        }

        if (is_link($link)) {
            return realpath((string) readlink($link)) === realpath($target);
        }

        if (PHP_OS_FAMILY === 'Windows' && is_dir($link)) {
            $probe = $target . DIRECTORY_SEPARATOR . '.storage-link-probe';
            $sentinel = $link . DIRECTORY_SEPARATOR . '.storage-link-probe';

            try {
                if (! file_exists($probe)) {
                    file_put_contents($probe, '1');
                }

                return file_exists($sentinel);
            } catch (\Throwable) {
                return false;
            }
        }

        return realpath($link) === realpath($target);
    }

    private function directoryIsEmpty(string $directory): bool
    {
        if (! is_dir($directory)) {
            return false;
        }

        $handle = opendir($directory);
        if ($handle === false) {
            return false;
        }

        try {
            while (($entry = readdir($handle)) !== false) {
                if ($entry !== '.' && $entry !== '..') {
                    return false;
                }
            }
        } finally {
            closedir($handle);
        }

        return true;
    }
}
