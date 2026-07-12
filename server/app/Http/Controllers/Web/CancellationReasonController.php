<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\BookingPhoneGuardService;
use App\Services\CancellationReasonService;
use Illuminate\Http\Request;

class CancellationReasonController extends Controller
{
    public function __construct(
        private readonly CancellationReasonService $reasons,
        private readonly BookingPhoneGuardService $phoneGuard,
    ) {
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'audience' => ['required', 'in:customer,driver'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
        ]);

        $payload = [
            'reasons' => $this->reasons->serializeForAudience($validated['audience']),
        ];

        if ($validated['audience'] === 'customer') {
            $payload['requires_reason'] = filled($validated['contact_phone'] ?? null)
                ? $this->phoneGuard->requiresCancelReason($validated['contact_phone'])
                : true;
            if (filled($validated['contact_phone'] ?? null)) {
                $payload['cancel_count'] = $this->phoneGuard->customerCancelCount($validated['contact_phone']);
            }
        }

        if ($validated['audience'] === 'driver') {
            $payload['requires_reason'] = true;
        }

        return response()->json($payload);
    }
}
