<?php

namespace App\Services;

use App\Models\DriverProfile;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class DriverPhotoService
{
    /** Khớp với upload_max_filesize PHP (thường 2M) */
    private const MAX_KB = 2048;

    private const ALLOWED_MIMES = ['jpeg', 'jpg', 'png', 'webp'];

    private const SINGLE_FIELDS = ['photo_portrait', 'photo_id_card', 'photo_id_card_back'];

    public function syncPhotos(DriverProfile $profile, Request $request): bool
    {
        $this->assertUploadsValid($request);

        $updates = [];
        $dir = 'drivers/' . $profile->id;
        Storage::disk('public')->makeDirectory($dir);

        foreach (self::SINGLE_FIELDS as $field) {
            $file = $request->file($field);
            if ($file instanceof UploadedFile) {
                if ($profile->{$field}) {
                    Storage::disk('public')->delete($profile->{$field});
                }
                $updates[$field] = $file->store($dir, 'public');
            }
        }

        $existing = $profile->photo_vehicles ?? [];
        $vehicleChanged = false;

        if ($request->filled('delete_vehicle_idx')) {
            $idx = (int) $request->input('delete_vehicle_idx');
            if (isset($existing[$idx])) {
                Storage::disk('public')->delete($existing[$idx]);
                array_splice($existing, $idx, 1);
                $vehicleChanged = true;
            }
        }

        $vehicleFiles = $this->validVehicleFiles($request);
        foreach ($vehicleFiles as $file) {
            $existing[] = $file->store($dir, 'public');
            $vehicleChanged = true;
        }

        if ($vehicleChanged) {
            $updates['photo_vehicles'] = array_values($existing);
        }

        if ($updates === []) {
            throw new InvalidArgumentException('Vui lòng chọn ít nhất một ảnh để upload hoặc chọn ảnh cần xóa.');
        }

        $profile->update($updates);

        return true;
    }

    /** @return list<UploadedFile> */
    private function validVehicleFiles(Request $request): array
    {
        if (! $request->hasFile('photo_vehicles')) {
            return [];
        }

        $files = $request->file('photo_vehicles');

        if (! is_array($files)) {
            $files = [$files];
        }

        return array_values(array_filter(
            $files,
            fn ($file) => $file instanceof UploadedFile
        ));
    }

    private function assertUploadsValid(Request $request): void
    {
        $rules = [];
        $attributes = [
            'photo_portrait'     => 'ảnh chân dung',
            'photo_id_card'      => 'ảnh CCCD mặt trước',
            'photo_id_card_back' => 'ảnh CCCD mặt sau',
        ];

        foreach (self::SINGLE_FIELDS as $field) {
            if ($request->hasFile($field)) {
                $file = $request->file($field);
                $this->assertFileReceived($file, $field, $attributes[$field] ?? $field);

                $rules[$field] = ['file', 'mimes:' . implode(',', self::ALLOWED_MIMES), 'max:' . self::MAX_KB];
            }
        }

        $vehicleFiles = $this->validVehicleFiles($request);
        foreach ($vehicleFiles as $index => $file) {
            $key = "photo_vehicles.{$index}";
            $this->assertFileReceived($file, $key, 'ảnh xe #' . ($index + 1));
            $rules[$key] = ['file', 'mimes:' . implode(',', self::ALLOWED_MIMES), 'max:' . self::MAX_KB];
        }

        if ($rules === [] && ! $request->filled('delete_vehicle_idx')) {
            throw new InvalidArgumentException('Vui lòng chọn ít nhất một ảnh để upload hoặc chọn ảnh cần xóa.');
        }

        if ($rules !== []) {
            Validator::make($request->all(), $rules, [
                'max'   => ':attribute không được lớn hơn 2MB.',
                'mimes' => ':attribute phải là JPG, PNG hoặc WebP.',
                'file'  => ':attribute không hợp lệ.',
            ], array_merge($attributes, [
                'photo_vehicles.*' => 'ảnh xe',
            ]))->validate();
        }
    }

    private function assertFileReceived(?UploadedFile $file, string $field, string $label): void
    {
        if (! $file instanceof UploadedFile) {
            return;
        }

        if ($file->isValid()) {
            return;
        }

        throw ValidationException::withMessages([
            $field => $this->uploadErrorMessage($file, $label),
        ]);
    }

    private function uploadErrorMessage(UploadedFile $file, string $label): string
    {
        return match ($file->getError()) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
                "{$label} quá lớn. Mỗi ảnh tối đa 2MB — hãy nén ảnh hoặc chọn file nhỏ hơn.",
            UPLOAD_ERR_PARTIAL =>
                "{$label} upload không hoàn tất. Vui lòng thử lại.",
            UPLOAD_ERR_NO_FILE =>
                "Chưa chọn {$label}.",
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION =>
                "Không ghi được {$label} lên server. Liên hệ quản trị viên.",
            default =>
                "Không upload được {$label}. Mã lỗi: {$file->getError()}.",
        };
    }

    public static function publicUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }
}
