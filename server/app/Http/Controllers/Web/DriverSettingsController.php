<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Driver\UpdateDriverSettingsRequest;
use App\Models\DriverProfile;
use App\Models\DriverProfileChangeRequest;
use App\Services\DriverProfileChangeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DriverSettingsController extends Controller
{
    public function __construct(
        private DriverProfileChangeService $profileChanges,
    ) {}

    public function updateSettings(UpdateDriverSettingsRequest $request)
    {
        $profile = $this->currentProfile();
        $validated = $request->validated();

        $profile->update([
            'locale'        => $validated['locale'],
            'sound_enabled' => (bool) ($validated['sound_enabled'] ?? true),
            'sound_preset'  => $validated['sound_preset'],
        ]);

        session(['driver_locale' => $profile->locale]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok'            => true,
                'message'       => 'Đã lưu cài đặt.',
                'locale'        => $profile->locale,
                'sound_enabled' => (bool) $profile->sound_enabled,
                'sound_preset'  => $profile->sound_preset,
            ]);
        }

        return redirect()
            ->route('driver.dashboard', ['tab' => 'settings'])
            ->with('success', 'Đã lưu cài đặt.');
    }

    public function submitDocuments(Request $request)
    {
        $profile = $this->currentProfile();

        if (! $profile->isApproved()) {
            return back()->withErrors(['documents' => 'Hồ sơ chưa được duyệt — chưa thể gửi cập nhật giấy tờ.']);
        }

        $this->profileChanges->submit($profile, $request);

        return redirect()
            ->route('driver.dashboard', ['tab' => 'account-update'])
            ->with('success', 'Đã gửi cập nhật. Chờ quản trị duyệt trước khi áp dụng.');
    }

    public function approveChange(DriverProfile $driverProfile, DriverProfileChangeRequest $changeRequest)
    {
        $this->assertAdminOwnsChange($driverProfile, $changeRequest);
        $this->profileChanges->approve($changeRequest, Auth::user());

        return redirect()
            ->route('admin.drivers.edit', $driverProfile)
            ->with('success', 'Đã duyệt và áp dụng cập nhật giấy tờ tài xế.');
    }

    public function rejectChange(Request $request, DriverProfile $driverProfile, DriverProfileChangeRequest $changeRequest)
    {
        $this->assertAdminOwnsChange($driverProfile, $changeRequest);
        $this->profileChanges->reject($changeRequest, Auth::user());

        return redirect()
            ->route('admin.drivers.edit', $driverProfile)
            ->with('success', 'Đã xóa yêu cầu cập nhật giấy tờ.');
    }

    private function currentProfile(): DriverProfile
    {
        $profile = DriverProfile::query()->where('user_id', Auth::id())->first();
        abort_unless($profile, 404);

        return $profile;
    }

    private function assertAdminOwnsChange(DriverProfile $driverProfile, DriverProfileChangeRequest $changeRequest): void
    {
        abort_unless((int) $changeRequest->driver_profile_id === (int) $driverProfile->id, 404);
        abort_unless($changeRequest->isPending(), 422);
    }
}
