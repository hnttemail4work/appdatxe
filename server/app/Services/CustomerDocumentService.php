<?php

namespace App\Services;

use App\Models\User;
use App\Support\DriverFieldRules;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Lưu ảnh CCCD khách hàng trên `users` — tái dùng rule MIME của DriverFieldRules. */
class CustomerDocumentService
{
    /** @return list<string> */
    public static function idCardFields(): array
    {
        return ['photo_id_card', 'photo_id_card_back'];
    }

    public function storeRegistrationPhotos(User $user, Request $request): void
    {
        $updates = $this->storeIdCardFiles($user, $request, required: true);
        if ($updates !== []) {
            $user->forceFill($updates)->save();
        }
    }

    /**
     * @return array<string, string> field => relative path (pending hoặc live)
     */
    public function storePendingIdCardPhotos(User $user, Request $request): array
    {
        $dir = 'customers/' . $user->id . '/pending';
        Storage::disk('public')->makeDirectory($dir);

        $stored = [];
        foreach (self::idCardFields() as $field) {
            $file = $request->file($field);
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $this->assertValidImage($file, $field);
            $stored[$field] = $file->store($dir, 'public');
        }

        return $stored;
    }

    /**
     * @return array<string, string>
     */
    public function storeIdCardFiles(User $user, Request $request, bool $required = false): array
    {
        $rules = DriverFieldRules::idCardPhotoRules($required);
        Validator::make($request->all(), $rules, [
            'required' => 'Vui lòng chọn :attribute.',
            'mimes'    => ':attribute phải là JPG, PNG hoặc WebP.',
            'file'     => ':attribute không hợp lệ.',
        ], [
            'photo_id_card'      => 'ảnh CCCD mặt trước',
            'photo_id_card_back' => 'ảnh CCCD mặt sau',
        ])->validate();

        $dir = 'customers/' . $user->id;
        Storage::disk('public')->makeDirectory($dir);

        $updates = [];
        foreach (self::idCardFields() as $field) {
            $file = $request->file($field);
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $this->assertValidImage($file, $field);
            if (is_string($user->{$field}) && $user->{$field} !== '') {
                Storage::disk('public')->delete($user->{$field});
            }
            $updates[$field] = $file->store($dir, 'public');
        }

        return $updates;
    }

    public function photoUrl(?string $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    private function assertValidImage(UploadedFile $file, string $field): void
    {
        if ($file->isValid()) {
            return;
        }

        $label = $field === 'photo_id_card_back' ? 'ảnh CCCD mặt sau' : 'ảnh CCCD mặt trước';

        throw ValidationException::withMessages([
            $field => match ($file->getError()) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
                    "{$label} quá lớn so với giới hạn upload của server.",
                UPLOAD_ERR_PARTIAL =>
                    "{$label} upload không hoàn tất. Vui lòng thử lại.",
                UPLOAD_ERR_NO_FILE =>
                    "Chưa chọn {$label}.",
                default =>
                    "Không tải được {$label}. Vui lòng thử lại.",
            },
        ]);
    }
}
