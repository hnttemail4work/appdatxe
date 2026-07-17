<?php

namespace App\Support;

use App\Rules\UniqueNormalizedPhone;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Quy tắc validation thống nhất cho hồ sơ tài xế trên toàn hệ thống. */
class DriverFieldRules
{
    /** @return list<string> */
    public static function requiredFieldsFor(string $context): array
    {
        // Đăng ký: họ tên tùy chọn; SĐT + mật khẩu bắt buộc.
        $contact = $context === 'register' ? ['phone'] : ['name', 'phone'];
        $account = $context === 'register' ? ['password', 'password_confirmation'] : [];
        $vehicle = ['vehicle_license_plate', 'vehicle_type'];
        $bank = ['bank_name', 'bank_account'];

        return match ($context) {
            'register' => array_merge($contact, $account, $vehicle, $bank),
            'operator' => $bank,
            default    => [],
        };
    }

    public static function isRequired(string $context, string $field): bool
    {
        return in_array($field, self::requiredFieldsFor($context), true);
    }

    /** @return list<string> */
    public static function licenseClasses(): array
    {
        return ['B1', 'B2', 'C', 'D', 'E', 'F'];
    }

    /** @return list<string> */
    public static function vehicleTypes(): array
    {
        return DriverVehicleOptions::allowedKeys();
    }

    /** @return array<string, mixed> */
    public static function userFields(?int $userId = null, string $context = 'optional'): array
    {
        $emailUnique = Rule::unique('users', 'email');
        if ($userId) {
            $emailUnique->ignore($userId);
        }

        $phoneUnique = new UniqueNormalizedPhone($userId);

        $req = fn (string $field) => self::isRequired($context, $field) ? 'required' : 'nullable';

        $emailRules = ['nullable', 'email', 'max:255', $emailUnique];
        if (self::isRequired($context, 'email')) {
            $emailRules[0] = 'required';
        }

        $phoneRules = [$req('phone'), 'string', 'max:30'];
        if ($context === 'register' || $userId !== null) {
            $phoneRules[] = $phoneUnique;
        }

        $passwordRules = $context === 'register'
            ? ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()]
            : ['nullable', 'string', 'min:8'];

        $passwordConfirmRules = $context === 'register'
            ? ['required', 'string']
            : ['nullable'];

        return [
            'name'          => [$req('name'), 'string', 'max:255'],
            'email'         => $emailRules,
            'phone'         => $phoneRules,
            'password'      => $passwordRules,
            'password_confirmation' => $passwordConfirmRules,
            'id_number'     => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'address'       => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, mixed> */
    public static function profileFields(?int $profileId = null, string $context = 'optional'): array
    {
        $licenseUnique = Rule::unique('driver_profiles', 'license_number');
        if ($profileId) {
            $licenseUnique->ignore($profileId);
        }

        $req = fn (string $field) => self::isRequired($context, $field) ? 'required' : 'nullable';

        return [
            'license_number'          => ['nullable', 'string', 'max:50', $licenseUnique],
            'license_class'           => ['nullable', Rule::in(self::licenseClasses())],
            'license_expiry'          => ['nullable', 'date'],
            'experience_years'        => ['nullable', 'integer', 'min:0', 'max:50'],
            'notes'                   => ['nullable', 'string', 'max:1000'],
            'bank_name'               => ['nullable', 'string', 'max:100'],
            'bank_account'            => ['nullable', 'string', 'max:50'],
            'vehicle_license_plate'   => [$req('vehicle_license_plate'), 'string', 'max:20'],
            'vehicle_type'            => [$req('vehicle_type'), Rule::in(self::vehicleTypes())],
            'vehicle_brand'           => ['nullable', 'string', 'max:100'],
            'vehicle_model'           => ['nullable', 'string', 'max:100'],
            'vehicle_color'           => ['nullable', 'string', 'max:50'],
            // Số chỗ suy ra từ loại xe khi đăng ký; admin vẫn có thể gửi kèm.
            'vehicle_seats'           => ['nullable', 'integer', 'min:0', 'max:50'],
        ];
    }

    /** @return array<string, mixed> */
    public static function registrationPhotoRules(): array
    {
        $imageRule = ['required', 'file', 'mimes:jpeg,jpg,png,webp'];

        return [
            'photo_portrait'      => $imageRule,
            'photo_id_card'       => $imageRule,
            'photo_id_card_back'  => $imageRule,
            'photo_license_front' => $imageRule,
            'photo_license_back'  => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp'],
            'photo_vehicles'      => ['required', 'array', 'min:1'],
            'photo_vehicles.*'    => ['required', 'file', 'mimes:jpeg,jpg,png,webp'],
        ];
    }

    /** @return array<string, mixed> */
    public static function registrationRules(): array
    {
        return array_merge(
            self::userFields(null, 'register'),
            self::profileFields(null, 'register'),
            self::registrationPhotoRules(),
            ['terms' => ['accepted']],
        );
    }

    /** @return array<string, mixed> — admin/QL sửa tài xế, không bắt buộc đủ mọi field */
    public static function operatorUpdateRules(int $userId, int $profileId, bool $contactLocked = false): array
    {
        $rules = array_merge(
            self::userFields($userId, 'optional'),
            self::profileFields($profileId, 'operator'),
        );

        if ($contactLocked) {
            $rules['name'] = ['nullable', 'string', 'max:255'];
            $rules['phone'] = ['nullable', 'string', 'max:30', new UniqueNormalizedPhone($userId)];
        }

        return $rules;
    }
}
