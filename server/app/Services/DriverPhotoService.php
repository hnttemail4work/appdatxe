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
    private const ALLOWED_MIMES = ['jpeg', 'jpg', 'png', 'webp'];

    private const SINGLE_FIELDS = [
        'photo_portrait',
        'photo_id_card',
        'photo_id_card_back',
        'photo_license_front',
        'photo_license_back',
    ];

    /** @return list<string> */
    public static function identityPhotoFields(): array
    {
        return DriverProfile::IDENTITY_PHOTO_FIELDS;
    }

    public function syncPhotos(DriverProfile $profile, Request $request, bool $lockIdentityPhotos = false): bool
    {
        if ($lockIdentityPhotos) {
            foreach (self::identityPhotoFields() as $field) {
                if ($request->hasFile($field)) {
                    throw new InvalidArgumentException(
                        'Ảnh CCCD, bằng lái và chân dung đã được duyệt — không thể thay đổi. Liên hệ quản lý nếu cần cập nhật.'
                    );
                }
            }
        }

        $hasFileChanges = $this->requestHasPhotoFileChanges($request);

        if ($hasFileChanges) {
            $this->assertUploadsValid($request, $lockIdentityPhotos);
        }

        $updates = [];
        $dir = 'drivers/' . $profile->id;
        Storage::disk('public')->makeDirectory($dir);

        foreach (self::SINGLE_FIELDS as $field) {
            if ($lockIdentityPhotos && in_array($field, self::identityPhotoFields(), true)) {
                continue;
            }

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
        $catalogIndex = $profile->catalogVehiclePhotoIndex();

        if ($request->filled('delete_vehicle_idx')) {
            $idx = (int) $request->input('delete_vehicle_idx');
            if (isset($existing[$idx])) {
                Storage::disk('public')->delete($existing[$idx]);
                array_splice($existing, $idx, 1);
                $vehicleChanged = true;

                if ($idx === $catalogIndex) {
                    $catalogIndex = 0;
                } elseif ($idx < $catalogIndex) {
                    $catalogIndex = max(0, $catalogIndex - 1);
                }
            }
        }

        $vehicleFiles = $hasFileChanges ? $this->validVehicleFiles($request) : [];
        foreach ($vehicleFiles as $file) {
            $existing[] = $file->store($dir, 'public');
            $vehicleChanged = true;
        }

        if ($vehicleChanged) {
            $updates['photo_vehicles'] = array_values($existing);
        }

        if ($request->has('catalog_vehicle_photo_index')) {
            $vehicleCount = count($updates['photo_vehicles'] ?? $profile->photo_vehicles ?? []);
            if ($vehicleCount > 0) {
                $requested = (int) $request->input('catalog_vehicle_photo_index');
                $updates['catalog_vehicle_photo_index'] = max(0, min($requested, $vehicleCount - 1));
            }
        } elseif ($vehicleChanged) {
            $vehicleCount = count($updates['photo_vehicles'] ?? []);
            if ($vehicleCount > 0) {
                $updates['catalog_vehicle_photo_index'] = max(0, min($catalogIndex, $vehicleCount - 1));
            } else {
                $updates['catalog_vehicle_photo_index'] = 0;
            }
        }

        if ($updates === []) {
            $msg = $lockIdentityPhotos
                ? 'Chỉ có thể thêm ảnh xe. Giấy tờ và chân dung đã khóa sau khi duyệt.'
                : 'Vui lòng chọn ít nhất một ảnh để upload hoặc chọn ảnh cần xóa.';

            throw new InvalidArgumentException($msg);
        }

        $profile->update($updates);

        return true;
    }

    private function requestHasPhotoFileChanges(Request $request): bool
    {
        if ($request->filled('delete_vehicle_idx')) {
            return true;
        }

        foreach (self::SINGLE_FIELDS as $field) {
            if ($request->hasFile($field)) {
                return true;
            }
        }

        return $this->validVehicleFiles($request) !== [];
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

    private function assertUploadsValid(Request $request, bool $lockIdentityPhotos = false): void
    {
        $rules = [];
        $attributes = [
            'photo_portrait'       => 'ảnh chân dung',
            'photo_id_card'        => 'ảnh CCCD mặt trước',
            'photo_id_card_back'   => 'ảnh CCCD mặt sau',
            'photo_license_front'  => 'ảnh bằng lái mặt trước',
            'photo_license_back'   => 'ảnh bằng lái mặt sau',
        ];

        foreach (self::SINGLE_FIELDS as $field) {
            if ($lockIdentityPhotos && in_array($field, self::identityPhotoFields(), true)) {
                continue;
            }

            if ($request->hasFile($field)) {
                $file = $request->file($field);
                $this->assertFileReceived($file, $field, $attributes[$field] ?? $field);

                $rules[$field] = ['file', 'mimes:' . implode(',', self::ALLOWED_MIMES)];
            }
        }

        $vehicleFiles = $this->validVehicleFiles($request);
        foreach ($vehicleFiles as $index => $file) {
            $key = "photo_vehicles.{$index}";
            $this->assertFileReceived($file, $key, 'ảnh xe #' . ($index + 1));
            $rules[$key] = ['file', 'mimes:' . implode(',', self::ALLOWED_MIMES)];
        }

        if ($rules === [] && ! $request->filled('delete_vehicle_idx')) {
            throw new InvalidArgumentException('Vui lòng chọn ít nhất một ảnh để upload hoặc chọn ảnh cần xóa.');
        }

        if ($rules !== []) {
            Validator::make($request->all(), $rules, [
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
                "{$label} quá lớn so với giới hạn upload của server. Liên hệ quản trị nếu cần hỗ trợ.",
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
        return \App\Support\PublicStorageUrl::url($path);
    }

    /** Lưu ảnh hồ sơ khi đăng ký tài xế (bắt buộc đủ ảnh). */
    public function storeRegistrationPhotos(DriverProfile $profile, \Illuminate\Http\Request $request): void
    {
        $required = ['photo_portrait', 'photo_id_card', 'photo_id_card_back', 'photo_license_front'];
        foreach ($required as $field) {
            if (! $request->hasFile($field)) {
                throw new InvalidArgumentException('Vui lòng upload đầy đủ ảnh hồ sơ và giấy tờ.');
            }
        }

        if ($this->validVehicleFiles($request) === []) {
            throw new InvalidArgumentException('Vui lòng upload ít nhất một ảnh xe.');
        }

        $this->syncPhotos($profile, $request);
    }
}
