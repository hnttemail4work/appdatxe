<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class PublicStorageUrl
{
    public static function url(?string $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $path = self::normalizeRelativePath($path);

        if ($path === null || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return route('storage.public', ['path' => $path], absolute: false);
    }

    private static function normalizeRelativePath(string $path): ?string
    {
        $path = trim(str_replace('\\', '/', $path));

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            if (preg_match('#/storage/(.+)$#', $path, $matches) !== 1) {
                return null;
            }

            $path = $matches[1];
        }

        $path = ltrim($path, '/');

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, 8);
        }

        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        return $path;
    }
}