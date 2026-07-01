<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\DriverProfile;
use App\Support\ProvinceResolver;
use App\Models\DriverTripRequest;
use App\Models\Schedule;
use App\Models\ScheduleMergeRequest;
use App\Support\DriverFieldRules;
use App\Services\BookingWorkflowService;
use App\Services\DriverMissedTripService;
use App\Services\DriverPhotoService;
use App\Services\DriverProfileSyncService;
use App\Services\DriverTripRequestService;
use App\Services\DriverWalletService;
use App\Services\ScheduleLifecycleService;
use App\Services\TripConsolidationService;
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
        private readonly DriverMissedTripService $missedTrips,
        private readonly DriverWalletService $driverWallet,
    ) {
    }

    public function myDashboard(Request $request)
    {
        $this->scheduleLifecycle->sync();
        $this->driverRequests->expireStale();
        $this->driverWallet->enforceDeadlines();

        $user    = Auth::user();
        $profile = DriverProfile::query()->where('user_id', $user->id)->with('operator')->first();
        $walletBlockReason = $profile ? $this->driverWallet->acceptBlockReason($profile) : null;
        $driverWallet = $profile ? $this->driverWallet->walletFor($profile) : null;
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

        $tripActionCount = $tripSchedulesAll
            ->filter(fn (Schedule $s): bool => in_array($s->driverWorkflowPhase(), ['upcoming', 'active'], true))
            ->count();

        $pendingMergeRequests = app(TripConsolidationService::class)
            ->pendingMergeRequestsForDriver($user->id);

        $pendingTripRequestGroups = $this->driverRequests->pendingGroupsForDriver($user->id);

        $tripActionCount += $pendingMergeRequests->count();
        $tripActionCount += $pendingTripRequestGroups->count();

        $showTopUpBanner = $profile ? $this->driverWallet->shouldShowTopUpBanner($profile) : false;
        $revenueStats = $profile
            ? $this->driverWallet->driverRevenueStats($profile)
            : ['day' => 0, 'week' => 0];
        $walletHistoryAll = $driverWallet
            ? $this->driverWallet->walletActivityHistory($driverWallet)
            : collect();
        $walletHistory = PageList::paginateCollection($walletHistoryAll, $request, 'history_page');

        return view('driver.dashboard', compact(
            'user',
            'profile',
            'walletBlockReason',
            'driverWallet',
            'walletHistory',
            'showTopUpBanner',
            'revenueStats',
            'tripSchedules',
            'tripActionCount',
            'tripHistory',
            'pendingMergeRequests',
            'pendingTripRequestGroups',
        ));
    }

    public function advanceSchedule(Request $request, Schedule $schedule)
    {
        try {
            $stage = $this->workflow->driverAdvanceScheduleStage($schedule, Auth::id());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        $label = $schedule->fresh()->driverStageLabel($stage);

        return redirect()->route('driver.dashboard', ['tab' => 'trips'])->with('success', "Đã cập nhật: {$label}.");
    }

    public function acceptTripRequest(Request $request, DriverTripRequest $driverTripRequest)
    {
        try {
            $this->driverRequests->accept($driverTripRequest, Auth::id());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['driver_request' => $e->getMessage()]);
        }

        return redirect()->route('driver.dashboard', ['tab' => 'trips'])->with('success', 'Đã nhận chuyến. Xem tab Xem chuyến để theo dõi.');
    }

    public function claimBooking(Request $request, Booking $booking)
    {
        try {
            $this->driverRequests->claimBooking($booking, Auth::id());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['driver_request' => $e->getMessage()]);
        }

        return redirect()->route('driver.dashboard', ['tab' => 'trips'])->with('success', 'Đã nhận cuốc — khách sẽ thấy thông tin tài xế.');
    }

    public function rejectTripRequest(Request $request, DriverTripRequest $driverTripRequest)
    {
        try {
            $this->driverRequests->reject($driverTripRequest, Auth::id());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['driver_request' => $e->getMessage()]);
        }

        return redirect()->route('driver.dashboard', ['tab' => 'trips'])->with('success', 'Đã từ chối yêu cầu nhận chuyến.');
    }

    public function acceptMergeRequest(Request $request, ScheduleMergeRequest $mergeRequest)
    {
        try {
            app(TripConsolidationService::class)->acceptMergeRequest($mergeRequest, Auth::id());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['merge' => $e->getMessage()]);
        }

        return redirect()->route('driver.dashboard', ['tab' => 'trips'])
            ->with('success', 'Đã đồng ý gom chuyến — danh sách khách đã cập nhật trên chuyến của bạn.');
    }

    public function rejectMergeRequest(Request $request, ScheduleMergeRequest $mergeRequest)
    {
        try {
            app(TripConsolidationService::class)->rejectMergeRequest($mergeRequest, Auth::id());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['merge' => $e->getMessage()]);
        }

        return redirect()->route('driver.dashboard', ['tab' => 'trips'])
            ->with('success', 'Đã từ chối gom chuyến — quản lý sẽ xử lý riêng.');
    }

    public function updateLocation(Request $request)
    {
        $validated = $request->validate([
            'lat'     => ['required', 'numeric', 'between:-90,90'],
            'lng'     => ['required', 'numeric', 'between:-180,180'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $profile = DriverProfile::query()->where('user_id', Auth::id())->firstOrFail();

        $address = trim((string) ($validated['address'] ?? ''));

        $profile->update([
            'last_lat'            => $validated['lat'],
            'last_lng'            => $validated['lng'],
            'last_location_at'    => now(),
            'last_address'        => $address !== '' ? $address : null,
            'last_province'       => ProvinceResolver::fromMapPick(
                (float) $validated['lat'],
                (float) $validated['lng'],
                $address !== '' ? $address : null,
            ),
            'availability_status' => ($profile->availability_status ?? 'off_duty') === 'on_trip'
                ? 'on_trip'
                : 'available',
        ]);

        $assigned = $this->driverRequests->retryWaitingBookings();

        return response()->json([
            'ok'         => true,
            'address'    => $address !== '' ? $address : null,
            'updated_at' => $profile->last_location_at?->format('H:i, d/m/Y'),
            'assigned'   => $assigned,
        ]);
    }

    public function updateAvailability(Request $request)
    {
        $validated = $request->validate([
            'availability_status' => ['required', Rule::in(['available', 'on_trip', 'off_duty'])],
        ]);

        $profile = DriverProfile::query()->where('user_id', Auth::id())->firstOrFail();
        $profile->update($validated);

        return redirect()->route('driver.dashboard')->with('success', 'Đã cập nhật trạng thái hoạt động.');
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
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        $message = $count > 1
            ? "Đã hoàn thành chuyến ({$count} vé)."
            : 'Đã hoàn thành chuyến.';

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
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect()->route('driver.dashboard')->with('success', 'Đã hủy chuyến. Quản lý sẽ được thông báo qua hệ thống.');
    }

    public function index(Request $request)
    {
        $filter = in_array($request->query('filter'), ['pending', 'rejected'], true)
            ? $request->query('filter')
            : 'all';

        $query = DriverProfile::query()
            ->with(['user', 'operator'])
            ->forOperatorManagement(Auth::id());

        if ($filter === 'pending') {
            $query->pendingForOperator(Auth::id());
        } elseif ($filter === 'rejected') {
            $query->where('approval_status', 'rejected')
                ->whereHas('user', fn ($q) => $q->where('role', 'driver'));
        }

        $drivers = PageList::paginateCollection(
            $query->latest()->get()
                ->when($filter === 'all', fn ($c) => $c->sortBy(fn ($d) => $d->isPendingApproval() ? 0 : 1)->values())
                ->when($filter !== 'all', fn ($c) => $c->values()),
            $request,
        );

        $pendingCount = DriverProfile::pendingCountForOperator(Auth::id());

        return view('operator.drivers.index', compact('drivers', 'filter', 'pendingCount'));
    }

    public function edit(Request $request, DriverProfile $driverProfile)
    {
        if (! $this->canManageDriver($driverProfile)) {
            abort(403);
        }

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

        return view('operator.drivers.edit', [
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
            ->route('operator.drivers.edit', $driverProfile)
            ->with('success', 'Đã cập nhật thông tin tài xế.');
    }

    public function destroy(DriverProfile $driverProfile)
    {
        if (! $this->canManageDriver($driverProfile)) {
            abort(403);
        }

        $this->profileSync->setAccountStatus($driverProfile, 'inactive');

        return redirect()->route('operator.drivers')->with('success', 'Đã vô hiệu hoá tài xế.');
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
            ->route('operator.drivers.edit', $driverProfile)
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
            ->route('operator.drivers')
            ->with('success', 'Đã duyệt tài xế — trạng thái Sẵn sàng nhận chuyến.');
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

    private function canManageDriver(DriverProfile $driverProfile): bool
    {
        if (Auth::user()->role !== 'operator') {
            return false;
        }

        if ($driverProfile->operator_id === Auth::id()) {
            return true;
        }

        return $driverProfile->operator_id === null
            && ($driverProfile->isPendingApproval() || $driverProfile->isRejected());
    }

    private function canApproveDriver(DriverProfile $driverProfile): bool
    {
        if (Auth::user()->role !== 'operator') {
            return false;
        }

        return $driverProfile->operator_id === null || $driverProfile->operator_id === Auth::id();
    }
}
