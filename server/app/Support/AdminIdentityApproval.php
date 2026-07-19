<?php

namespace App\Support;

/** Rules + payload khi admin duyệt hồ sơ kèm thông tin CCCD. */
final class AdminIdentityApproval
{
    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'gender'        => ['required', 'in:male,female'],
            'id_number'     => ['nullable', 'string', 'max:20'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'name.required'          => 'Vui lòng nhập họ tên (scan CCCD hoặc nhập tay).',
            'date_of_birth.required' => 'Vui lòng nhập ngày sinh.',
            'date_of_birth.before'   => 'Ngày sinh không hợp lệ.',
            'gender.required'        => 'Vui lòng chọn giới tính.',
            'gender.in'              => 'Giới tính không hợp lệ.',
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{name: string, date_of_birth: string, gender: string, id_number: ?string}
     */
    public static function userAttributes(array $validated): array
    {
        $id = trim((string) ($validated['id_number'] ?? ''));

        return [
            'name'          => trim((string) $validated['name']),
            'date_of_birth' => (string) $validated['date_of_birth'],
            'gender'        => ($validated['gender'] ?? 'male') === 'female' ? 'female' : 'male',
            'id_number'     => $id !== '' ? $id : null,
        ];
    }
}
