<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignDriverRequest;
use App\Http\Requests\Admin\BulkDismissBookingsRequest;
use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\DriverWalletTransaction;
use App\Services\BookingWorkflowService;
use App\Services\DriverTripRequestService;
use App\Services\LaterReturnBookingService;
use App\Services\PushNotificationService;
use App\Services\ScheduleLifecycleService;
use App\Support\PageList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * Nhóm "quản lý booking" — tách ra từ AdminController (Fat Controller):
 * danh sách/đồng bộ đơn, gán tài xế, nhắc tài xế, hủy đơn, tạo chuyến đón về, xóa hàng loạt.
 */
class AdminBookingController extends Controller
{
    public function __construct(
        private readonly ScheduleLifecycleService $scheduleLifecycle,
        private readonly DriverTripRequestService $tripRequests,
        private readonly BookingWorkflowService $workflow,
        private readonly LaterReturnBookingService $laterReturnBookings,
        private readonly PushNotificationService $pushNotifications,
    ) {
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
            'alerts'                   => app(\App\Services\AdminOperatorAlertService::class)->pullAlerts(),
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
            ->get()
            ->reject(fn (Booking $booking): bool => $booking->shouldHideFromGuestAndOperatorActiveLists())
            ->values();

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

    public function bulkDismissBookings(BulkDismissBookingsRequest $request)
    {
        $validated = $request->validated();

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

    public function confirmAndAssignBooking(AssignDriverRequest $request, Booking $booking)
    {
        $this->scheduleLifecycle->sync();
        $this->tripRequests->expireStale();

        $booking->loadMissing(['schedule.route', 'schedule.vehicle', 'schedule.template']);

        if ($booking->passengerPickedUp()) {
            return back()->withErrors(['driver_code' => 'Tài xế đã đón khách — không thể gán lại.']);
        }

        $validated = $request->validated();

        $driverCode = strtoupper(trim($validated['driver_code']));

        $isReassign = $booking->driverAcceptanceState() === 'accepted'
            || $booking->needs_operator_help_at
            || $booking->isPastPickupTime();

        try {
            $this->tripRequests->assignBookingDriver(
                $booking->fresh(['schedule.route', 'schedule.vehicle', 'schedule.template']),
                $driverCode,
                (int) Auth::id(),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['driver_code' => $e->getMessage()])->withInput();
        }

        return back()->with(
            'success',
            $isReassign
                ? 'Đã tạo chuyến mới và gán lại tài xế — chờ xác nhận trong 15 phút.'
                : 'Đã giao chuyến cho tài xế — chờ xác nhận trong 15 phút.',
        );
    }

    public function nudgeDriverBooking(Booking $booking)
    {
        $this->scheduleLifecycle->sync();
        $this->tripRequests->expireStale();

        $booking->loadMissing(['schedule.route']);

        $request = $booking->latestDriverTripRequest();
        if (! $request || ! $request->isPending()) {
            return back()->withErrors(['booking' => 'Chuyến không còn ở trạng thái chờ tài xế nhận.']);
        }

        if (! $booking->assignedDriverHasPushSubscription()) {
            return back()->withErrors(['booking' => 'Tài xế chưa bật app — không gửi được thông báo đẩy.']);
        }

        if (! $this->pushNotifications->nudgeDriverTripRequest($request)) {
            return back()->withErrors(['booking' => 'Không gửi được thông báo. Kiểm tra cấu hình TB đẩy (VAPID).']);
        }

        return back()->with('success', 'Đã gửi lại thông báo cho tài xế.');
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
