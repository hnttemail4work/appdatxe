<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreReferrerRequest;
use App\Http\Requests\Admin\UpdateReferrerRequest;
use App\Models\DriverProfile;
use App\Models\ReferralCode;
use App\Services\ReferralCodeService;
use App\Support\PageList;
use App\Support\PricingConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * Nhóm "mã giới thiệu" — tách ra từ AdminController (Fat Controller).
 * Trang admin QR (sub-tab mã giảm / rule / ĐN-ĐK).
 */
class AdminReferralController extends Controller
{
    public function __construct(
        private readonly ReferralCodeService $referralCodes,
    ) {
    }

    public function referrals(Request $request)
    {
        $data = $this->referralsListData($request);
        $data['qrTab'] = $this->resolveQrTab($request);
        $data['pricingSettings'] = PricingConfig::forAdmin();

        return view('admin.referrals', $data);
    }

    /**
     * @return array{
     *     referralCodes: \Illuminate\Contracts\Pagination\LengthAwarePaginator,
     *     assignableDrivers: \Illuminate\Support\Collection<int, DriverProfile>,
     *     inviteDriver: DriverProfile|null,
     *     inviteReferral: ReferralCode|null,
     *     commissionReferral: ReferralCode|null
     * }
     */
    private function referralsListData(Request $request): array
    {
        $referralCodes = ReferralCode::query()
            ->whereNull('driver_profile_id')
            ->with(['booking', 'assignedDriverProfile'])
            ->orderByRaw("CASE WHEN type = 'referrer' AND status = 'suspended' THEN 1 WHEN type = 'referrer' THEN 0 WHEN type = 'booking_temp' AND status = 'active' THEN 2 WHEN type = 'booking_temp' THEN 3 ELSE 4 END")
            ->orderByDesc('activated_at')
            ->orderByDesc('created_at')
            ->paginate(PageList::PER_PAGE, ['*'], 'referrals_page')
            ->withQueryString();

        $assignableDrivers = DriverProfile::query()
            ->with('user')
            ->where('approval_status', 'approved')
            ->whereHas('user', fn ($q) => $q->where('role', 'driver'))
            ->orderByDesc('id')
            ->get()
            ->filter(fn (DriverProfile $p) => $p->user)
            ->values();

        $inviteDriver = null;
        $inviteReferral = null;
        $commissionReferral = null;
        $inviteDriverId = (int) $request->query('invite_driver', 0);
        if ($inviteDriverId > 0) {
            $inviteDriver = $assignableDrivers->firstWhere('id', $inviteDriverId);
            if ($inviteDriver) {
                $inviteReferral = $this->referralCodes->forDriver($inviteDriver)
                    ?? $inviteDriver->referralCode;
                $commissionReferral = $this->referralCodes->assignedCommissionForDriver($inviteDriver)
                    ?? $inviteDriver->assignedCommissionCode;
            }
        }

        return compact(
            'referralCodes',
            'assignableDrivers',
            'inviteDriver',
            'inviteReferral',
            'commissionReferral',
        );
    }

    private function resolveQrTab(Request $request): string
    {
        $tab = (string) $request->query('tab', 'codes');

        return in_array($tab, ['codes', 'rules', 'user-auth', 'driver-auth'], true)
            ? $tab
            : 'codes';
    }

    /** @return array<string, mixed> */
    private function qrCodesRedirectParams(array $extra = []): array
    {
        return array_merge(['tab' => 'codes'], $extra);
    }

    public function assignReferrer(Request $request, ReferralCode $referralCode)
    {
        $validated = $request->validate([
            'driver_profile_id' => ['required', 'integer', 'exists:driver_profiles,id'],
        ]);

        $profile = DriverProfile::query()->findOrFail((int) $validated['driver_profile_id']);

        try {
            $this->referralCodes->assignCommissionToDriver($referralCode, $profile);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['driver_profile_id' => $e->getMessage()]);
        }

        $driverName = $profile->user?->preferredDisplayName() ?: $profile->driver_code;

        return redirect()->route('admin.referrals', $this->qrCodesRedirectParams())
            ->with('success', 'Đã gán mã ' . $referralCode->code . ' cho tài xế ' . $driverName . '.');
    }

    public function revokeReferrer(ReferralCode $referralCode)
    {
        try {
            $this->referralCodes->revokeCommissionFromDriver($referralCode);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['referral' => $e->getMessage()]);
        }

        return redirect()->route('admin.referrals', $this->qrCodesRedirectParams())
            ->with('success', 'Đã thu hồi mã ' . $referralCode->code . ' khỏi tài xế.');
    }

    public function storeReferrer(StoreReferrerRequest $request)
    {
        $validated = $request->validated();

        $referral = $this->referralCodes->createReferrer(
            $validated['name'],
            $validated['phone'],
            Auth::id(),
        );

        return redirect()->route('admin.referrals', $this->qrCodesRedirectParams(['referrals_page' => 1]))
            ->with('success', 'Đã tạo mã giới thiệu ' . $referral->code . ' cho ' . $referral->name . '.');
    }

    public function updateReferrer(UpdateReferrerRequest $request, ReferralCode $referralCode)
    {
        if ($referralCode->type !== ReferralCode::TYPE_REFERRER) {
            abort(403);
        }

        $validated = $request->validated();

        $referralCode->update([
            'commission_percent'        => (float) $validated['commission_percent'],
            'customer_discount_percent' => (float) $validated['customer_discount_percent'],
        ]);

        return redirect()->route('admin.referrals', $this->qrCodesRedirectParams())
            ->with('success', 'Đã cập nhật mã ' . $referralCode->code . ' — giảm giá ' . number_format($validated['customer_discount_percent'], 1) . '%, hoa hồng ' . number_format($validated['commission_percent'], 1) . '%.');
    }

    public function suspendReferrer(ReferralCode $referralCode)
    {
        $this->referralCodes->suspendReferrer($referralCode);

        return redirect()->route('admin.referrals', $this->qrCodesRedirectParams())
            ->with('success', 'Đã tạm ngưng mã ' . $referralCode->code . ' (' . $referralCode->name . ').');
    }

    public function showReferrer(ReferralCode $referralCode)
    {
        $this->referralCodes->restoreReferrer($referralCode);

        return redirect()->route('admin.referrals', $this->qrCodesRedirectParams())
            ->with('success', 'Mã ' . $referralCode->code . ' đã chuyển sang trạng thái sử dụng.');
    }

    public function destroyReferralCode(ReferralCode $referralCode)
    {
        $code = $referralCode->code;
        $this->referralCodes->deleteBookingReferralCode($referralCode);

        return redirect()->route('admin.referrals', $this->qrCodesRedirectParams())
            ->with('success', 'Đã xóa mã ' . $code . '.');
    }
}
