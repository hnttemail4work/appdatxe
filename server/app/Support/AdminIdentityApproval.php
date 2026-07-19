<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Rules + payload khi admin duyệt hồ sơ kèm thông tin CCCD (khách / tài xế thống nhất). */
final class AdminIdentityApproval
{
    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return array_merge([
            'name'          => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'gender'        => ['required', 'in:male,female'],
            'id_number'     => ['required', 'string', 'regex:/^\d{9,12}$/', 'max:20'],
            'address'       => ['required', 'string', 'max:500'],
        ], self::photoRules());
    }

    /** Ảnh CCCD (khách + tài xế). */
    public static function idCardPhotoFields(): array
    {
        return ['photo_id_card', 'photo_id_card_back'];
    }

    /** Ảnh thêm khi duyệt tài xế (chân dung + bằng lái) — cùng xoay/cắt. */
    public static function driverExtraPhotoFields(): array
    {
        return ['photo_portrait', 'photo_license_front', 'photo_license_back'];
    }

    /** @return list<string> */
    public static function driverPhotoFields(): array
    {
        return array_merge(self::idCardPhotoFields(), self::driverExtraPhotoFields());
    }

    /**
     * Ảnh đã xoay/cắt từ panel duyệt (optional).
     *
     * @param  list<string>|null  $fields
     * @return array<string, mixed>
     */
    public static function photoRules(?array $fields = null): array
    {
        $fields ??= self::idCardPhotoFields();
        $rule = ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:10240'];
        $rules = [];
        foreach ($fields as $field) {
            $rules[$field] = $rule;
        }

        return $rules;
    }

    /** Rules duyệt tài xế: CCCD + chân dung + bằng lái. */
    public static function driverRules(): array
    {
        return array_merge([
            'name'          => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'gender'        => ['required', 'in:male,female'],
            'id_number'     => ['required', 'string', 'regex:/^\d{9,12}$/', 'max:20'],
            'address'       => ['required', 'string', 'max:500'],
        ], self::photoRules(self::driverPhotoFields()));
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'name.required'          => 'Vui lòng nhập họ và tên.',
            'name.max'               => 'Họ và tên tối đa 255 ký tự.',
            'date_of_birth.required' => 'Vui lòng nhập ngày tháng năm sinh.',
            'date_of_birth.date'     => 'Ngày sinh không hợp lệ.',
            'date_of_birth.before'   => 'Ngày sinh phải trước hôm nay.',
            'date_of_birth.after'    => 'Ngày sinh không hợp lệ.',
            'gender.required'        => 'Vui lòng chọn giới tính.',
            'gender.in'              => 'Giới tính không hợp lệ.',
            'id_number.required'     => 'Vui lòng nhập số CCCD.',
            'id_number.regex'        => 'Số CCCD phải gồm 9–12 chữ số.',
            'id_number.max'          => 'Số CCCD tối đa 20 ký tự.',
            'address.required'       => 'Vui lòng nhập địa chỉ.',
            'address.max'            => 'Địa chỉ tối đa 500 ký tự.',
            'photo_id_card.mimes'         => 'Ảnh CCCD mặt trước phải là JPG, PNG hoặc WebP.',
            'photo_id_card_back.mimes'    => 'Ảnh CCCD mặt sau phải là JPG, PNG hoặc WebP.',
            'photo_id_card.max'           => 'Ảnh CCCD mặt trước tối đa 10MB.',
            'photo_id_card_back.max'      => 'Ảnh CCCD mặt sau tối đa 10MB.',
            'photo_portrait.mimes'        => 'Ảnh chân dung phải là JPG, PNG hoặc WebP.',
            'photo_portrait.max'          => 'Ảnh chân dung tối đa 10MB.',
            'photo_license_front.mimes'   => 'Ảnh bằng lái mặt trước phải là JPG, PNG hoặc WebP.',
            'photo_license_front.max'     => 'Ảnh bằng lái mặt trước tối đa 10MB.',
            'photo_license_back.mimes'    => 'Ảnh bằng lái mặt sau phải là JPG, PNG hoặc WebP.',
            'photo_license_back.max'      => 'Ảnh bằng lái mặt sau tối đa 10MB.',
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{name: string, date_of_birth: string, gender: string, id_number: string, address: string}
     */
    public static function userAttributes(array $validated): array
    {
        $id = preg_replace('/\D+/', '', (string) ($validated['id_number'] ?? '')) ?: '';

        return [
            'name'          => trim((string) $validated['name']),
            'date_of_birth' => (string) $validated['date_of_birth'],
            'gender'        => ($validated['gender'] ?? 'male') === 'female' ? 'female' : 'male',
            'id_number'     => $id,
            'address'       => trim((string) ($validated['address'] ?? '')),
        ];
    }

    /**
     * Lưu ảnh đã chỉnh (xoay/cắt) từ form duyệt — dùng chung User / DriverProfile.
     *
     * @param  list<string>|null  $fields
     * @return array<string, string> field => path
     */
    public static function storeAdjustedIdCardPhotos(
        Request $request,
        Model $owner,
        string $directory,
        ?array $fields = null,
    ): array {
        $fields ??= self::idCardPhotoFields();
        $disk = Storage::disk('public');
        $disk->makeDirectory($directory);

        $updates = [];
        foreach ($fields as $field) {
            $file = $request->file($field);
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $old = $owner->{$field} ?? null;
            if (is_string($old) && $old !== '') {
                $disk->delete($old);
            }

            $updates[$field] = $file->store($directory, 'public');
        }

        return $updates;
    }
}
