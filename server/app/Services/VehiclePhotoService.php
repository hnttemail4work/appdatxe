<?php

namespace App\Services;

use App\Models\Vehicle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class VehiclePhotoService
{
    public function __construct(
        private readonly ImageCompressService $compress,
    ) {
    }

    public function storeForVehicle(Vehicle $vehicle, UploadedFile $file): string
    {
        if ($vehicle->photo_path) {
            Storage::disk('public')->delete($vehicle->photo_path);
        }

        $path = $this->compress->storeOptimized(
            $file,
            'vehicles/' . $vehicle->id,
            'cover',
        );

        $vehicle->update(['photo_path' => $path]);

        return $path;
    }

    public static function publicUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }
}
