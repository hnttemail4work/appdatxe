<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\DriverWalletTransaction;
use App\Models\PlatformSetting;
use App\Models\ReferralCode;
use App\Models\TripRoute;
use App\Services\LaterReturnBookingService;
use App\Services\AdminRevenueService;
use App\Services\BookingWorkflowService;
use App\Services\DriverTripRequestService;
use App\Services\DriverWalletService;
use App\Services\ReferralCodeService;
use App\Services\ScheduleLifecycleService;
use InvalidArgumentException;
use App\Support\AppBrandingSettings;
use App\Support\BookingPageSettings;
use App\Support\LocationCatalog;
use App\Support\PageList;
use App\Support\ProvinceCenters;
use App\Support\RouteDistanceCatalog;
use App\Support\PlatformFees;
use App\Support\PushNotificationSettings;
use App\Support\VehicleCapacityOptions;
use App\Support\VehicleCapacityPricing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function __construct(
        private readonly ScheduleLifecycleService $scheduleLifecycle,
        private readonly ReferralCodeService $referralCodes,
        private readonly DriverWalletService $driverWallet,
        private readonly DriverTripRequestService $tripRequests,
        private readonly BookingWorkflowService $workflow,
        private readonly AdminRevenueService $revenue,
        private readonly LaterReturnBookingService $laterReturnBookings,
    ) {
    }

    public function dashboard()
    {
        $this->scheduleLifecycle->sync();

        $referralCodes = ReferralCode::query()
            ->with('booking')
            ->orderByRaw("CASE WHEN type = 'referrer' AND status = 'suspended' THEN 1 ELSE 0 END")
            ->latest()
            ->paginate(PageList::PER_PAGE, ['*'], 'referrals_page')
            ->withQueryString();

        $referralCommissionStats = $this->referralCodes->commissionStatsForReferralIds(
            $referralCodes->getCollection()
                ->where('type', ReferralCode::TYPE_REFERRER)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all(),
        );

        $feeSettings = [
            'app_commission'           => PlatformFees::appCommissionPercent(),
            'referral_commission_first'  => PlatformFees::referralCommissionFirstPercent(),
            'referral_commission_repeat' => PlatformFees::referralCommissionRepeatPercent(),
            'round_trip_discount'      => PlatformFees::roundTripDiscountPercent(),
            'km_rate_under_100'   => PlatformFees::kmRateUnder100(),
            'km_rate_over_100'    => PlatformFees::kmRateOver100(),
            'departure_plan_surcharge_today' => PlatformFees::departurePlanTodaySurchargePercent(),
            'departure_plan_surcharge_tomorrow' => PlatformFees::departurePlanTomorrowSurchargePercent(),
            'departure_plan_surcharge_later_per_day' => PlatformFees::departurePlanLaterPercentPerDay(),
            'vehicle_capacity'    => VehicleCapacityPricing::settingsForAdmin(),
            'vehicleCapacityEnabled' => VehicleCapacityOptions::enabled(),
            'vehicleCapacityKnown'   => VehicleCapacityOptions::knownCapacities(),
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
            'referralCodes',
            'referralCommissionStats',
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

    public function updateReferrer(Request $request, ReferralCode $referralCode)
    {
        if ($referralCode->type !== ReferralCode::TYPE_REFERRER) {
            abort(403);
        }

        $validated = $request->validate([
            'commission_percent'         => ['required', 'numeric', 'min:0', 'max:100'],
            'customer_discount_percent'  => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $referralCode->update([
            'commission_percent'        => (float) $validated['commission_percent'],
            'customer_discount_percent' => (float) $validated['customer_discount_percent'],
        ]);

        return redirect()->route('admin.dashboard', ['tab' => 'referrals'])
            ->with('success', 'Đã cập nhật mã ' . $referralCode->code . ' — giảm giá ' . number_format($validated['customer_discount_percent'], 1) . '%, hoa hồng ' . number_format($validated['commission_percent'], 1) . '%.');
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

    public function destroyReferralCode(ReferralCode $referralCode)
    {
        $code = $referralCode->code;
        $this->referralCodes->deleteBookingReferralCode($referralCode);

        return redirect()->route('admin.dashboard', ['tab' => 'referrals'])
            ->with('success', 'Đã xóa mã ' . $code . '.');
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

        return redirect()->route('admin.dashboard', ['tab' => 'settings'])
            ->with('success', 'Đã lưu tài khoản ngân hàng — QR VietQR tự sinh khi tài xế nạp ví / đóng phí.');
    }

    public function updateBookingPageSettings(Request $request)
    {
        $validated = $request->validate([
            'hero_title'    => ['nullable', 'string', 'max:120'],
            'banner'        => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:2048'],
            'remove_banner' => ['nullable', 'boolean'],
        ]);

        BookingPageSettings::saveHeroTitle((string) ($validated['hero_title'] ?? ''));

        if ($request->boolean('remove_banner')) {
            BookingPageSettings::removeBanner();
        } elseif ($request->hasFile('banner')) {
            BookingPageSettings::storeBanner($request->file('banner'));
        }

        return redirect()->route('admin.dashboard', ['tab' => 'appearance'])
            ->with('success', 'Đã lưu cài đặt trang đặt xe.');
    }

    public function updateBrandingSettings(Request $request)
    {
        $validated = $request->validate([
            'app_name'       => ['nullable', 'string', 'max:80'],
            'brand_title'    => ['nullable', 'string', 'max:40'],
            'brand_tagline'  => ['nullable', 'string', 'max:80'],
        ]);

        AppBrandingSettings::saveBranding(
            (string) ($validated['app_name'] ?? ''),
            (string) ($validated['brand_title'] ?? ''),
            (string) ($validated['brand_tagline'] ?? ''),
        );

        return redirect()->route('admin.dashboard', ['tab' => 'appearance'])
            ->with('success', 'Đã lưu thương hiệu.');
    }

    public function updatePushSettings(Request $request)
    {
        $validated = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'events'  => ['nullable', 'array'],
            'events.*'=> ['string', 'max:64'],
            'vapid_public'  => ['nullable', 'string', 'max:255'],
            'vapid_private' => ['nullable', 'string', 'max:255'],
            'vapid_subject' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['enabled'] = $request->boolean('enabled');
        PushNotificationSettings::saveFromAdmin($validated);

        return redirect()->route('admin.dashboard', ['tab' => 'appearance'])
            ->with('success', 'Đã lưu cài đặt thông báo đẩy.');
    }

    public function updateFeeSettings(Request $request)
    {
        $knownCapacities = VehicleCapacityOptions::knownCapacities();
        $capacityRules = [];
        foreach ($knownCapacities as $capacity) {
            $capacityRules['capacity_percents.' . $capacity] = ['nullable', 'numeric', 'min:50', 'max:500'];
        }

        $validated = $request->validate(array_merge([
            'app_commission'        => ['required', 'numeric', 'min:0', 'max:100'],
            'referral_commission_first'  => ['required', 'numeric', 'min:0', 'max:100'],
            'referral_commission_repeat' => ['required', 'numeric', 'min:0', 'max:100'],
            'round_trip_discount'   => ['required', 'numeric', 'min:0', 'max:100'],
            'km_rate_under_100'   => ['required', 'integer', 'min:0'],
            'km_rate_over_100'    => ['required', 'integer', 'min:0'],
            'departure_plan_surcharge_today' => ['required', 'numeric', 'min:0', 'max:500'],
            'departure_plan_surcharge_tomorrow' => ['required', 'numeric', 'min:0', 'max:500'],
            'departure_plan_surcharge_later_per_day' => ['required', 'numeric', 'min:0', 'max:500'],
            'capacity_step_percent' => ['required', 'numeric', 'min:0', 'max:50'],
            'capacity_percents'   => ['nullable', 'array'],
            'capacity_enabled'    => ['nullable', 'array'],
            'capacity_enabled.*'  => ['integer', 'min:1', 'max:60'],
            'capacity_custom_add' => ['nullable', 'integer', 'min:1', 'max:60'],
        ], $capacityRules));

        $enabled = collect($validated['capacity_enabled'] ?? [])
            ->map(fn ($value): int => (int) $value)
            ->filter(fn (int $value): bool => $value >= 1 && $value <= 60)
            ->values()
            ->all();

        $customAdd = (int) ($validated['capacity_custom_add'] ?? 0);
        if ($customAdd >= 1 && $customAdd <= 60) {
            $enabled[] = $customAdd;
        }

        VehicleCapacityOptions::saveEnabled($enabled);

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

        PlatformSetting::setValue('departure_plan_surcharge_today_percentage', [
            'value' => (float) $validated['departure_plan_surcharge_today'],
        ], 'finance');

        PlatformSetting::setValue('departure_plan_surcharge_tomorrow_percentage', [
            'value' => (float) $validated['departure_plan_surcharge_tomorrow'],
        ], 'finance');

        PlatformSetting::setValue('departure_plan_surcharge_later_per_day_percentage', [
            'value' => (float) $validated['departure_plan_surcharge_later_per_day'],
        ], 'finance');

        VehicleCapacityPricing::save(
            (float) $validated['capacity_step_percent'],
            $validated['capacity_percents'] ?? [],
        );

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

    public function bookings(Request $request)
    {
        $data = $this->bookingsListData($request);

        return view('admin.bookings', $data);
    }

    public function bookingsSync(Request $request)
    {
        $data = $this->bookingsListData($request);
        $bookingList = $data['bookingList'];

        return response()->json([
            'list'                     => $bookingList,
            'counts'                   => $data['bookingListCounts'],
            'catalog_off_duty_count'   => $data['catalogOffDutyBookingCount'],
            'late_pickup_alert_count'  => $data['latePickupAlertCount'],
            'html'                     => view('partials.admin-booking-list-table', [
                'bookings'          => $data['passengers'],
                'drivers'           => $data['drivers'],
                'showBulkDelete'    => $bookingList === 'cancelled',
                'bookingList'       => $bookingList,
                'showAssignActions' => false,
                'showWaitingColumn' => $bookingList === 'active',
            ])->render(),
            'synced_at'                => now()->format('H:i:s'),
        ]);
    }

    /** @return array<string, mixed> */
    private function bookingsListData(Request $request): array
    {
        $this->scheduleLifecycle->sync();
        $this->tripRequests->expireStale();

        $bookingList = in_array($request->query('list'), ['active', 'completed', 'feedback', 'cancelled'], true)
            ? $request->query('list')
            : 'active';

        $bookingBase = fn () => Booking::query()->visibleOnOperatorDashboard();

        $bookingEagerLoad = [
            'schedule.route',
            'schedule.vehicle',
            'schedule.template',
            'schedule.driver.driverProfile.user',
            'schedule.driverTripRequests',
            'appliedReferralCode',
            'tripReview',
            'cancellationReason',
        ];

        $activeAll = $bookingBase()
            ->operatorListBucket('active')
            ->with($bookingEagerLoad)
            ->latest()
            ->get();

        $waitingBookings = $activeAll
            ->filter(fn (Booking $booking): bool => $booking->needsAdminWaitingAttention())
            ->values();

        $activeBookings = $activeAll
            ->reject(fn (Booking $booking): bool => $booking->needsAdminWaitingAttention())
            ->values();

        $sortedActiveBookings = $waitingBookings->concat($activeBookings)->values();

        $bookingListCounts = [
            'active'    => $activeAll->count(),
            'completed' => $bookingBase()->operatorListBucket('completed')->count(),
            'feedback'  => $bookingBase()->operatorListBucket('feedback')->count(),
            'cancelled' => $bookingBase()->operatorListBucket('cancelled')->count(),
        ];

        if ($bookingList === 'active') {
            $passengers = PageList::paginateCollection($sortedActiveBookings, $request);
        } else {
            $passengers = $bookingBase()
                ->operatorListBucket($bookingList)
                ->with($bookingEagerLoad)
                ->latest()
                ->paginate(PageList::PER_PAGE)
                ->withQueryString();
        }

        $drivers = DriverProfile::query()
            ->operational()
            ->with('user')
            ->get();

        $pendingDriverCount = (int) DriverProfile::query()->pendingApproval()->count();
        $pendingSettleCount = DriverWalletTransaction::query()
            ->where('type', 'deposit')
            ->where('status', 'pending')
            ->count();

        $catalogOffDutyBookingCount = $waitingBookings
            ->filter(fn (Booking $booking): bool => $booking->catalogDriverOffDutyAlert())
            ->count();

        $latePickupAlertCount = $activeAll
            ->filter(fn (Booking $booking): bool => $booking->adminPickupAlert() !== null)
            ->count();

        return compact(
            'drivers',
            'passengers',
            'pendingDriverCount',
            'pendingSettleCount',
            'bookingList',
            'bookingListCounts',
            'catalogOffDutyBookingCount',
            'latePickupAlertCount',
        );
    }

    public function revenueReport(Request $request)
    {
        $this->scheduleLifecycle->sync();

        return view('admin.revenue', [
            'summary'         => $this->revenue->completedTripsSummary(),
            'referrerRows'    => $this->revenue->referrerSummaryRows(),
            'completedTrips'  => $this->revenue->paginatedCompletedTrips($request),
        ]);
    }

    public function driverWallet(Request $request)
    {
        $this->driverWallet->enforceDeadlines();

        $depositsPendingAll = DriverWalletTransaction::query()
            ->with(['wallet.driverProfile.user'])
            ->where('type', 'deposit')
            ->where('status', 'pending')
            ->latest()
            ->get();

        $walletHistoryAll = DriverWalletTransaction::query()
            ->with(['wallet.driverProfile.user'])
            ->where('type', 'deposit')
            ->latest()
            ->limit(80)
            ->get()
            ->map(fn (DriverWalletTransaction $transaction) => [
                'kind'            => 'deposit',
                'amount'          => (int) $transaction->amount,
                'at'              => $transaction->created_at,
                'label'           => DriverWalletTransaction::historyLabelFor($transaction->status),
                'meta'            => match ($transaction->status) {
                    'approved' => 'Duyệt ' . ($transaction->approved_at?->format('d/m/Y H:i') ?? '—'),
                    'rejected' => 'Từ chối ' . ($transaction->approved_at?->format('d/m/Y H:i') ?? '—'),
                    default    => 'Gửi ' . $transaction->created_at->format('d/m/Y H:i'),
                },
                'status'          => $transaction->status,
                'driver_name'     => $transaction->wallet->driverProfile->user->name ?? '—',
                'driver_code'     => $transaction->wallet->driverProfile->driver_code ?? null,
                'proof_image_url' => $transaction->proofImageUrl(),
                'reference'       => $transaction->depositReference(),
            ]);

        $depositsPending = PageList::paginateCollection($depositsPendingAll, $request, 'deposit_page');
        $walletHistory = PageList::paginateCollection($walletHistoryAll, $request, 'history_page');

        $counts = [
            'deposits'    => $depositsPendingAll->count(),
            'settlements' => 0,
            'total'       => $depositsPendingAll->count(),
        ];

        return view('admin.driver-wallet', compact(
            'depositsPending',
            'walletHistory',
            'counts',
        ));
    }

    public function bulkDismissBookings(Request $request)
    {
        $validated = $request->validate([
            'booking_ids'   => ['required', 'array', 'min:1'],
            'booking_ids.*' => ['integer', 'distinct'],
        ], [
            'booking_ids.required' => 'Vui lòng chọn ít nhất một đơn.',
            'booking_ids.min'      => 'Vui lòng chọn ít nhất một đơn.',
        ]);

        if (! Booking::supportsOperatorDismiss()) {
            return back()->withErrors(['booking_ids' => 'Hệ thống chưa cập nhật migration — liên hệ quản trị.']);
        }

        $updated = Booking::query()
            ->whereIn('id', array_map('intval', $validated['booking_ids']))
            ->where(function ($q): void {
                $q->whereIn('booking_status', ['cancelled', 'rejected'])
                    ->orWhere('trip_status', 'cancelled');
            })
            ->update(['operator_dismissed_at' => now()]);

        if ($updated < 1) {
            return back()->withErrors(['booking_ids' => 'Không có đơn hủy hợp lệ để xóa.']);
        }

        return redirect()->route('admin.bookings', ['list' => 'cancelled'])
            ->with('success', "Đã xóa {$updated} đơn hủy khỏi danh sách.");
    }

    public function approveDeposit(DriverWalletTransaction $transaction)
    {
        try {
            $this->driverWallet->approveDeposit($transaction, Auth::id());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['wallet' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.driverWallet')
            ->with('success', 'Đã cộng tiền vào ví tài xế.');
    }

    public function rejectDeposit(DriverWalletTransaction $transaction)
    {
        try {
            $this->driverWallet->rejectDeposit($transaction, Auth::id());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['wallet' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.driverWallet')
            ->with('success', 'Đã từ chối yêu cầu nạp ví.');
    }

    public function approveDepositsBulk(Request $request)
    {
        $validated = $request->validate([
            'transaction_ids'   => ['required', 'array', 'min:1'],
            'transaction_ids.*' => ['integer', 'distinct', 'exists:driver_wallet_transactions,id'],
        ], [
            'transaction_ids.required' => 'Vui lòng chọn ít nhất một đơn nạp.',
            'transaction_ids.min'      => 'Vui lòng chọn ít nhất một đơn nạp.',
        ]);

        $result = $this->driverWallet->approveDepositsBulk(
            array_map('intval', $validated['transaction_ids']),
            Auth::id(),
        );

        if ($result['approved'] < 1) {
            return back()->withErrors(['wallet' => 'Không có đơn nạp hợp lệ để duyệt.']);
        }

        $message = "Đã duyệt {$result['approved']} đơn nạp và cộng ví.";
        if ($result['skipped'] > 0) {
            $message .= " ({$result['skipped']} đơn bỏ qua — đã xử lý hoặc không hợp lệ.)";
        }

        return redirect()
            ->route('admin.driverWallet')
            ->with('success', $message);
    }

    public function confirmAndAssignBooking(Request $request, Booking $booking)
    {
        $this->scheduleLifecycle->sync();
        $this->tripRequests->expireStale();

        $booking->loadMissing(['schedule.route', 'schedule.vehicle']);

        if ($booking->passengerPickedUp()) {
            return back()->withErrors(['driver_code' => 'Tài xế đã đón khách — không thể gán lại.']);
        }

        if (! $booking->hasDriverAccepted()) {
            $validated = $request->validate([
                'driver_code' => ['required', 'string', 'max:20'],
            ]);

            $driverCode = strtoupper(trim($validated['driver_code']));

            try {
                $this->tripRequests->requestDriver(
                    $booking->schedule->fresh(['route']),
                    $driverCode,
                    (string) $booking->contact_phone,
                );
            } catch (InvalidArgumentException $e) {
                return back()->withErrors(['driver_code' => $e->getMessage()])->withInput();
            }

            return back()->with('success', 'Đã giao chuyến cho tài xế mới — chờ xác nhận trong 15 phút.');
        }

        if ($booking->hasDriverAccepted() && $booking->schedule->departure_time > now()) {
            $validated = $request->validate([
                'driver_code' => ['required', 'string', 'max:20'],
            ]);

            $driverCode = strtoupper(trim($validated['driver_code']));

            try {
                $this->tripRequests->reassignScheduleDriver(
                    $booking->schedule->fresh(['route', 'vehicle', 'bookings']),
                    $driverCode,
                    Auth::id(),
                );
            } catch (InvalidArgumentException $e) {
                return back()->withErrors(['driver_code' => $e->getMessage()])->withInput();
            }

            $profile = DriverProfile::query()
                ->where('driver_code', $driverCode)
                ->with('user')
                ->first();

            return back()->with('success', 'Đã gán lại tài xế — chờ xác nhận trong 15 phút.');
        }

        return back()->with('success', 'Chuyến đã có tài xế nhận.');
    }

    public function cancelBooking(Booking $booking)
    {
        $this->scheduleLifecycle->sync();
        $this->tripRequests->expireStale();

        $booking->loadMissing(['schedule.route', 'schedule.vehicle']);

        try {
            $this->workflow->cancelByAdmin($booking, (int) Auth::id());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.bookings', ['list' => 'cancelled'])
            ->with('success', 'Đã hủy chuyến — khách và tài xế không còn thấy trên app.');
    }

    public function dispatchLaterReturnPickup(Booking $booking)
    {
        $this->scheduleLifecycle->sync();
        $this->tripRequests->expireStale();

        $booking->loadMissing(['schedule.template', 'schedule.route', 'schedule.vehicle']);

        try {
            $returnBooking = $this->laterReturnBookings->dispatchReturnPickup($booking);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.bookings', ['list' => 'active'])
            ->with('success', 'Đã tạo chuyến đón khách về — mã ' . ($returnBooking->booking_reference ?? '—') . '.');
    }
}
