<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommissionSettingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => [
                'commission_percentage' => data_get(PlatformSetting::getValue('commission_percentage', ['value' => 10]), 'value', 10),
                'deposit_percentage' => data_get(PlatformSetting::getValue('deposit_percentage', ['value' => 30]), 'value', 30),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'commission_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'deposit_percentage' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ]);

        PlatformSetting::setValue('commission_percentage', ['value' => (float) $validated['commission_percentage']], 'finance');

        if (array_key_exists('deposit_percentage', $validated)) {
            PlatformSetting::setValue('deposit_percentage', ['value' => (float) $validated['deposit_percentage']], 'finance');
        }

        return response()->json([
            'message' => 'Commission settings updated successfully.',
            'data' => [
                'commission_percentage' => (float) $validated['commission_percentage'],
                'deposit_percentage' => $validated['deposit_percentage'] ?? data_get(PlatformSetting::getValue('deposit_percentage', ['value' => 30]), 'value', 30),
            ],
        ]);
    }
}
<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommissionSettingController extends Controller
{
    public function show(): JsonResponse
    {
        $commissionSetting = PlatformSetting::getValue('commission_percentage', ['value' => 10]);

        return response()->json([
            'data' => [
                'commission_percentage' => $commissionSetting['value'] ?? 10,
                'deposit_percentage' => 30,
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'commission_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        PlatformSetting::setValue('commission_percentage', [
            'value' => (float) $validated['commission_percentage'],
        ], 'finance');

        return response()->json([
            'message' => 'Commission percentage updated successfully.',
            'data' => [
                'commission_percentage' => (float) $validated['commission_percentage'],
            ],
        ]);
    }
}
