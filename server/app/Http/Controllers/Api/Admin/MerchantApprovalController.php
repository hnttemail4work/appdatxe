<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MerchantProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantApprovalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $profiles = MerchantProfile::query()
            ->with(['user', 'approver'])
            ->when($request->filled('kyc_status'), fn ($query) => $query->where('kyc_status', $request->string('kyc_status')))
            ->latest()
            ->paginate((int) $request->input('per_page', 15));

        return response()->json([
            'data' => $profiles->items(),
            'meta' => [
                'current_page' => $profiles->currentPage(),
                'last_page' => $profiles->lastPage(),
                'per_page' => $profiles->perPage(),
                'total' => $profiles->total(),
            ],
        ]);
    }

    public function show(MerchantProfile $merchantProfile): JsonResponse
    {
        return response()->json(['data' => $merchantProfile->load(['user', 'approver'])]);
    }

    public function approve(Request $request, MerchantProfile $merchantProfile): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $merchantProfile->update([
            'kyc_status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'suspended_at' => null,
            'notes' => $validated['notes'] ?? $merchantProfile->notes,
        ]);

        $merchantProfile->user()->update(['status' => 'active']);

        return response()->json(['data' => $merchantProfile->fresh()->load(['user', 'approver'])]);
    }

    public function suspend(Request $request, MerchantProfile $merchantProfile): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $merchantProfile->update([
            'kyc_status' => 'suspended',
            'suspended_at' => now(),
            'notes' => $validated['notes'] ?? $merchantProfile->notes,
        ]);

        $merchantProfile->user()->update(['status' => 'suspended']);

        return response()->json(['data' => $merchantProfile->fresh()->load(['user', 'approver'])]);
    }

    public function reject(Request $request, MerchantProfile $merchantProfile): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $merchantProfile->update([
            'kyc_status' => 'rejected',
            'notes' => $validated['notes'] ?? $merchantProfile->notes,
        ]);

        $merchantProfile->user()->update(['status' => 'inactive']);

        return response()->json(['data' => $merchantProfile->fresh()->load(['user', 'approver'])]);
    }
}
