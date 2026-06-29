<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\RegistrationService;
use App\Services\ScheduleLifecycleService;
use App\Support\PlatformFees;
use App\Support\PageList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function __construct(
        private readonly ScheduleLifecycleService $scheduleLifecycle,
        private readonly RegistrationService $registration,
    ) {
    }

    public function dashboard()
    {
        $this->scheduleLifecycle->sync();

        $operators = User::query()
            ->where('role', 'operator')
            ->latest()
            ->paginate(PageList::PER_PAGE)
            ->withQueryString();

        $feeSettings = [
            'app_commission'      => PlatformFees::appCommissionPercent(),
            'round_trip_discount' => PlatformFees::roundTripDiscountPercent(),
        ];

        return view('admin.dashboard', compact('operators', 'feeSettings'));
    }

    public function storeOperator(Request $request)
    {
        $validated = $request->validate($this->registration->operatorRules());

        $this->registration->registerOperator($validated, Auth::id());

        return redirect()->route('admin.dashboard')
            ->with('success', 'Đã tạo tài khoản quản lý cho ' . $validated['name'] . '. Họ có thể đăng nhập ngay.');
    }

    public function updateUserStatus(Request $request, User $user)
    {
        if ($user->role !== 'operator') {
            abort(403);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
        ]);

        $user->update($validated);

        $label = match ($validated['status']) {
            'active'    => 'kích hoạt',
            'inactive'  => 'vô hiệu hóa',
            'suspended' => 'tạm ngưng',
        };

        return redirect()->route('admin.dashboard')
            ->with('success', 'Đã ' . $label . ' tài khoản quản lý ' . $user->name . '.');
    }

    public function updateFeeSettings(Request $request)
    {
        $validated = $request->validate([
            'app_commission'      => ['required', 'numeric', 'min:0', 'max:100'],
            'round_trip_discount' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        PlatformSetting::setValue('app_commission_percentage', [
            'value' => (float) $validated['app_commission'],
        ], 'finance');

        PlatformSetting::setValue('commission_percentage', [
            'value' => (float) $validated['app_commission'],
        ], 'finance');

        PlatformSetting::setValue('round_trip_discount_percentage', [
            'value' => (float) $validated['round_trip_discount'],
        ], 'finance');

        return redirect()->route('admin.dashboard', ['tab' => 'fees'])
            ->with('success', 'Đã lưu cài đặt phí.');
    }
}
