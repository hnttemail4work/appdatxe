<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDestinationRequest;
use App\Http\Requests\Admin\UpdateAdminPasswordRequest;
use App\Http\Requests\Admin\UpdateBankSettingsRequest;
use App\Http\Requests\Admin\UpdateBookingPageSettingsRequest;
use App\Http\Requests\Admin\UpdateBrandingSettingsRequest;
use App\Http\Requests\Admin\UpdateFeeSettingsRequest;
use App\Http\Requests\Admin\UpdatePushSettingsRequest;
use App\Http\Requests\Admin\UpdateRouteDistancesRequest;
use App\Models\PlatformSetting;
use App\Models\TripRoute;
use App\Services\ScheduleLifecycleService;
use App\Support\AppBrandingSettings;
use App\Support\BookingPageSettings;
use App\Support\LocationCatalog;
use App\Support\PlatformFees;
use App\Support\PlatformPaymentInfo;
use App\Support\ProvinceCenters;
use App\Support\PushNotificationSettings;
use App\Support\RouteDistanceCatalog;
use App\Support\VehicleTypePricing;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Nhóm "cài đặt hệ thống" — tách ra từ AdminController (Fat Controller):
 * dashboard tổng quan, phí/bảng giá, bank, trang đặt xe, thương hiệu, thông báo đẩy,
 * và danh mục điểm đến (destinations). Không đụng tới booking/wallet/referral.
 */
class AdminSettingsController extends Controller
{
    public function __construct(
        private readonly ScheduleLifecycleService $scheduleLifecycle,
    ) {
    }

    public function dashboard()
    {
        $this->scheduleLifecycle->sync();

        $feeSettings = [
            'app_commission'           => PlatformFees::appCommissionPercent(),
            'referral_commission_first'  => PlatformFees::referralCommissionFirstPercent(),
            'referral_commission_repeat' => PlatformFees::referralCommissionRepeatPercent(),
            'km_rate_under_100'   => PlatformFees::kmRateUnder100(),
            'km_rate_over_100'    => PlatformFees::kmRateOver100(),
            'departure_plan_surcharge_today' => PlatformFees::departurePlanTodaySurchargePercent(),
            'departure_plan_surcharge_tomorrow' => PlatformFees::departurePlanTomorrowSurchargePercent(),
            'departure_plan_surcharge_later_per_day' => PlatformFees::departurePlanLaterPercentPerDay(),
            'vehicle_types' => VehicleTypePricing::settingsForAdmin(),
            'vehicleTypeLabels' => VehicleTypePricing::labels(),
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
        $bookingPageSettings = BookingPageSettings::forAdmin();
        $brandingSettings = AppBrandingSettings::forAdmin();
        $pushSettings = PushNotificationSettings::forAdmin();
        $pushEventLabels = PushNotificationSettings::eventLabels();
        $pushVapidReady = PushNotificationSettings::vapidKeys() !== null;

        return view('admin.dashboard', compact(
            'feeSettings',
            'hubRoutes',
            'bankSettings',
            'bankQrPreview',
            'bookingPageSettings',
            'brandingSettings',
            'pushSettings',
            'pushEventLabels',
            'pushVapidReady',
        ));
    }

    public function updateBankSettings(UpdateBankSettingsRequest $request)
    {
        $validated = $request->validated();

        PlatformSetting::setValue('platform_bank', [
            'bank_name'    => trim($validated['bank_name']),
            'bank_bin'     => preg_replace('/\D/', '', $validated['bank_bin']),
            'account'      => preg_replace('/\s+/', '', $validated['account']),
            'account_name' => trim($validated['account_name']),
        ], 'finance');

        return redirect()->route('admin.dashboard', ['tab' => 'settings'])
            ->with('success', 'Đã lưu tài khoản ngân hàng — QR VietQR tự sinh khi tài xế nạp ví / đóng phí.');
    }

    public function updatePassword(UpdateAdminPasswordRequest $request)
    {
        $user = Auth::user();

        $validated = $request->validated();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return redirect()->route('admin.dashboard', ['tab' => 'account'])
                ->withErrors(['current_password' => 'Mật khẩu hiện tại không đúng.']);
        }

        $user->update([
            'password'             => $validated['password'],
            'must_change_password' => false,
        ]);

        return redirect()->route('admin.dashboard', ['tab' => 'account'])
            ->with('success', 'Đã đổi mật khẩu quản trị thành công.');
    }

    public function updateBookingPageSettings(UpdateBookingPageSettingsRequest $request)
    {
        $validated = $request->validated();

        BookingPageSettings::saveHeroTitle((string) ($validated['hero_title'] ?? ''));

        if ($request->boolean('remove_banner')) {
            BookingPageSettings::removeBanner();
        } elseif ($request->hasFile('banner')) {
            BookingPageSettings::storeBanner($request->file('banner'));
        }

        return redirect()->route('admin.dashboard', ['tab' => 'appearance'])
            ->with('success', 'Đã lưu cài đặt trang đặt xe.');
    }

    public function updateBrandingSettings(UpdateBrandingSettingsRequest $request)
    {
        $validated = $request->validated();

        AppBrandingSettings::saveBranding(
            (string) ($validated['app_name'] ?? ''),
            (string) ($validated['brand_title'] ?? ''),
            (string) ($validated['brand_tagline'] ?? ''),
            (string) ($validated['pwa_guest_short_name'] ?? ''),
            (string) ($validated['pwa_driver_short_name'] ?? ''),
        );

        if ($request->boolean('remove_app_icon')) {
            AppBrandingSettings::removeAppIcon();
        } elseif ($request->hasFile('app_icon')) {
            AppBrandingSettings::storeAppIcon($request->file('app_icon'));
        }

        return redirect()->route('admin.dashboard', ['tab' => 'appearance'])
            ->with('success', 'Đã lưu thương hiệu và biểu tượng app.');
    }

    public function updatePushSettings(UpdatePushSettingsRequest $request)
    {
        $validated = $request->validated();

        $validated['enabled'] = $request->boolean('enabled');
        PushNotificationSettings::saveFromAdmin($validated);

        return redirect()->route('admin.dashboard', ['tab' => 'appearance'])
            ->with('success', 'Đã lưu cài đặt thông báo đẩy.');
    }

    public function updateFeeSettings(UpdateFeeSettingsRequest $request)
    {
        $validated = $request->validated();

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

        PlatformSetting::setValue('pricing_km_rate_under_100', [
            'value' => (int) $validated['km_rate_under_100'],
        ], 'finance');

        PlatformSetting::setValue('pricing_km_rate_over_100', [
            'value' => (int) $validated['km_rate_over_100'],
        ], 'finance');

        PlatformSetting::setValue('departure_plan_surcharge_today_percentage', [
            'value' => (float) $validated['departure_plan_surcharge_today'],
        ], 'finance');

        PlatformSetting::setValue('departure_plan_surcharge_tomorrow_percentage', [
            'value' => (float) $validated['departure_plan_surcharge_tomorrow'],
        ], 'finance');

        PlatformSetting::setValue('departure_plan_surcharge_later_per_day_percentage', [
            'value' => (float) $validated['departure_plan_surcharge_later_per_day'],
        ], 'finance');

        VehicleTypePricing::save(
            (float) $validated['vehicle_type_step_percent'],
            $validated['vehicle_type_percents'] ?? [],
        );

        return redirect()->route('admin.dashboard', ['tab' => 'fees'])
            ->with('success', 'Đã lưu cài đặt phí và bảng giá theo loại xe.');
    }

    public function updateRouteDistances(UpdateRouteDistancesRequest $request)
    {
        $validated = $request->validated();

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

    public function storeDestination(StoreDestinationRequest $request)
    {
        $hub = RouteDistanceCatalog::HUB;

        $validated = $request->validated();

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
                ProvinceCenters::warmCenter($name);

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
        ProvinceCenters::warmCenter($name);

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
