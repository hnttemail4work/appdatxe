<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MerchantProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MerchantApprovalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kyc_status' => ['nullable', Rule::in(['pending', 'approved', 'suspended', 'rejected'])],
        ]);

        $merchants = MerchantProfile::query()
            ->with(['user', 'approver'])
            ->when($validated['kyc_status'] ?? null, function ($query, string $kycStatus): void {
                $query->where('kyc_status', $kycStatus);
            })
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $merchants]);
    }

    public function show(MerchantProfile $merchantProfile): JsonResponse
    {
        $merchantProfile->load(['user', 'approver']);

        return response()->json(['data' => $merchantProfile]);
    }

    public function update(Request $request, MerchantProfile $merchantProfile): JsonResponse
    {
        $validated = $request->validate([
            'company_name' => ['sometimes', 'string', 'max:255'],
            'tax_code' => ['sometimes', 'nullable', 'string', 'max:255'],
            'business_license' => ['sometimes', 'nullable', 'string', 'max:255'],
            'kyc_status' => ['sometimes', Rule::in(['pending', 'approved', 'suspended', 'rejected'])],
            'documents' => ['sometimes', 'array'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $merchantProfile->update($validated);

        return response()->json(['message' => 'Merchant profile updated successfully.', 'data' => $merchantProfile->fresh(['user', 'approver'])]);
    }

    public function approve(Request $request, MerchantProfile $merchantProfile): JsonResponse
    {
        $merchantProfile->update([
            'kyc_status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'suspended_at' => null,
        ]);

        return response()->json(['message' => 'Merchant approved successfully.', 'data' => $merchantProfile->fresh(['user', 'approver'])]);
    }

    public function suspend(MerchantProfile $merchantProfile): JsonResponse
    {
        $merchantProfile->update([
            'kyc_status' => 'suspended',
            'suspended_at' => now(),
        ]);

        return response()->json(['message' => 'Merchant suspended successfully.', 'data' => $merchantProfile->fresh(['user', 'approver'])]);
    }

    public function reject(MerchantProfile $merchantProfile): JsonResponse
    {
        $merchantProfile->update([
            'kyc_status' => 'rejected',
            'suspended_at' => now(),
        ]);

        return response()->json(['message' => 'Merchant rejected successfully.', 'data' => $merchantProfile->fresh(['user', 'approver'])]);
    }
}
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
