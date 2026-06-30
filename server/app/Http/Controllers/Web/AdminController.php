<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Models\ReferralCode;
use App\Models\TripLedger;
use App\Models\TripRoute;
use App\Models\User;
use App\Services\CompanyRevenueService;
use App\Services\RegistrationService;
use App\Services\ReferralCodeService;
use App\Services\ScheduleLifecycleService;
use App\Support\LocationCatalog;
use App\Support\PageList;
use App\Support\RouteDistanceCatalog;
use App\Support\PlatformFees;
use App\Support\PlatformPaymentInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function __construct(
        private readonly ScheduleLifecycleService $scheduleLifecycle,
        private readonly RegistrationService $registration,
        private readonly ReferralCodeService $referralCodes,
        private readonly CompanyRevenueService $revenue,
    ) {
    }

    public function dashboard()
    {
        $this->scheduleLifecycle->sync();

        $operators = User::query()
            ->where('role', 'operator')
            ->latest()
            ->paginate(PageList::PER_PAGE, ['*'], 'operators_page')
            ->withQueryString();

        $referralCodes = ReferralCode::query()
            ->orderByRaw("CASE WHEN type = 'referrer' AND status = 'suspended' THEN 1 ELSE 0 END")
            ->latest()
            ->paginate(PageList::PER_PAGE, ['*'], 'referrals_page')
            ->withQueryString();

        $feeSettings = [
            'app_commission'           => PlatformFees::appCommissionPercent(),
            'referral_commission_first'  => PlatformFees::referralCommissionFirstPercent(),
            'referral_commission_repeat' => PlatformFees::referralCommissionRepeatPercent(),
            'round_trip_discount'      => PlatformFees::roundTripDiscountPercent(),
            'km_rate_under_100'   => PlatformFees::kmRateUnder100(),
            'km_rate_over_100'    => PlatformFees::kmRateOver100(),
        ];

        $hubRoutes = TripRoute::query()
            ->where('departure', RouteDistanceCatalog::HUB)
            ->orderByDesc('is_active')
            ->orderByRaw('CASE WHEN is_active = 1 THEN updated_at ELSE NULL END DESC')
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
            LocationCatalog::forgetCache();
        }

        $bankSettings = PlatformPaymentInfo::bank();
        $bankQrPreview = PlatformPaymentInfo::vietQrImageUrl();
        $revenueSummary = $this->revenue->summary();
        $revenueMonthFrom = now()->startOfMonth();
        $revenueMonthTo = now();
        $revenueByDriver = $this->revenue->actorBreakdown(TripLedger::OUTCOME_COMPLETED, $revenueMonthFrom, $revenueMonthTo);
        $revenueByCustomerCancel = $this->revenue->actorBreakdown(TripLedger::OUTCOME_CANCELLED_CUSTOMER, $revenueMonthFrom, $revenueMonthTo);
        $revenueByDriverCancel = $this->revenue->actorBreakdown(TripLedger::OUTCOME_CANCELLED_DRIVER, $revenueMonthFrom, $revenueMonthTo);
        $revenueByRoute = $this->revenue->routeBreakdown($revenueMonthFrom, $revenueMonthTo);
        $tripLedger = TripLedger::query()
            ->orderByDesc('recorded_at')
            ->paginate(PageList::PER_PAGE, ['*'], 'trips_page')
            ->withQueryString();

        return view('admin.dashboard', compact(
            'operators',
            'referralCodes',
            'feeSettings',
            'hubRoutes',
            'bankSettings',
            'bankQrPreview',
            'revenueSummary',
            'revenueByDriver',
            'revenueByCustomerCancel',
            'revenueByDriverCancel',
            'revenueByRoute',
            'tripLedger',
        ));
    }

    public function storeOperator(Request $request)
    {
        $validated = $request->validate($this->registration->operatorRules());

        $this->registration->registerOperator($validated, Auth::id());

        return redirect()->route('admin.dashboard', ['tab' => 'create'])
            ->with('success', 'Đã tạo tài khoản quản lý cho ' . $validated['name'] . '. Họ có thể đăng nhập ngay.');
    }

    public function storeReferrer(Request $request)
    {
        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
        ]);

        $referral = $this->referralCodes->createReferrer(
            $validated['name'],
            $validated['phone'],
            (int) Auth::id(),
        );

        return redirect()->route('admin.dashboard', ['tab' => 'referrals'])
            ->with('success', 'Đã tạo mã giới thiệu ' . $referral->code . ' cho ' . $referral->name . '.');
    }

    public function suspendReferrer(ReferralCode $referralCode)
    {
        $this->referralCodes->suspendReferrer($referralCode);

        return redirect()->route('admin.dashboard', ['tab' => 'referrals'])
            ->with('success', 'Đã tạm ngưng mã ' . $referralCode->code . ' (' . $referralCode->name . ').');
    }

    public function showReferrer(ReferralCode $referralCode)
    {
        $this->referralCodes->restoreReferrer($referralCode);

        return redirect()->route('admin.dashboard', ['tab' => 'referrals'])
            ->with('success', 'Mã ' . $referralCode->code . ' đã chuyển sang trạng thái sử dụng.');
    }

    public function updateBankSettings(Request $request)
    {
        $validated = $request->validate([
            'bank_name'    => ['required', 'string', 'max:120'],
            'bank_bin'     => ['required', 'string', 'max:20'],
            'account'      => ['required', 'string', 'max:40'],
            'account_name' => ['required', 'string', 'max:120'],
        ]);

        PlatformSetting::setValue('platform_bank', [
            'bank_name'    => trim($validated['bank_name']),
            'bank_bin'     => preg_replace('/\D/', '', $validated['bank_bin']),
            'account'      => preg_replace('/\s+/', '', $validated['account']),
            'account_name' => trim($validated['account_name']),
        ], 'finance');

        return redirect()->route('admin.dashboard', ['tab' => 'bank'])
            ->with('success', 'Đã lưu tài khoản ngân hàng — QR VietQR tự sinh khi tài xế nạp ví / đóng phí.');
    }

    public function updateUserStatus(Request $request, User $user)
    {
        if ($user->role !== 'operator') {
            abort(403);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended'])],
        ]);

        $user->update($validated);

        $label = match ($validated['status']) {
            'active'    => 'kích hoạt',
            'suspended' => 'tạm ngưng',
        };

        return redirect()->route('admin.dashboard', ['tab' => 'list'])
            ->with('success', 'Đã ' . $label . ' tài khoản quản lý ' . $user->name . '.');
    }

    public function updateFeeSettings(Request $request)
    {
        $validated = $request->validate([
            'app_commission'        => ['required', 'numeric', 'min:0', 'max:100'],
            'referral_commission_first'  => ['required', 'numeric', 'min:0', 'max:100'],
            'referral_commission_repeat' => ['required', 'numeric', 'min:0', 'max:100'],
            'round_trip_discount'   => ['required', 'numeric', 'min:0', 'max:100'],
            'km_rate_under_100'   => ['required', 'integer', 'min:0'],
            'km_rate_over_100'    => ['required', 'integer', 'min:0'],
        ]);

        PlatformSetting::setValue('app_commission_percentage', [
            'value' => (float) $validated['app_commission'],
        ], 'finance');

        PlatformSetting::setValue('commission_percentage', [
            'value' => (float) $validated['app_commission'],
        ], 'finance');

        PlatformSetting::setValue('referral_commission_first_percentage', [
            'value' => (float) $validated['referral_commission_first'],
        ], 'finance');

        PlatformSetting::setValue('referral_commission_repeat_percentage', [
            'value' => (float) $validated['referral_commission_repeat'],
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
                ->where('is_active', true)
                ->update(['distance_km' => (int) $row['distance_km']]);
        }

        LocationCatalog::forgetCache();

        return redirect()->route('admin.dashboard', ['tab' => 'routes'])
            ->with('success', 'Đã lưu quãng đường từ TP.HCM.');
    }

    public function storeDestination(Request $request)
    {
        $hub = RouteDistanceCatalog::HUB;

        $validated = $request->validate([
            'destination' => [
                'required',
                'string',
                'max:100',
                Rule::notIn([$hub]),
                Rule::unique('routes', 'destination')->where(
                    fn ($q) => $q->where('departure', $hub)->where('is_active', true),
                ),
            ],
            'distance_km' => ['required', 'integer', 'min:1', 'max:2000'],
        ], [
            'destination.unique' => 'Điểm đến này đã có trong danh sách.',
            'destination.not_in' => 'Không thể thêm trùng trung tâm ' . $hub . '.',
        ]);

        $name = trim($validated['destination']);
        $existing = TripRoute::query()
            ->where('departure', $hub)
            ->where('destination', $name)
            ->first();

        if ($existing) {
            if (! $existing->is_active) {
                $existing->update([
                    'is_active'   => true,
                    'distance_km' => (int) $validated['distance_km'],
                    'updated_at'  => now(),
                ]);
                LocationCatalog::forgetCache();

                return redirect()->route('admin.dashboard', ['tab' => 'routes'])
                    ->with('success', 'Đã hiện lại điểm đến ' . $name . '.');
            }

            return back()
                ->withInput()
                ->withErrors(['destination' => 'Điểm đến này đã có trong danh sách.']);
        }

        TripRoute::query()->create([
            'departure'    => $hub,
            'destination'  => $name,
            'distance_km'  => (int) $validated['distance_km'],
            'base_price'   => 0,
            'is_active'    => true,
        ]);

        LocationCatalog::forgetCache();

        return redirect()->route('admin.dashboard', ['tab' => 'routes'])
            ->with('success', 'Đã thêm điểm đến ' . trim($validated['destination']) . '.');
    }

    public function destroyDestination(TripRoute $tripRoute)
    {
        if ($tripRoute->departure !== RouteDistanceCatalog::HUB) {
            abort(404);
        }

        $tripRoute->update(['is_active' => false]);
        LocationCatalog::forgetCache();

        return redirect()->route('admin.dashboard', ['tab' => 'routes'])
            ->with('success', 'Đã ẩn điểm đến ' . $tripRoute->destination . '.');
    }

    public function showDestination(TripRoute $tripRoute)
    {
        if ($tripRoute->departure !== RouteDistanceCatalog::HUB) {
            abort(404);
        }

        $tripRoute->update([
            'is_active'  => true,
            'updated_at' => now(),
        ]);
        LocationCatalog::forgetCache();

        return redirect()->route('admin.dashboard', ['tab' => 'routes'])
            ->with('success', 'Đã hiện điểm đến ' . $tripRoute->destination . ' lên đầu danh sách.');
    }
}
