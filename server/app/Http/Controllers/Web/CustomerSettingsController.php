<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\UpdateCustomerSettingsRequest;
use Illuminate\Support\Facades\Auth;

class CustomerSettingsController extends Controller
{
    public function update(UpdateCustomerSettingsRequest $request)
    {
        $user = Auth::user();
        $validated = $request->validated();

        $user->update([
            'locale'        => $validated['locale'],
            'sound_enabled' => (bool) ($validated['sound_enabled'] ?? true),
            'sound_preset'  => $validated['sound_preset'],
        ]);

        session(['customer_locale' => $user->locale]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok'            => true,
                'message'       => 'Đã lưu cài đặt.',
                'locale'        => $user->locale,
                'sound_enabled' => (bool) $user->sound_enabled,
                'sound_preset'  => $user->sound_preset,
            ]);
        }

        return redirect()
            ->route('customer.account', ['tab' => 'settings'])
            ->with('success', 'Đã lưu cài đặt.');
    }
}
