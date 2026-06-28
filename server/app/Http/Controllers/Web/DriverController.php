<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\DriverTripRequest;
use App\Models\Schedule;
use App\Support\DriverFieldRules;
use App\Services\BookingWorkflowService;
use App\Services\DriverMissedTripService;
use App\Services\DriverPhotoService;
use App\Services\DriverProfileSyncService;
use App\Services\DriverTripRequestService;
use App\Services\DriverWalletService;
use App\Services\ScheduleLifecycleService;
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

    public function myDashboard()
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
                'transactions',
            ]);
        }

        $weekStart = now()->startOfWeek(\Carbon\Carbon::MONDAY)->startOfDay();
        $weekEnd = now()->endOfWeek(\Carbon\Carbon::SUNDAY)->endOfDay();

        $tripSchedules = Schedule::query()
            ->with([
                'route',
                'vehicle',
                'tripSettlement',
                'bookings' => fn ($q) => $q->orderByDesc('id'),
            ])
            ->where('driver_id', $user->id)
            ->whereNot('status', 'cancelled')
            ->whereBetween('departure_time', [$weekStart, $weekEnd])
            ->whereHas('bookings', fn ($q) => $q->whereNotIn('booking_status', ['cancelled', 'rejected']))
            ->get()
            ->filter(fn (Schedule $schedule): bool => $schedule->driverRelevantBookings()->isNotEmpty())
            ->sortBy(fn (Schedule $schedule): string => $schedule->driverViewSortKey())
            ->values();

        $pendingRequests = DriverTripRequest::query()
            ->where('driver_id', $user->id)
            ->where('status', 'pending')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->with(['schedule.route', 'schedule.vehicle', 'schedule.bookings'])
            ->latest()
            ->get();

        $tripActionCount = $tripSchedules
            ->filter(fn (Schedule $s): bool => in_array($s->driverWorkflowPhase(), ['active', 'needs_settle', 'enter_settle_code'], true))
            ->count();

        $showTopUpBanner = $profile ? $this->driverWallet->shouldShowTopUpBanner($profile) : false;
        $settlementBlockReason = $profile ? $this->driverWallet->settlementBlockReason($profile) : null;
        $revenueStats = $profile
            ? $this->driverWallet->driverRevenueStats($profile)
            : ['day' => 0, 'week' => 0];

        return view('driver.dashboard', compact(
            'user',
            'profile',
            'pendingRequests',
            'walletBlockReason',
            'driverWallet',
            'showTopUpBanner',
            'settlementBlockReason',
            'revenueStats',
            'tripSchedules',
            'tripActionCount',
        ));
    }

    public function acceptTripRequest(Request $request, DriverTripRequest $driverTripRequest)
    {
        try {
            $this->driverRequests->accept($driverTripRequest, Auth::id());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['driver_request' => $e->getMessage()]);
        }

        return redirect()->route('driver.dashboard')->with('success', 'Đã nhận chuyến. Thông tin đã đồng bộ tới khách và quản lý.');
    }

    public function rejectTripRequest(Request $request, DriverTripRequest $driverTripRequest)
    {
        try {
            $this->driverRequests->reject($driverTripRequest, Auth::id());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['driver_request' => $e->getMessage()]);
        }

        return redirect()->route('driver.dashboard')->with('success', 'Đã từ chối yêu cầu nhận chuyến.');
    }

    public function myProfile()
    {
        $user    = Auth::user();
        $profile = DriverProfile::query()->where('user_id', $user->id)->with('operator')->first();

        return view('driver.profile', compact('user', 'profile'));
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

    public function updateMyProfile(Request $request)
    {
        $user = Auth::user();
        $profile = DriverProfile::query()->where('user_id', $user->id)->firstOrFail();

        $validated = $request->validate(DriverFieldRules::selfUpdateRules($user->id, $profile->id));

        $this->profileSync->fillUserFromValidated($profile, $validated);
        $this->profileSync->fillProfileFromValidated($profile, $validated);

        return redirect()->route('driver.profile')->with('success', 'Đã cập nhật hồ sơ tài xế.');
    }

    public function uploadMyPhotos(Request $request)
    {
        $profile = DriverProfile::query()->where('user_id', Auth::id())->firstOrFail();

        try {
            $this->photoService->syncPhotos($profile, $request, $profile->identityPhotosLocked());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['photos' => $e->getMessage()])->withInput();
        }

        return redirect()->route('driver.profile')->with('success', 'Đã lưu ảnh thành công.');
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
            ? "Đã hoàn thành chuyến ({$count} vé). Kết chuyến tại mục Ví & kết chuyến."
            : 'Đã hoàn thành chuyến. Kết chuyến tại mục Ví & kết chuyến.';

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
            ? "Đã hoàn thành chuyến ({$count} vé). Kết chuyến tại mục Ví & kết chuyến."
            : 'Đã hoàn thành chuyến. Kết chuyến tại mục Ví & kết chuyến.';

        return redirect()->route('driver.dashboard')->with('success', $message);
    }

    public function index()
    {
        $drivers = DriverProfile::query()
            ->with(['user', 'operator'])
            ->forOperatorManagement(Auth::id())
            ->latest()
            ->get();

        return view('operator.drivers.index', compact('drivers'));
    }

    public function edit(DriverProfile $driverProfile)
    {
        if (! $this->canManageDriver($driverProfile)) {
            abort(403);
        }

        $driverProfile->load(['user', 'operator']);
        $driverWallet = $this->driverWallet->walletFor($driverProfile);
        $driverWallet->load('transactions');
        $pendingDeposits = $this->driverWallet->pendingDepositsForDriver($driverProfile);

        return view('operator.drivers.edit', [
            'driver'          => $driverProfile,
            'driverWallet'    => $driverWallet,
            'pendingDeposits' => $pendingDeposits,
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
            DriverFieldRules::operatorUpdateRules($driverProfile->user_id, $driverProfile->id),
        );

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

    public function reject(DriverProfile $driverProfile)
    {
        if (! $this->canApproveDriver($driverProfile)) {
            abort(403);
        }

        if (! $driverProfile->isPendingApproval()) {
            return back()->withErrors(['driver' => 'Tài xế này không còn ở trạng thái chờ duyệt.']);
        }

        $this->profileSync->reject($driverProfile);

        return redirect()
            ->route('operator.drivers')
            ->with('success', 'Đã từ chối hồ sơ tài xế.');
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

        return $driverProfile->operator_id === null && $driverProfile->isPendingApproval();
    }

    private function canApproveDriver(DriverProfile $driverProfile): bool
    {
        if (Auth::user()->role !== 'operator') {
            return false;
        }

        return $driverProfile->operator_id === null || $driverProfile->operator_id === Auth::id();
    }
}
