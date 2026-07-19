<?php

namespace App\Support;

use App\Rules\UniqueNormalizedPhone;
use Illuminate\Validation\Rule;
use Closure;

/** Quy tắc validation thống nhất cho hồ sơ tài xế trên toàn hệ thống. */
class DriverFieldRules
{
    /** @return list<string> */
    public static function requiredFieldsFor(string $context): array
    {
        // Đăng ký: không nhập họ tên (admin scan CCCD khi duyệt); SĐT + PIN 6 số bắt buộc.
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

        if ($context === 'register') {
            $phoneRules = AuthPhone::rules(unique: true, ignoreUserId: $userId);
        } elseif ($userId !== null && self::isRequired($context, 'phone')) {
            $phoneRules = AuthPhone::rules(unique: true, ignoreUserId: $userId);
        } elseif ($userId !== null) {
            $phoneRules = [
                'nullable',
                'string',
                'max:30',
                function (string $attribute, mixed $value, Closure $fail) use ($phoneUnique): void {
                    if ($value === null || trim((string) $value) === '') {
                        return;
                    }
                    if (! AuthPhone::isValid((string) $value)) {
                        $fail(AuthMessages::PHONE_INVALID);

                        return;
                    }
                    $phoneUnique->validate($attribute, $value, $fail);
                },
            ];
        } else {
            $phoneRules = [$req('phone'), 'string', 'max:30'];
        }

        $passwordRules = $context === 'register'
            ? PinPassword::rules(confirmed: true)
            : ['nullable', 'string', 'digits:'.PinPassword::LENGTH];

        $passwordConfirmRules = $context === 'register'
            ? ['required', 'string', 'digits:'.PinPassword::LENGTH]
            : ['nullable'];

        return [
            'name'          => [$req('name'), 'string', 'max:255'],
            'email'         => $emailRules,
            'phone'         => $phoneRules,
            'password'      => $passwordRules,
            'password_confirmation' => $passwordConfirmRules,
            'id_number'     => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'address'       => ['nullable', 'string', 'max:500'],
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

    /** @return list<string> */
    public static function imageMimes(): array
    {
        return ['jpeg', 'jpg', 'png', 'webp'];
    }

    /** @return array<string, mixed> — CCCD trước/sau (dùng chung đăng ký TX / khách) */
    public static function idCardPhotoRules(bool $required = true): array
    {
        $rule = [$required ? 'required' : 'nullable', 'file', 'mimes:' . implode(',', self::imageMimes())];

        return [
            'photo_id_card'      => $rule,
            'photo_id_card_back' => $rule,
        ];
    }

    /** @return array<string, mixed> */
    public static function registrationPhotoRules(): array
    {
        $imageRule = ['required', 'file', 'mimes:' . implode(',', self::imageMimes())];

        return array_merge(self::idCardPhotoRules(true), [
            'photo_portrait'      => $imageRule,
            'photo_license_front' => $imageRule,
            'photo_license_back'  => ['nullable', 'file', 'mimes:' . implode(',', self::imageMimes())],
            'photo_vehicles'      => ['required', 'array', 'min:1'],
            'photo_vehicles.*'    => ['required', 'file', 'mimes:' . implode(',', self::imageMimes())],
        ]);
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
            $rules['phone'] = [
                'nullable',
                'string',
                'max:30',
                function (string $attribute, mixed $value, Closure $fail) use ($userId): void {
                    if ($value === null || trim((string) $value) === '') {
                        return;
                    }
                    if (! AuthPhone::isValid((string) $value)) {
                        $fail(AuthMessages::PHONE_INVALID);

                        return;
                    }
                    (new UniqueNormalizedPhone($userId))->validate($attribute, $value, $fail);
                },
            ];
        }

        return $rules;
    }
}
