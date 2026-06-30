<?php

namespace App\Services;

use App\Models\PlatformSetting;
use App\Support\CustomerBookingBanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CustomerBookingBannerService
{
    private const BANNER_DIR = 'banners/booking';

    private const BANNER_MAX_WIDTH = 1400;

    public function __construct(
        private readonly ImageCompressService $compress,
    ) {
    }

    public function save(UploadedFile $file): void
    {
        $this->deleteStoredFile();

        $path = $this->compress->storeOptimized(
            $file,
            self::BANNER_DIR,
            'hero-' . now()->format('YmdHis'),
            self::BANNER_MAX_WIDTH,
        );

        PlatformSetting::setValue(CustomerBookingBanner::SETTING_KEY, [
            'image_path' => $path,
        ], 'branding');
    }

    public function remove(): void
    {
        $this->deleteStoredFile();

        PlatformSetting::setValue(CustomerBookingBanner::SETTING_KEY, [
            'image_path' => null,
        ], 'branding');
    }

    private function deleteStoredFile(): void
    {
        $path = CustomerBookingBanner::imagePath();
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
