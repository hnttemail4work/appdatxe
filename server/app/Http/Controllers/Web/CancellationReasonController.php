<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancellationReasonIndexRequest;
use App\Services\BookingPhoneGuardService;
use App\Services\CancellationReasonService;

class CancellationReasonController extends Controller
{
    public function __construct(
        private readonly CancellationReasonService $reasons,
        private readonly BookingPhoneGuardService $phoneGuard,
    ) {
    }

    public function index(CancellationReasonIndexRequest $request)
    {
        $validated = $request->validated();

        $payload = [
            'reasons' => $this->reasons->serializeForAudience($validated['audience']),
        ];

        // Lý do hủy chuyến (khách sau khi TX nhận / tài xế) — luôn bắt buộc khi mở modal.
        $payload['requires_reason'] = true;

        if ($validated['audience'] === 'customer' && filled($validated['contact_phone'] ?? null)) {
            $payload['cancel_count'] = $this->phoneGuard->customerCancelCount($validated['contact_phone']);
        }

        return response()->json($payload);
    }
}
