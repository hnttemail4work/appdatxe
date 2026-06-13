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
