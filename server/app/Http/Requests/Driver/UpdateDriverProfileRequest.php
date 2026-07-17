<?php

namespace App\Http\Requests\Driver;

use App\Models\DriverProfile;
use App\Support\DriverFieldRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDriverProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var DriverProfile $driverProfile */
        $driverProfile = $this->route('driverProfile');

        return DriverFieldRules::operatorUpdateRules(
            $driverProfile->user_id,
            $driverProfile->id,
            $driverProfile->contactFieldsLocked(),
        );
    }
}
