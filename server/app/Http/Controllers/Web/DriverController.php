<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\DriverProfile;
use App\Support\ProvinceResolver;
use App\Models\DriverTripRequest;
use App\Models\Schedule;
use App\Support\DriverFieldRules;
use App\Services\BookingWorkflowService;
use App\Services\DriverAvailabilityService;
use App\Services\DriverCancelRateService;
use App\Services\DriverLatePickupService;
use App\Services\DriverMissedTripService;
use App\Services\DriverPhotoService;
use App\Services\DriverProfileSyncService;
use App\Services\DriverProximityService;
use App\Services\GuestBookingDriverStatusService;
use App\Services\DriverTripRequestService;
use App\Services\DriverWalletService;
use App\Services\ScheduleLifecycleService;
use App\Support\PageList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class DriverController extends Controller
{
    public function __construct(
        private readonly DriverPhotoService $photoService,
        private readonly DriverProfileSyncService $profileSync,
        private readonly BookingWorkflowService $workflow,
        private readonly ScheduleLifecycleService $scheduleLifecycle,
        private readonly DriverTripRequestService $driverRequests,
        private readonly DriverAvailabilityService $driverAvailability,
        private readonly DriverMissedTripService $missedTrips,
        private readonly DriverCancelRateService $cancelRates,
        private readonly DriverWalletService $driverWallet,
        private readonly DriverLatePickupService $latePickup,
        private readonly DriverProximityService $proximity,
        private readonly GuestBookingDriverStatusService $guestDriverStatus,
    ) {
    }

    public function myDashboard(Request $request)
    {
        $this->scheduleLifecycle->sync();
        $this->driverRequests->expireStale();
        $this->driverRequests->retryWaitingBookingsWithoutExpire();
        $this->driverWallet->enforceDeadlines();

        $user    = Auth::user();
        $profile = DriverProfile::query()->where('user_id', $user->id)->with('operator')->first();
        $driverWallet = null;
        $walletNotice = null;

        if ($profile) {
            $this->driverAvailability->enforceWebPresenceIdleFor($profile);
            $this->driverAvailability->syncAfterTripCompleted((int) $profile->user_id);
            $profile = $profile->fresh(['operator']);
            if (($profile->availability_status ?? 'off_duty') === 'available') {
                $this->driverAvailability->touchWebPresence((int) $profile->user_id);
            }
            $driverWallet = $this->driverWallet->reconcileWallet($profile);
            $walletNotice = $this->driverWallet->walletNoticeForDriver($profile);
        }

        $walletBlockReason = $profile ? $this->driverWallet->acceptBlockReason($profile) : null;
        if ($driverWallet) {
            $driverWallet->load([
                'settlements.schedule.route',
                'settlements.schedule.bookings',
                'settlements.booking.schedule.route',
                'transactions' => fn ($q) => $q->where('type', 'deposit')->latest(),
            ]);
        }

        $tripSchedulesAll = Schedule::query()
            ->with([
                'route',
                'vehicle',
                'tripSettlement',
                'bookings' => fn ($q) => $q->orderByDesc('id'),
            ])
            ->forDriverActiveTrips($user->id)
            ->get()
            ->filter(fn (Schedule $schedule): bool => $schedule->driverRelevantBookings()->isNotEmpty()
                && $schedule->driverWorkflowPhase() !== 'settled'
                && $schedule->isVisibleOnDriverDashboard())
            ->sortBy(fn (Schedule $schedule): string => $schedule->driverViewSortKey())
            ->values();

        $tripSchedules = PageList::paginateCollection($tripSchedulesAll, $request, 'trips_page');

        $tripHistoryAll = Schedule::query()
            ->forDriverHistory($user->id)
            ->with([
                'route',
                'vehicle',
                'bookings' => fn ($q) => $q->with('cancellationReason')->orderByDesc('id'),
            ])
            ->orderByDesc('departure_time')
            ->limit(200)
            ->get()
            ->filter(fn (Schedule $schedule): bool => $schedule->driverHistoryBookingsFor($user->id)->isNotEmpty())
            ->values();

        $tripHistory = PageList::paginateCollection($tripHistoryAll, $request, 'history_page');

        $tripActionCount = $this->driverRequests->tripActionCountForDriver($user->id);
        $tripFlags = $this->driverAvailability->driverDashboardTripFlags($user->id);
        $driverTripActive = $tripFlags['active'];
        $driverTripUpcoming = $tripFlags['upcoming'];
        $driverOnTrip = $driverTripActive;

        $pendingTripRequestGroups = $this->driverRequests->visiblePendingGroupsForDriver($user->id);

        $showTopUpBanner = $walletNotice !== null;
        $revenueStats = $profile
            ? $this->driverWallet->driverRevenueStats($profile)
            : ['day' => 0, 'week' => 0];
        $walletHistoryAll = $driverWallet
            ? $this->driverWallet->walletActivityHistory($driverWallet, depositsOnly: true)
            : collect();
        $walletHistory = PageList::paginateCollection($walletHistoryAll, $request, 'history_page');

        return view('driver.dashboard', compact(
            'user',
            'profile',
            'walletBlockReason',
            'driverWallet',
            'walletHistory',
            'showTopUpBanner',
            'walletNotice',
            'revenueStats',
            'tripSchedules',
            'tripActionCount',
            'driverOnTrip',
            'driverTripActive',
            'driverTripUpcoming',
            'tripHistory',
            'pendingTripRequestGroups',
        ));
    }

    public function advanceSchedule(Request $request, Schedule $schedule)
    {
        try {
            $stage = $this->workflow->driverAdvanceScheduleStage($schedule, Auth::id());
        } catch (InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        $label = $schedule->fresh()->driverStageLabel($stage);
        $message = "Đã cập nhật: {$label}.";

        if ($request->expectsJson()) {
            return response()->json([
                'ok'       => true,
                'redirect' => route('driver.dashboard', ['tab' => 'trips']),
                'message'  => $message,
            ]);
        }

        return redirect()->route('driver.dashboard', ['tab' => 'trips'])->with('success', $message);
    }

    public function acceptTripRequest(Request $request, DriverTripRequest $driverTripRequest)
    {
        try {
            $this->driverRequests->accept($driverTripRequest, (int) Auth::id());
        } catch (InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['driver_request' => $e->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok'       => true,
                'redirect' => route('driver.dashboard', ['tab' => 'trips']),
                'message'  => 'Đã nhận chuyến. Xem tab Chuyến đang chạy để theo dõi.',
            ]);
        }

        return redirect()->route('driver.dashboard', ['tab' => 'trips'])->with('success', 'Đã nhận chuyến. Xem tab Xem chuyến để theo dõi.');
    }

    public function rejectTripRequest(Request $request, DriverTripRequest $driverTripRequest)
    {
        try {
            $this->driverRequests->reject($driverTripRequest, (int) Auth::id());
        } catch (InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['driver_request' => $e->getMessage()]);
        }

        $profile = DriverProfile::query()->where('user_id', Auth::id())->first();

        if ($request->expectsJson()) {
            return response()->json([
                'ok'                  => true,
                'availability'        => $profile?->availability_status ?? 'off_duty',
                'message'             => 'Đã từ chối cuốc — đã tắt Sẵn sàng.',
            ]);
        }

        return redirect()->route('driver.dashboard', ['tab' => 'trips'])->with('success', 'Đã từ chối yêu cầu nhận chuyến.');
    }

    public function updateLocation(Request $request)
    {
        $validated = $request->validate([
            'lat'     => ['required', 'numeric', 'between:-90,90'],
            'lng'     => ['required', 'numeric', 'between:-180,180'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $profile = DriverProfile::query()->where('user_id', Auth::id())->firstOrFail();

        if (($profile->availability_status ?? 'off_duty') === 'off_duty') {
            return response()->json([
                'message' => 'Bật «Sẵn sàng» trước khi cập nhật vị trí.',
            ], 422);
        }

        $address = trim((string) ($validated['address'] ?? ''));

        $profile->update([
            'last_lat'         => $validated['lat'],
            'last_lng'         => $validated['lng'],
            'last_location_at' => now(),
            'last_address'     => $address !== '' ? $address : null,
            'last_province'    => ProvinceResolver::fromMapPick(
                (float) $validated['lat'],
                (float) $validated['lng'],
                $address !== '' ? $address : null,
            ),
        ]);

        $this->driverAvailability->syncAfterTripCompleted((int) $profile->user_id);
        $profile = $profile->fresh();

        $this->driverAvailability->touchWebPresence((int) $profile->user_id);

        $this->driverRequests->expireStale();
        $assigned = $this->driverRequests->resumeDriverMatchingAfterAvailability($profile->fresh());
        $pickupDistances = $this->proximity->refreshAssignedPickupDistances($profile);
        $proximityPayload = $this->firstAssignedPickupPayload((int) $profile->user_id);

        if (! empty($proximityPayload['distance_label'])) {
            $schedule = $this->driverAvailability->activeSchedulesForDriver((int) $profile->user_id)->first();
            $booking = $schedule?->driverRelevantBookings()->first();
            if ($booking && ($schedule->resolvedDriverStage() ?? '') === Schedule::DRIVER_STAGE_ASSIGNED) {
                try {
                    app(\App\Services\PushNotificationService::class)->onDriverEnRoute(
                        $booking,
                        (string) $proximityPayload['distance_label'],
                    );
                } catch (\Throwable) {
                }
            }
        }

        return response()->json([
            'ok'                    => true,
            'address'               => $address !== '' ? $address : null,
            'updated_at'            => $profile->last_location_at?->format('H:i, d/m/Y'),
            'assigned'              => $assigned,
            'pickup_distances'      => $pickupDistances,
            'pickup_distance_label' => $proximityPayload['distance_label'] ?? ($pickupDistances[0]['distance_label'] ?? null),
            'pickup_eta_label'      => $proximityPayload['eta_label'] ?? null,
            'pickup_proximity_hint' => $proximityPayload['proximity_hint'] ?? null,
        ]);
    }

    /** @return array<string, mixed> */
    private function firstAssignedPickupPayload(int $driverUserId): array
    {
        $schedule = $this->driverAvailability->activeSchedulesForDriver($driverUserId)->first();
        if (! $schedule) {
            return [];
        }

        $booking = $schedule->driverRelevantBookings()->first();
        if (! $booking) {
            return [];
        }

        return $this->guestDriverStatus->build($booking) ?? [];
    }

    public function updateAvailability(Request $request)
    {
        $validated = $request->validate([
            'availability_status' => ['required', Rule::in(['available', 'off_duty'])],
        ]);

        $profile = DriverProfile::query()->where('user_id', Auth::id())->firstOrFail();

        if ($validated['availability_status'] === 'off_duty'
            && $this->driverAvailability->activeTripCount((int) $profile->user_id) > 0) {
            $message = 'Đang có chuyến — hoàn thành chuyến trước khi tạm nghỉ.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->withErrors(['availability' => $message]);
        }

        if ($validated['availability_status'] === 'off_duty') {
            $this->driverAvailability->markOffDuty($profile);
        } else {
            $this->driverAvailability->markAvailable($profile->fresh());
            $this->driverRequests->resumeDriverMatchingAfterAvailability($profile->fresh());
        }

        $profile = $profile->fresh();
        $pendingTripRequestCount = $this->driverRequests->visiblePendingGroupsForDriver((int) $profile->user_id)->count();

        if ($request->expectsJson()) {
            return response()->json([
                'ok'                    => true,
                'availability_status'   => $profile->availability_status,
                'location_cleared'      => $validated['availability_status'] === 'off_duty',
                'pending_trip_requests' => $pendingTripRequestCount,
                'needs_location'        => $validated['availability_status'] === 'available',
            ]);
        }

        return redirect()->route('driver.dashboard')->with(
            'success',
            $validated['availability_status'] === 'off_duty'
                ? 'Đã tạm nghỉ — vị trí đã được xóa.'
                : 'Đã bật sẵn sàng.',
        );
    }

    /** Tài xế báo hoàn thành chuyến — tất cả vé trên cùng chuyến xe. */
    public function completeTrip(Request $request, Booking $booking)
    {
        try {
            $count = $this->workflow->driverCompleteTrip($booking, Auth::id());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        $message = $count > 1
            ? "Đã hoàn thành chuyến ({$count} vé)."
            : 'Đã hoàn thành chuyến.';

        return redirect()->route('driver.dashboard')->with('success', $message);
    }

    public function completeSchedule(Request $request, Schedule $schedule)
    {
        try {
            $count = $this->workflow->driverCompleteSchedule($schedule, Auth::id());
        } catch (InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        $message = $count > 1
            ? "Đã hoàn thành chuyến ({$count} vé)."
            : 'Đã hoàn thành chuyến.';

        if ($request->expectsJson()) {
            return response()->json([
                'ok'       => true,
                'redirect' => route('driver.dashboard', ['tab' => 'trips']),
                'message'  => $message,
            ]);
        }

        return redirect()->route('driver.dashboard')->with('success', $message);
    }

    public function cancelSchedule(Request $request, Schedule $schedule)
    {
        $validated = $request->validate([
            'cancellation_reason_id' => ['required', 'integer', 'exists:cancellation_reasons,id'],
        ]);

        try {
            $this->workflow->cancelScheduleByDriver(
                $schedule,
                Auth::id(),
                (int) $validated['cancellation_reason_id'],
            );
        } catch (InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        $profile = DriverProfile::query()->where('user_id', Auth::id())->first();
        $message = ($profile && $profile->isMissedTripLocked())
            ? 'Đã hủy chuyến. Tài khoản đã bị khóa do hủy 3 lần liên tiếp trong ngày — liên hệ quản lý để mở khóa.'
            : 'Đã hủy chuyến. Quản lý sẽ được thông báo qua hệ thống.';

        if ($request->expectsJson()) {
            return response()->json([
                'ok'       => true,
                'redirect' => route('driver.dashboard', ['tab' => 'trips']),
                'message'  => $message,
            ]);
        }

        return redirect()->route('driver.dashboard')->with('success', $message);
    }

    public function latePickupContinue(Request $request, Schedule $schedule)
    {
        try {
            $this->latePickup->confirmContinue($schedule, Auth::id());
        } catch (InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('driver.dashboard')->with('success', 'Đã xác nhận — tiếp tục đến điểm đón.');
    }

    public function index(Request $request)
    {
        $filter = in_array($request->query('filter'), ['pending', 'rejected'], true)
            ? $request->query('filter')
            : 'all';

        $query = DriverProfile::query()
            ->with(['user', 'operator', 'wallet']);

        if (Auth::user()->role === 'admin') {
            if ($filter === 'pending') {
                $query->pendingApproval();
            } elseif ($filter === 'rejected') {
                $query->where('approval_status', 'rejected')
                    ->whereHas('user', fn ($q) => $q->where('role', 'driver'));
            }
        } else {
            $query->forOperatorManagement(Auth::id());
            if ($filter === 'pending') {
                $query->pendingForOperator(Auth::id());
            } elseif ($filter === 'rejected') {
                $query->where('approval_status', 'rejected')
                    ->whereHas('user', fn ($q) => $q->where('role', 'driver'));
            }
        }

        $drivers = PageList::paginateCollection(
            $query->latest()->get()
                ->when($filter === 'all', fn ($c) => $c->sortBy(fn ($d) => $d->isPendingApproval() ? 0 : 1)->values())
                ->when($filter !== 'all', fn ($c) => $c->values()),
            $request,
        );

        $this->driverAvailability->reconcileMany($drivers->items());
        foreach ($drivers->items() as $driver) {
            $driver->refresh();
        }

        $pendingCount = Auth::user()->role === 'admin'
            ? (int) DriverProfile::query()->pendingApproval()->count()
            : DriverProfile::pendingCountForOperator(Auth::id());

        $statsMonth = now()->startOfMonth();
        $driverMonthlyStats = [];

        if ($filter === 'all') {
            $monthInput = trim((string) $request->query('month', ''));
            if ($monthInput !== '' && preg_match('/^\d{4}-\d{2}$/', $monthInput) === 1) {
                try {
                    $statsMonth = \Carbon\Carbon::createFromFormat('Y-m', $monthInput)->startOfMonth();
                } catch (\Exception) {
                    $statsMonth = now()->startOfMonth();
                }
            }

            $driverMonthlyStats = $this->driverWallet->monthlyStatsForDrivers($drivers->items(), $statsMonth);
            $cancelRates = $this->cancelRates->monthlyCancelRatesForDrivers(
                collect($drivers->items())->pluck('user_id')->map(fn ($id) => (int) $id)->all(),
                $statsMonth,
            );

            foreach ($driverMonthlyStats as $userId => &$row) {
                $row['cancel_rate'] = $cancelRates[(int) $userId] ?? 0.0;
            }
            unset($row);
        }

        return view('admin.drivers.index', compact(
            'drivers',
            'filter',
            'pendingCount',
            'statsMonth',
            'driverMonthlyStats',
        ));
    }

    public function edit(Request $request, DriverProfile $driverProfile)
    {
        if (! $this->canManageDriver($driverProfile)) {
            abort(403);
        }

        $this->driverAvailability->syncAfterTripCompleted((int) $driverProfile->user_id);
        $driverProfile->refresh();

        $driverProfile->load(['user', 'operator']);
        $driverWallet = $this->driverWallet->walletFor($driverProfile);
        $driverWallet->load([
            'transactions',
            'settlements.schedule.route',
        ]);
        $pendingDepositsAll = $this->driverWallet->pendingDepositsForDriver($driverProfile);
        $pendingDeposits = PageList::paginateCollection($pendingDepositsAll, $request, 'deposit_page');
        $walletHistoryAll = $this->driverWallet->walletActivityHistory($driverWallet);
        $walletHistory = PageList::paginateCollection($walletHistoryAll, $request, 'history_page');

        return view('admin.drivers.edit', [
            'driver'          => $driverProfile,
            'driverWallet'    => $driverWallet,
            'pendingDeposits' => $pendingDeposits,
            'walletHistory'   => $walletHistory,
        ]);
    }

    public function update(Request $request, DriverProfile $driverProfile)
    {
        if (! $this->canManageDriver($driverProfile)) {
            abort(403);
        }

        if ($driverProfile->isPendingApproval() || $driverProfile->isRejected()) {
            return back()->withErrors(['driver' => 'Hồ sơ đang chờ duyệt hoặc đã bị từ chối — chưa thể chỉnh sửa.']);
        }

        $validated = $request->validate(
            DriverFieldRules::operatorUpdateRules(
                $driverProfile->user_id,
                $driverProfile->id,
                $driverProfile->contactFieldsLocked(),
            ),
        );

        if ($driverProfile->contactFieldsLocked()) {
            unset($validated['name'], $validated['phone']);
        }

        $this->profileSync->fillProfileFromValidated($driverProfile, $validated);
        $this->profileSync->fillUserFromValidated($driverProfile, $validated);

        return redirect()
            ->route('admin.drivers.edit', $driverProfile)
            ->with('success', 'Đã cập nhật thông tin tài xế.');
    }

    public function destroy(DriverProfile $driverProfile)
    {
        if (! $this->canManageDriver($driverProfile)) {
            abort(403);
        }

        $this->profileSync->setAccountStatus($driverProfile, 'inactive');

        return redirect()->route('admin.drivers')->with('success', 'Đã vô hiệu hoá tài xế.');
    }

    public function uploadPhotos(Request $request, DriverProfile $driverProfile)
    {
        if (! $this->canManageDriver($driverProfile)) {
            abort(403);
        }

        if ($driverProfile->isPendingApproval() || $driverProfile->isRejected()) {
            return back()->withErrors(['photos' => 'Hồ sơ đang chờ duyệt hoặc đã bị từ chối — chưa thể thay đổi ảnh.']);
        }

        try {
            $this->photoService->syncPhotos($driverProfile, $request);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['photos' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('admin.drivers.edit', ['driverProfile' => $driverProfile, 'tab' => 'photos'])
            ->with('success', 'Đã cập nhật ảnh tài xế.');
    }

    public function approve(Request $request, DriverProfile $driverProfile)
    {
        if (! $this->canApproveDriver($driverProfile)) {
            abort(403);
        }

        if (! $driverProfile->isPendingApproval()) {
            return back()->withErrors(['driver' => 'Tài xế này đã được duyệt hoặc không còn chờ duyệt.']);
        }

        $this->profileSync->approve($driverProfile, Auth::id());

        return redirect()
            ->route('admin.drivers')
            ->with('success', 'Đã duyệt tài xế — trạng thái Sẵn sàng.');
    }

    public function reject(Request $request, DriverProfile $driverProfile)
    {
        if (! $this->canApproveDriver($driverProfile)) {
            abort(403);
        }

        if (! $driverProfile->isPendingApproval()) {
            return back()->withErrors(['driver' => 'Tài xế này không còn ở trạng thái chờ duyệt.']);
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $this->profileSync->reject($driverProfile, $validated['rejection_reason']);

        return back()->with('success', 'Đã từ chối hồ sơ tài xế.');
    }

    public function clearRejectionNote(DriverProfile $driverProfile)
    {
        if (! $this->canManageDriver($driverProfile)) {
            abort(403);
        }

        if (! $driverProfile->hasRejectionNote()) {
            return back()->withErrors(['driver' => 'Không có ghi chú từ chối để xóa.']);
        }

        $this->profileSync->clearRejectionNote($driverProfile);

        return back()->with('success', 'Đã xóa ghi chú từ chối.');
    }

    public function unlock(DriverProfile $driverProfile)
    {
        if (! $this->canManageDriver($driverProfile)) {
            abort(403);
        }

        if (! $driverProfile->isMissedTripLocked()) {
            return back()->withErrors(['driver' => 'Tài xế không bị khóa do bỏ lỡ chuyến.']);
        }

        $this->missedTrips->unlock($driverProfile);

        return back()->with('success', 'Đã mở khóa tài xế. Số lần bỏ lỡ chuyến đã được reset.');
    }

    public function resetCancelRate(DriverProfile $driverProfile)
    {
        if (! $this->canManageDriver($driverProfile)) {
            abort(403);
        }

        $this->cancelRates->reset($driverProfile);

        return back()->with('success', 'Đã đặt lại tỷ lệ hủy cuốc về 0%.');
    }

    private function canManageDriver(DriverProfile $driverProfile): bool
    {
        return Auth::user()->role === 'admin';
    }

    private function canApproveDriver(DriverProfile $driverProfile): bool
    {
        return Auth::user()->role === 'admin'
            && ($driverProfile->isPendingApproval() || $driverProfile->operator_id === null);
    }
}
