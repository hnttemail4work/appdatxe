<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePricingSettingsRequest;
use App\Http\Requests\Admin\UpsertPricingSurchargeRequest;
use App\Http\Requests\Admin\UpsertPricingTollRequest;
use App\Http\Requests\Admin\UpsertVehicleTypeRequest;
use App\Models\PlatformSetting;
use App\Models\PricingSurchargeRule;
use App\Models\PricingToll;
use App\Models\VehicleType;
use App\Services\ReferralCodeService;
use App\Support\PricingConfig;

class AdminPricingController extends Controller
{
    public function __construct(
        private readonly ReferralCodeService $referralCodes,
    ) {
    }

    /** @return array<string, mixed> */
    public static function dashboardPayload(): array
    {
        return [
            'pricingSettings' => PricingConfig::forAdmin(),
            'vehicleTypes'    => VehicleType::query()->orderBy('sort_order')->orderBy('id')->get(),
            'surchargeRules'  => PricingSurchargeRule::query()->orderBy('type')->orderBy('sort_order')->orderBy('id')->get(),
            'pricingTolls'    => PricingToll::query()->orderBy('from_province')->orderBy('to_province')->get(),
        ];
    }

    public function updateSettings(UpdatePricingSettingsRequest $request)
    {
        $v = $request->validated();

        if ($request->input('form_scope') === 'qr') {
            PlatformSetting::setValue('referral_commission_first_percentage', ['value' => (float) $v['referral_commission_first']], 'finance');
            PlatformSetting::setValue('referral_commission_repeat_percentage', ['value' => (float) $v['booking_qr_discount']], 'finance');
            PlatformSetting::setValue('driver_invite_qr_discount_percentage', ['value' => (float) $v['driver_invite_qr_discount']], 'finance');

            if ($request->boolean('sync_driver_invite_discount')) {
                $this->referralCodes->syncDriverInviteDiscountPercent((float) $v['driver_invite_qr_discount']);
            }

            return redirect()->route('admin.referrals', ['tab' => 'rules'])
                ->with('success', 'Đã lưu rule giảm giá QR.');
        }

        PlatformSetting::setValue('pricing_km_rate_under_100', ['value' => (int) $v['km_rate_under_100']], 'finance');
        PlatformSetting::setValue('pricing_km_rate_over_100', ['value' => (int) $v['km_rate_over_100']], 'finance');
        PlatformSetting::setValue('pricing_intra_flat_max_km', ['value' => (int) $v['intra_flat_max_km']], 'finance');
        PlatformSetting::setValue('pricing_intra_flat_price', ['value' => (int) $v['intra_flat_price']], 'finance');
        PlatformSetting::setValue('pricing_rounding_unit', ['value' => (int) $v['rounding_unit']], 'finance');
        PlatformSetting::setValue('app_commission_percentage', ['value' => (float) $v['app_commission']], 'finance');
        PlatformSetting::setValue('commission_percentage', ['value' => (float) $v['app_commission']], 'finance');
        PricingConfig::setRainSurchargeEnabled($request->boolean('rain_surcharge_enabled'));

        return redirect()->route('admin.dashboard', ['tab' => 'fees'])
            ->with('success', 'Đã lưu cấu hình tính tiền.');
    }

    public function storeVehicleType(UpsertVehicleTypeRequest $request)
    {
        $v = $request->validated();
        VehicleType::query()->create([
            'key'           => $v['key'],
            'label'         => $v['label'],
            'seats'         => $v['seats'] ?? null,
            'family'        => $v['family'] ?? 'other',
            'price_percent' => (float) $v['price_percent'],
            'sort_order'    => (int) ($v['sort_order'] ?? 0),
            'is_active'     => $request->boolean('is_active', true),
        ]);
        VehicleType::forgetCache();

        return redirect()->route('admin.dashboard', ['tab' => 'fees'])
            ->with('success', 'Đã thêm loại xe.');
    }

    public function updateVehicleType(UpsertVehicleTypeRequest $request, VehicleType $vehicleType)
    {
        $v = $request->validated();
        $vehicleType->update([
            'label'         => $v['label'],
            'seats'         => $v['seats'] ?? null,
            'family'        => $v['family'] ?? 'other',
            'price_percent' => (float) $v['price_percent'],
            'sort_order'    => (int) ($v['sort_order'] ?? $vehicleType->sort_order),
            'is_active'     => $request->boolean('is_active'),
        ]);
        VehicleType::forgetCache();

        return redirect()->route('admin.dashboard', ['tab' => 'fees'])
            ->with('success', 'Đã cập nhật loại xe.');
    }

    public function destroyVehicleType(VehicleType $vehicleType)
    {
        if ($vehicleType->key === 'sedan_4') {
            return redirect()->route('admin.dashboard', ['tab' => 'fees'])
                ->withErrors(['vehicle_type' => 'Không thể xóa loại chuẩn sedan_4 — hãy tắt nếu cần.']);
        }

        $vehicleType->update(['is_active' => false]);
        VehicleType::forgetCache();

        return redirect()->route('admin.dashboard', ['tab' => 'fees'])
            ->with('success', 'Đã ẩn loại xe (không xóa cứng).');
    }

    public function storeSurcharge(UpsertPricingSurchargeRequest $request)
    {
        $v = $request->validated();
        PricingSurchargeRule::query()->create([
            'type'       => $v['type'],
            'name'       => $v['name'],
            'mode'       => $v['mode'],
            'value'      => (float) $v['value'],
            'payload'    => $this->surchargePayload($v),
            'is_active'  => $request->boolean('is_active', true),
            'sort_order' => (int) ($v['sort_order'] ?? 0),
        ]);
        PricingSurchargeRule::forgetCache();

        return redirect()->route('admin.dashboard', ['tab' => 'fees'])
            ->with('success', 'Đã thêm quy tắc phụ phí.');
    }

    public function updateSurcharge(UpsertPricingSurchargeRequest $request, PricingSurchargeRule $surcharge)
    {
        $v = $request->validated();
        $surcharge->update([
            'type'       => $v['type'],
            'name'       => $v['name'],
            'mode'       => $v['mode'],
            'value'      => (float) $v['value'],
            'payload'    => $this->surchargePayload($v),
            'is_active'  => $request->boolean('is_active'),
            'sort_order' => (int) ($v['sort_order'] ?? $surcharge->sort_order),
        ]);
        PricingSurchargeRule::forgetCache();

        return redirect()->route('admin.dashboard', ['tab' => 'fees'])
            ->with('success', 'Đã cập nhật phụ phí.');
    }

    public function destroySurcharge(PricingSurchargeRule $surcharge)
    {
        $surcharge->delete();
        PricingSurchargeRule::forgetCache();

        return redirect()->route('admin.dashboard', ['tab' => 'fees'])
            ->with('success', 'Đã xóa quy tắc phụ phí.');
    }

    public function storeToll(UpsertPricingTollRequest $request)
    {
        $v = $request->validated();
        PricingToll::query()->updateOrCreate(
            [
                'from_province' => trim($v['from_province']),
                'to_province'   => trim($v['to_province']),
            ],
            [
                'amount_vnd' => (int) $v['amount_vnd'],
                'is_active'  => $request->boolean('is_active', true),
            ],
        );
        PricingToll::forgetCache();

        return redirect()->route('admin.dashboard', ['tab' => 'fees'])
            ->with('success', 'Đã lưu thu phí tuyến.');
    }

    public function updateToll(UpsertPricingTollRequest $request, PricingToll $toll)
    {
        $v = $request->validated();
        $toll->update([
            'from_province' => trim($v['from_province']),
            'to_province'   => trim($v['to_province']),
            'amount_vnd'    => (int) $v['amount_vnd'],
            'is_active'     => $request->boolean('is_active'),
        ]);
        PricingToll::forgetCache();

        return redirect()->route('admin.dashboard', ['tab' => 'fees'])
            ->with('success', 'Đã cập nhật thu phí.');
    }

    public function destroyToll(PricingToll $toll)
    {
        $toll->delete();
        PricingToll::forgetCache();

        return redirect()->route('admin.dashboard', ['tab' => 'fees'])
            ->with('success', 'Đã xóa thu phí tuyến.');
    }

    /** @param  array<string, mixed>  $v */
    private function surchargePayload(array $v): array
    {
        return match ($v['type']) {
            PricingSurchargeRule::TYPE_HOLIDAY => [
                'starts_on' => $v['starts_on'] ?? null,
                'ends_on'   => $v['ends_on'] ?? ($v['starts_on'] ?? null),
            ],
            PricingSurchargeRule::TYPE_PEAK => [
                'days_of_week' => array_map('intval', $v['days_of_week'] ?? []),
                'start_time'   => $v['start_time'] ?? '07:00',
                'end_time'     => $v['end_time'] ?? '09:00',
            ],
            PricingSurchargeRule::TYPE_RAIN => [
                'start_time' => $v['start_time'] ?? null,
                'end_time'   => $v['end_time'] ?? null,
            ],
            default => [],
        };
    }
}
