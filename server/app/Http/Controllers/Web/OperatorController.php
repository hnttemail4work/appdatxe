<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\DriverWalletTransaction;
use App\Models\Schedule;
use App\Models\User;
use App\Services\DriverTripRequestService;
use App\Services\DriverWalletService;
use App\Services\OperatorBookingDismissService;
use App\Services\OperatorTripOverdueService;
use App\Services\ScheduleLifecycleService;
use App\Services\TripConsolidationService;
use App\Support\PageList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class OperatorController extends Controller
{
    public function __construct(
        private readonly ScheduleLifecycleService $scheduleLifecycle,
        private readonly DriverWalletService $driverWallet,
        private readonly DriverTripRequestService $tripRequests,
    ) {
    }

    public function dashboard(Request $request)
    {
        if (in_array($request->query('tab'), ['today', 'referrals'], true)) {
            return redirect()->route('operator.dashboard');
        }

        $this->scheduleLifecycle->sync();
        $this->tripRequests->expireStale();
        app(OperatorTripOverdueService::class)->escalateOverdueTrips();
        app(OperatorBookingDismissService::class)->purgeExpiredDismissals();

        $user = Auth::user();

        $drivers = DriverProfile::query()
            ->where('operator_id', $user->id)
            ->operational()
            ->with('user')
            ->get();

        $driverList = DriverProfile::query()
            ->forOperatorManagement($user->id)
            ->with('user')
            ->latest()
            ->get();

        $pendingDriverCount = DriverProfile::pendingCountForOperator($user->id);

        $pendingBookingsCount = Booking::query()
            ->forOperatorVehicle($user->id)
            ->visibleOnOperatorDashboard()
            ->operatorListBucket('pending')
            ->count();

        $bookingList = in_array($request->query('list'), ['active', 'pending', 'completed', 'feedback', 'cancelled'], true)
            ? $request->query('list')
            : 'active';

        $operatorBookingBase = fn () => Booking::query()
            ->forOperatorVehicle($user->id)
            ->visibleOnOperatorDashboard();

        $bookingListCounts = [
            'active'    => $operatorBookingBase()->operatorListBucket('active')->count(),
            'pending'   => $operatorBookingBase()->operatorListBucket('pending')->count(),
            'completed' => $operatorBookingBase()->operatorListBucket('completed')->count(),
            'feedback'  => $operatorBookingBase()->operatorListBucket('feedback')->count(),
            'cancelled' => $operatorBookingBase()->operatorListBucket('cancelled')->count(),
        ];

        $passengers = $operatorBookingBase()
            ->operatorListBucket($bookingList)
            ->with([
                'schedule.route',
                'schedule.vehicle',
                'schedule.template',
                'schedule.driver.driverProfile.user',
                'schedule.driverTripRequests',
                'appliedReferralCode',
                'tripReview',
                'cancellationReason',
            ])
            ->latest()
            ->paginate(PageList::PER_PAGE)
            ->withQueryString();

        $pendingSettleCount = $this->driverWallet->pendingWalletRequestCounts($user->id)['total'];

        return view('operator.dashboard', compact(
            'drivers',
            'driverList',
            'passengers',
            'pendingDriverCount',
            'pendingSettleCount',
            'pendingBookingsCount',
            'bookingList',
            'bookingListCounts',
        ));
    }

    public function driverWallet(Request $request)
    {
        $this->driverWallet->enforceDeadlines();

        $operatorId = Auth::id();
        $depositsPendingAll = $this->driverWallet->pendingDepositsForOperator($operatorId);
        $walletHistoryAll = $this->driverWallet->operatorWalletActivityHistory($operatorId);

        $depositsPending = PageList::paginateCollection($depositsPendingAll, $request, 'deposit_page');
        $walletHistory = PageList::paginateCollection($walletHistoryAll, $request, 'history_page');

        $counts = $this->driverWallet->pendingWalletRequestCounts($operatorId);

        return view('operator.driver-wallet', compact(
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

        $operatorId = Auth::id();

        if (! Booking::supportsOperatorDismiss()) {
            return back()->withErrors(['booking_ids' => 'Hệ thống chưa cập nhật migration — liên hệ quản trị.']);
        }

        $updated = Booking::query()
            ->whereIn('id', array_map('intval', $validated['booking_ids']))
            ->whereHas('schedule.vehicle', fn ($q) => $q->where('operator_id', $operatorId))
            ->where(function ($q): void {
                $q->whereIn('booking_status', ['cancelled', 'rejected'])
                    ->orWhere('trip_status', 'cancelled');
            })
            ->update(['operator_dismissed_at' => now()]);

        if ($updated < 1) {
            return back()->withErrors(['booking_ids' => 'Không có đơn hủy hợp lệ để xóa.']);
        }

        return redirect()->route('operator.dashboard', ['list' => 'cancelled'])
            ->with('success', "Đã xóa {$updated} đơn hủy khỏi danh sách.");
    }

    public function dismissStuckBooking(Booking $booking)
    {
        try {
            app(OperatorBookingDismissService::class)->dismissFromOperatorQueue($booking, (int) Auth::id());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect()->route('operator.dashboard', ['list' => 'pending'])
            ->with('success', 'Đã ẩn đơn. Hệ thống sẽ tự xóa sau ' . OperatorBookingDismissService::RETENTION_DAYS . ' ngày.');
    }

    /** Gom khách ghép xe từ chuyến nguồn sang chuyến đích (cùng tuyến, gần giờ). */
    public function mergeSchedules(Schedule $target, Schedule $source)
    {
        try {
            $pending = app(TripConsolidationService::class)->mergeSchedules($target, $source, (int) Auth::id());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['merge' => $e->getMessage()]);
        }

        if ($pending) {
            return back()->with(
                'success',
                'Đã gửi yêu cầu gom chuyến — chờ tài xế xác nhận (tài xế có thể hỏi khách trước khi đồng ý).',
            );
        }

        return back()->with('success', 'Đã gom chuyến — khách ghép xe dùng chung mã ' . ($target->fresh()->shortTripCode() ?: 'chuyến') . '.');
    }

    public function approveDeposit(DriverWalletTransaction $transaction)
    {
        $transaction->loadMissing('wallet.driverProfile');
        if ((int) $transaction->wallet->driverProfile->operator_id !== Auth::id()) {
            abort(403);
        }

        try {
            $this->driverWallet->approveDeposit($transaction, Auth::id());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['wallet' => $e->getMessage()]);
        }

        return redirect()
            ->route('operator.driverWallet')
            ->with('success', 'Đã cộng tiền vào ví tài xế — tài xế có thể nhận cuốc bình thường.');
    }

    public function confirmAndAssignBooking(Request $request, Booking $booking)
    {
        $this->scheduleLifecycle->sync();
        $this->tripRequests->expireStale();

        $user = Auth::user();
        $booking->loadMissing(['schedule.route', 'schedule.vehicle']);

        if ((int) $booking->schedule->vehicle->operator_id !== $user->id) {
            abort(403);
        }

        if ($booking->needsOperatorConfirmation()) {
            $validated = $request->validate([
                'driver_code' => ['required', 'string', 'max:20'],
            ]);

            $driverCode = strtoupper(trim($validated['driver_code']));
            $profile = DriverProfile::query()
                ->where('operator_id', $user->id)
                ->operational()
                ->where('driver_code', $driverCode)
                ->first();

            if (! $profile) {
                return back()->withErrors(['driver_code' => 'Không tìm thấy tài xế hợp lệ.'])->withInput();
            }

            $designated = $booking->schedule->designatedDriverProfile();
            if ($designated && (int) $designated->user_id !== (int) $profile->user_id) {
                $designated->loadMissing('user');

                return back()->withErrors([
                    'driver_code' => 'Chuyến đã giao cho tài xế ' . $designated->user->name . ' — không thể chọn tài xế khác.',
                ])->withInput();
            }

            if (! $booking->operator_confirmed_at) {
                $booking->update(['operator_confirmed_at' => now()]);
            }

            try {
                $this->tripRequests->requestDriver(
                    $booking->schedule->fresh(['route']),
                    $driverCode,
                    (string) $booking->contact_phone,
                );
            } catch (InvalidArgumentException $e) {
                return back()->withErrors(['driver_code' => $e->getMessage()])->withInput();
            }

            $this->tripRequests->clearOperatorHelp($booking->fresh());

            return back()->with('success', 'Đã giao chuyến cho tài xế ' . $profile->user->name . ' — chờ tài xế xác nhận.');
        }

        if (! $booking->hasDriverAccepted()) {
            $validated = $request->validate([
                'driver_code' => ['required', 'string', 'max:20'],
            ]);

            $driverCode = strtoupper(trim($validated['driver_code']));

            $designated = $booking->schedule->designatedDriverProfile();
            if ($designated) {
                $designated->loadMissing('user');
                if (strtoupper(trim((string) $designated->driver_code)) !== $driverCode) {
                    return back()->withErrors([
                        'driver_code' => 'Chuyến đã giao cho tài xế ' . $designated->user->name . ' — không thể chọn tài xế khác.',
                    ])->withInput();
                }
            }

            try {
                $this->tripRequests->requestDriver(
                    $booking->schedule->fresh(['route']),
                    $driverCode,
                    (string) $booking->contact_phone,
                );
            } catch (InvalidArgumentException $e) {
                return back()->withErrors(['driver_code' => $e->getMessage()])->withInput();
            }

            return back()->with('success', 'Đã giao lại chuyến cho tài xế mới.');
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
                    $user->id,
                );
            } catch (InvalidArgumentException $e) {
                return back()->withErrors(['driver_code' => $e->getMessage()])->withInput();
            }

            $profile = DriverProfile::query()
                ->where('operator_id', $user->id)
                ->where('driver_code', $driverCode)
                ->with('user')
                ->first();

            return back()->with('success', 'Đã đổi sang tài xế backup ' . ($profile?->user->name ?? $driverCode) . '.');
        }

        return back()->with('success', 'Chuyến đã có tài xế nhận.');
    }
}
