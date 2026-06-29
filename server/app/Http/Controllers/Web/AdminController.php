<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Models\TripRoute;
use App\Models\User;
use App\Services\RegistrationService;
use App\Services\ScheduleLifecycleService;
use App\Support\PageList;
use App\Support\RouteDistanceCatalog;
use App\Support\PlatformFees;
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
            'km_rate_under_100'   => PlatformFees::kmRateUnder100(),
            'km_rate_over_100'    => PlatformFees::kmRateOver100(),
        ];

        $hubRoutes = TripRoute::query()
            ->where('departure', RouteDistanceCatalog::HUB)
            ->orderBy('destination')
            ->get();

        if ($hubRoutes->isEmpty()) {
            foreach (RouteDistanceCatalog::hubRouteRows() as $row) {
                $hubRoutes->push(TripRoute::query()->firstOrCreate(
                    ['departure' => $row['departure'], 'destination' => $row['destination']],
                    ['base_price' => 0, 'distance_km' => $row['distance_km'], 'is_active' => true],
                ));
            }
            $hubRoutes = $hubRoutes->sortBy('destination')->values();
        }

        return view('admin.dashboard', compact('operators', 'feeSettings', 'hubRoutes'));
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
            'km_rate_under_100'   => ['required', 'integer', 'min:0'],
            'km_rate_over_100'    => ['required', 'integer', 'min:0'],
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

        PlatformSetting::setValue('pricing_km_rate_under_100', [
            'value' => (int) $validated['km_rate_under_100'],
        ], 'finance');

        PlatformSetting::setValue('pricing_km_rate_over_100', [
            'value' => (int) $validated['km_rate_over_100'],
        ], 'finance');

        return redirect()->route('admin.dashboard', ['tab' => 'fees'])
            ->with('success', 'Đã lưu cài đặt phí và bảng giá.');
    }

    public function updateRouteDistances(Request $request)
    {
        $validated = $request->validate([
            'routes'               => ['required', 'array'],
            'routes.*.id'          => ['required', 'integer', 'exists:routes,id'],
            'routes.*.distance_km' => ['required', 'integer', 'min:1', 'max:2000'],
        ]);

        foreach ($validated['routes'] as $row) {
            TripRoute::query()
                ->where('id', $row['id'])
                ->where('departure', RouteDistanceCatalog::HUB)
                ->update(['distance_km' => (int) $row['distance_km']]);
        }

        return redirect()->route('admin.dashboard', ['tab' => 'routes'])
            ->with('success', 'Đã lưu quãng đường từ TP.HCM.');
    }
}
