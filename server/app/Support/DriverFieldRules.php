<?php

namespace App\Support;

use Illuminate\Validation\Rule;

/** Quy tắc validation thống nhất cho hồ sơ tài xế trên toàn hệ thống. */
class DriverFieldRules
{
    /** @return list<string> */
    public static function requiredFieldsFor(string $context): array
    {
        $contact = ['name', 'phone'];
        $vehicle = ['vehicle_license_plate', 'vehicle_type', 'vehicle_seats'];

        return match ($context) {
            'register', 'profile' => array_merge($contact, $vehicle),
            default               => [],
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
        return ['limousine', 'sedan', 'suv'];
    }

    /** @return array<string, mixed> */
    public static function userFields(?int $userId = null, string $context = 'optional'): array
    {
        $emailUnique = Rule::unique('users', 'email');
        if ($userId) {
            $emailUnique->ignore($userId);
        }

        $phoneUnique = Rule::unique('users', 'phone');
        if ($userId) {
            $phoneUnique->ignore($userId);
        }

        $req = fn (string $field) => self::isRequired($context, $field) ? 'required' : 'nullable';

        $emailRules = ['nullable', 'email', 'max:255', $emailUnique];
        if ($context === 'register' || $context === 'profile') {
            $emailRules[0] = 'nullable';
        } elseif (self::isRequired($context, 'email')) {
            $emailRules[0] = 'required';
        }

        $phoneRules = [$req('phone'), 'string', 'max:30'];
        if ($context === 'register') {
            $phoneRules[] = $phoneUnique;
        }

        return [
            'name'          => [$req('name'), 'string', 'max:255'],
            'email'         => $emailRules,
            'phone'         => $phoneRules,
            'password'      => ['nullable', 'string', 'min:8'],
            'password_confirmation' => ['nullable'],
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
            'vehicle_seats'           => [$req('vehicle_seats'), 'integer', 'min:4', 'max:50'],
        ];
    }

    /** @return array<string, mixed> */
    public static function registrationPhotoRules(): array
    {
        $imageRule = ['required', 'file', 'mimes:jpeg,jpg,png,webp', 'max:2048'];

        return [
            'photo_portrait'      => $imageRule,
            'photo_id_card'       => $imageRule,
            'photo_id_card_back'  => $imageRule,
            'photo_license_front' => $imageRule,
            'photo_license_back'  => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'photo_vehicles'      => ['required', 'array', 'min:1'],
            'photo_vehicles.*'    => ['required', 'file', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
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

    /** @return array<string, mixed> */
    public static function selfUpdateRules(int $userId, int $profileId): array
    {
        return array_merge(
            self::userFields($userId, 'profile'),
            self::profileFields($profileId, 'profile'),
        );
    }

    /** @return array<string, mixed> — admin/QL sửa tài xế, không bắt buộc đủ mọi field */
    public static function operatorUpdateRules(int $userId, int $profileId): array
    {
        return array_merge(
            self::userFields($userId, 'optional'),
            self::profileFields($profileId, 'optional'),
        );
    }
}
