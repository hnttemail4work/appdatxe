<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class ImageCompressService
{
    private const MAX_WIDTH = 640;

    private const JPEG_QUALITY = 78;

    /** @var list<string> */
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public function storeOptimized(UploadedFile $file, string $directory, string $basename = 'photo', ?int $maxWidth = null): string
    {
        $maxWidth = $maxWidth ?? self::MAX_WIDTH;

        if (! extension_loaded('gd')) {
            return $file->store($directory, 'public');
        }

        $sourcePath = $file->getRealPath();
        if ($sourcePath === false) {
            throw new InvalidArgumentException('Không đọc được file ảnh.');
        }

        $info = @getimagesize($sourcePath);
        if ($info === false || ! in_array($info['mime'] ?? '', self::ALLOWED_MIMES, true)) {
            throw new InvalidArgumentException('Ảnh phải là JPG, PNG, WebP hoặc GIF.');
        }

        $source = $this->loadImage($sourcePath, $info['mime']);
        if ($source === null) {
            return $file->store($directory, 'public');
        }

        [$srcW, $srcH] = $info;
        $targetW = min($srcW, $maxWidth);
        $targetH = (int) round($srcH * ($targetW / $srcW));

        $canvas = imagecreatetruecolor($targetW, $targetH);
        if ($canvas === false) {
            imagedestroy($source);

            return $file->store($directory, 'public');
        }

        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetW, $targetH, $srcW, $srcH);
        imagedestroy($source);

        Storage::disk('public')->makeDirectory($directory);
        $relativePath = trim($directory, '/') . '/' . $basename . '.jpg';
        $absolutePath = Storage::disk('public')->path($relativePath);

        imagejpeg($canvas, $absolutePath, self::JPEG_QUALITY);
        imagedestroy($canvas);

        return $relativePath;
    }

    /** @param resource|null */
    private function loadImage(string $path, string $mime)
    {
        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path) ?: null,
            'image/png'  => @imagecreatefrompng($path) ?: null,
            'image/webp' => function_exists('imagecreatefromwebp') ? (@imagecreatefromwebp($path) ?: null) : null,
            'image/gif'  => @imagecreatefromgif($path) ?: null,
            default      => null,
        };
    }
}
