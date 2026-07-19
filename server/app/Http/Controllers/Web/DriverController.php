<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkDeleteRejectedRegistrationsRequest;
use App\Http\Requests\Admin\UpdateDriverInviteRequest;
use App\Http\Requests\Driver\CancelScheduleRequest;
use App\Http\Requests\Driver\RejectDriverProfileRequest;
use App\Http\Requests\Driver\RejectTripRequestRequest;
use App\Http\Requests\Driver\UpdateAvailabilityRequest;
use App\Http\Requests\Driver\UpdateDriverPasswordRequest;
use App\Http\Requests\Driver\UpdateDriverProfileRequest;
use App\Http\Requests\Driver\UpdateLocationRequest;
use App\Models\Booking;
use App\Models\DriverProfile;
use App\Support\ProvinceResolver;
use App\Models\DriverTripRequest;
use App\Models\Schedule;
use App\Support\AdminIdentityApproval;
use App\Support\DriverDefaultPassword;
use App\Services\BookingWorkflowService;
use App\Services\DriverAvailabilityService;
use App\Services\DriverCancelRateService;
use App\Services\DriverLatePickupService;
use App\Services\DriverMovementConfirmService;
use App\Services\DriverMissedTripService;
use App\Services\DriverPhotoService;
use App\Services\DriverProfileSyncService;
use App\Services\DriverProximityService;
use App\Services\GuestBookingDriverStatusService;
use App\Services\DriverTripRequestService;
use App\Services\DriverInboxService;
use App\Services\DriverWalletService;
use App\Services\PendingApprovalExpiryService;
use App\Services\ReferralCodeService;
use App\Services\ScheduleLifecycleService;
use App\Services\TripChatService;
use App\Support\PageList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class DriverController extends Controller
{
    use ApiResponds;

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
        private readonly DriverMovementConfirmService $movementConfirm,
        private readonly DriverProximityService $proximity,
        private readonly GuestBookingDriverStatusService $guestDriverStatus,
        private readonly ReferralCodeService $referralCodes,
        private readonly DriverInboxService $driverInbox,
        private readonly TripChatService $tripChat,
        private readonly PendingApprovalExpiryService $pendingExpiry,
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
            if (in_array($profile->availability_status ?? 'off_duty', ['available', 'on_trip'], true)) {
                $this->driverAvailability->touchWebPresence((int) $profile->user_id);
            }
            $this->driverAvailability->enforceWebPresenceIdleFor($profile);
            $this->driverAvailability->syncAfterTripCompleted((int) $profile->user_id);
            $profile = $profile->fresh(['operator']);
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
        // TODO (Fix Driver Toggle): Khóa switch theo đúng danh sách chuyến đang hiện trên dashboard, tránh cờ stale.
        $driverTripActive = $tripSchedulesAll->contains(
            fn (Schedule $schedule): bool => $schedule->driverWorkflowPhase() === 'active',
        );
        // TODO (Fix Driver Toggle): Chỉ đánh dấu upcoming khi thực sự còn chuyến upcoming đang visible.
        $driverTripUpcoming = $tripSchedulesAll->contains(
            fn (Schedule $schedule): bool => $schedule->driverWorkflowPhase() === 'upcoming',
        );
        $driverOnTrip = $driverTripActive;

        $driverPickupProximityLine = null;
        foreach ($tripSchedulesAll as $schedule) {
            if ($schedule->resolvedDriverStage() === Schedule::DRIVER_STAGE_ASSIGNED) {
                $driverPickupProximityLine = $this->latePickup->driverPickupProximityLine($schedule);
                break;
            }
        }

        $driverActiveSchedule = $tripSchedulesAll->first(
            fn (Schedule $schedule): bool => in_array($schedule->driverWorkflowPhase(), ['upcoming', 'active'], true),
        );

        $pendingTripRequestGroups = $this->driverRequests->visiblePendingGroupsForDriver($user->id);

        $driverMapTripPins = [];
        $driverActiveMapNav = null;
        if ($driverActiveSchedule) {
            $activeBookings = $driverActiveSchedule->driverRelevantBookings();

            foreach ($activeBookings as $booking) {
                if ($booking->pickup_lat !== null && $booking->pickup_lng !== null) {
                    $driverMapTripPins[] = [
                        'type'  => 'pickup',
                        'lat'   => (float) $booking->pickup_lat,
                        'lng'   => (float) $booking->pickup_lng,
                        'label' => $booking->pickupLabel(),
                    ];
                }
                if ($booking->dropoff_lat !== null && $booking->dropoff_lng !== null) {
                    $driverMapTripPins[] = [
                        'type'  => 'dropoff',
                        'lat'   => (float) $booking->dropoff_lat,
                        'lng'   => (float) $booking->dropoff_lng,
                        'label' => $booking->dropoffLabel(),
                    ];
                }
            }

            $primaryActiveBooking = $activeBookings->first();
            if ($primaryActiveBooking) {
                $driverActiveMapNav = \App\Support\MapNavigation::driverTargetForSchedule($driverActiveSchedule, $primaryActiveBooking);
            }
        }

        // Cuốc chờ nhận — hiện nút điều hướng (thiếu lat/lng → fallback trung tâm tỉnh trên tuyến).
        if ($driverActiveMapNav === null && $pendingTripRequestGroups->isNotEmpty()) {
            $pendingGroup = $pendingTripRequestGroups->first();
            $pendingBooking = $pendingGroup['passengers']->first() ?? null;
            $pendingSchedule = $pendingGroup['schedule'] ?? null;
            if ($pendingBooking instanceof \App\Models\Booking) {
                $driverActiveMapNav = \App\Support\MapNavigation::driverPickupTarget($pendingBooking, $pendingSchedule);
                if ($driverActiveMapNav
                    && $driverActiveMapNav['dest_lat'] !== null
                    && $driverActiveMapNav['dest_lng'] !== null
                    && $driverMapTripPins === []) {
                    $driverMapTripPins[] = [
                        'type'  => 'pickup',
                        'lat'   => (float) $driverActiveMapNav['dest_lat'],
                        'lng'   => (float) $driverActiveMapNav['dest_lng'],
                        'label' => $pendingBooking->pickupLabel(),
                    ];
                }
            }
        }

        $showTopUpBanner = $walletNotice !== null;
        $revenueStats = $profile
            ? $this->driverWallet->driverRevenueStats($profile)
            : ['day' => 0, 'week' => 0];
        $walletHistoryAll = $driverWallet
            ? $this->driverWallet->walletActivityHistory($driverWallet, depositsOnly: true)
            : collect();
        $walletHistory = PageList::paginateCollection($walletHistoryAll, $request, 'history_page');
        $mustChangePassword = (bool) $user->must_change_password;

        $driverInviteReferral = null;
        $driverInviteUrl = null;
        $driverInviteDiscountPercent = null;
        $driverCommissionReferral = null;
        $referredCustomers = collect();
        if ($profile) {
            $driverInviteReferral = $this->referralCodes->forDriver($profile);
            if ($driverInviteReferral?->isUsable()) {
                $driverInviteUrl = $driverInviteReferral->landingUrl();
                $driverInviteDiscountPercent = $driverInviteReferral->customerDiscountPercent();
            } else {
                $driverInviteReferral = null;
            }
            $driverCommissionReferral = $this->referralCodes->assignedCommissionForDriver($profile);
            if ($driverCommissionReferral && ! $driverCommissionReferral->isUsable()) {
                $driverCommissionReferral = null;
            }
            $referredCustomers = $this->referralCodes->referredCustomersForCodes([
                $this->referralCodes->forDriver($profile),
                $this->referralCodes->assignedCommissionForDriver($profile),
            ]);
        }

        $inboxUnread = $this->tripChat->mergeInboxUnread(
            $this->driverInbox->unreadCounts((int) $user->id),
            (int) $user->id,
        );
        $inboxInfoMessages = $this->driverInbox->listFor((int) $user->id, 'info');
        $inboxNoticeMessages = $this->driverInbox->listFor((int) $user->id, 'notice');
        $inboxChatThreads = $this->tripChat->recentThreadsForDriver((int) $user->id);

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
            'driverPickupProximityLine',
            'mustChangePassword',
            'driverMapTripPins',
            'driverActiveMapNav',
            'driverInviteReferral',
            'driverInviteUrl',
            'driverInviteDiscountPercent',
            'driverCommissionReferral',
            'referredCustomers',
            'inboxUnread',
            'inboxInfoMessages',
            'inboxNoticeMessages',
            'inboxChatThreads',
        ));
    }

    public function dashboardPoll(Request $request)
    {
        $this->scheduleLifecycle->sync();
        $this->driverRequests->expireStale();

        $user = Auth::user();
        $profile = DriverProfile::query()->where('user_id', $user->id)->first();

        if ($profile) {
            if (in_array($profile->availability_status ?? 'off_duty', ['available', 'on_trip'], true)) {
                $this->driverAvailability->touchWebPresence((int) $profile->user_id);
            }
            // TODO (Fix Driver Toggle): Poll chỉ giữ heartbeat — không tự tắt Hoạt động giữa lúc TX đang thao tác switch.
            $this->driverAvailability->syncAfterTripCompleted((int) $profile->user_id);
            $profile = $profile->fresh();
        }

        $tripActionCount = $this->driverRequests->tripActionCountForDriver($user->id);
        $pendingTripRequestCount = $profile
            ? $this->driverRequests->visiblePendingGroupsForDriver($user->id)->count()
            : 0;

        $driverPickupProximityLine = null;
        $driverSchedules = collect();
        if ($profile) {
            $driverSchedules = Schedule::query()
                ->forDriverActiveTrips($user->id)
                ->get()
                ->filter(fn (Schedule $schedule): bool => $schedule->driverRelevantBookings()->isNotEmpty()
                    && $schedule->driverWorkflowPhase() !== 'settled'
                    && $schedule->isVisibleOnDriverDashboard())
                ->values();

            foreach ($driverSchedules as $schedule) {
                if ($schedule->resolvedDriverStage() === Schedule::DRIVER_STAGE_ASSIGNED) {
                    $driverPickupProximityLine = $this->latePickup->driverPickupProximityLine($schedule);
                    break;
                }
            }
        }

        // TODO (Fix Driver Toggle): Poll dùng cùng nguồn trips visible như dashboard để không khóa switch oan.
        $driverTripActive = $driverSchedules->contains(
            fn (Schedule $schedule): bool => $schedule->driverWorkflowPhase() === 'active',
        );
        $driverTripUpcoming = $driverSchedules->contains(
            fn (Schedule $schedule): bool => $schedule->driverWorkflowPhase() === 'upcoming',
        );

        $inboxUnread = $this->tripChat->mergeInboxUnread(
            $this->driverInbox->unreadCounts((int) $user->id),
            (int) $user->id,
        );

        $fingerprint = sha1(implode('|', [
            $tripActionCount,
            $pendingTripRequestCount,
            $driverTripActive ? '1' : '0',
            $driverTripUpcoming ? '1' : '0',
            $profile?->availability_status ?? 'off_duty',
            (string) ($driverPickupProximityLine ?? ''),
            (string) ($inboxUnread['total'] ?? 0),
            (string) ($inboxUnread['chat'] ?? 0),
        ]));

        return response()->json([
            'trip_action_count'       => $tripActionCount,
            'pending_trip_requests'   => $pendingTripRequestCount,
            'driver_trip_active'      => $driverTripActive,
            'driver_trip_upcoming'    => $driverTripUpcoming,
            'availability_status'     => $profile?->availability_status ?? 'off_duty',
            'pickup_proximity_line'   => $driverPickupProximityLine,
            'inbox_unread'            => $inboxUnread,
            'fingerprint'             => $fingerprint,
        ]);
    }

    public function advanceSchedule(Request $request, Schedule $schedule)
    {
        try {
            $stage = $this->workflow->driverAdvanceScheduleStage($schedule, Auth::id());
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($request, $e->getMessage(), 'booking');
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

    public function confirmMovement(Request $request, Schedule $schedule)
    {
        try {
            $this->movementConfirm->confirmMovement($schedule, (int) Auth::id());
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($request, $e->getMessage(), 'booking');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok'       => true,
                'redirect' => route('driver.dashboard', ['tab' => 'trips']),
                'message'  => 'Đã xác nhận — di chuyển đến điểm đón khi sẵn sàng.',
            ]);
        }

        return redirect()->route('driver.dashboard', ['tab' => 'trips'])
            ->with('success', 'Đã xác nhận — di chuyển đến điểm đón khi sẵn sàng.');
    }

    public function acceptTripRequest(Request $request, DriverTripRequest $driverTripRequest)
    {
        try {
            $this->driverRequests->accept($driverTripRequest, (int) Auth::id());
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($request, $e->getMessage(), 'driver_request');
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

    public function rejectTripRequest(RejectTripRequestRequest $request, DriverTripRequest $driverTripRequest)
    {
        $validated = $request->validated();

        try {
            $this->driverRequests->reject(
                $driverTripRequest,
                (int) Auth::id(),
                (int) $validated['cancellation_reason_id'],
            );
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($request, $e->getMessage(), 'driver_request');
        }

        $profile = DriverProfile::query()->where('user_id', Auth::id())->first();

        if ($request->expectsJson()) {
            return response()->json([
                'ok'                  => true,
                'availability'        => $profile?->availability_status ?? 'off_duty',
                'message'             => 'Đã hủy cuốc. Quản lý sẽ được thông báo lý do bạn chọn.',
            ]);
        }

        return redirect()->route('driver.dashboard', ['tab' => 'trips'])->with('success', 'Đã hủy cuốc.');
    }

    public function updateLocation(UpdateLocationRequest $request)
    {
        $validated = $request->validated();

        $profile = DriverProfile::query()->where('user_id', Auth::id())->firstOrFail();

        if (($profile->availability_status ?? 'off_duty') === 'off_duty') {
            return response()->json([
                'message' => 'Bật «Sẵn sàng» trước khi cập nhật vị trí.',
            ], 422);
        }

        $address = trim((string) ($validated['address'] ?? ''));

        $heading = array_key_exists('heading', $validated) && $validated['heading'] !== null
            ? (float) $validated['heading']
            : null;

        $profile->update([
            'last_lat'         => $validated['lat'],
            'last_lng'         => $validated['lng'],
            'last_heading'     => $heading,
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
            if ($booking && ($schedule->resolvedDriverStage() ?? '') === Schedule::DRIVER_STAGE_ASSIGNED
                && $schedule->driverHasConfirmedMovement()) {
                try {
                    app(\App\Services\PushNotificationService::class)->onDriverEnRoute(
                        $booking,
                        (string) $proximityPayload['distance_label'],
                    );
                } catch (\Throwable) {
                }
            }
        }

        $activeSchedule = $this->driverAvailability->activeSchedulesForDriver((int) $profile->user_id)->first();

        return response()->json([
            'ok'                    => true,
            'address'               => $address !== '' ? $address : null,
            'updated_at'            => $profile->last_location_at?->format('H:i, d/m/Y'),
            'assigned'              => $assigned,
            'pickup_distances'      => $pickupDistances,
            'pickup_distance_label' => $proximityPayload['distance_label'] ?? ($pickupDistances[0]['distance_label'] ?? null),
            'pickup_eta_label'      => $proximityPayload['eta_label'] ?? null,
            'pickup_proximity_hint' => $proximityPayload['proximity_hint'] ?? null,
            'pickup_proximity_line' => $activeSchedule
                ? $this->latePickup->driverPickupProximityLine($activeSchedule)
                : null,
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

    public function updatePassword(UpdateDriverPasswordRequest $request)
    {
        $user = Auth::user();

        $validated = $request->validated();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()
                ->withErrors(['current_password' => 'Mật khẩu hiện tại không đúng.'])
                ->withInput($request->except('current_password', 'password', 'password_confirmation'));
        }

        $user->update([
            'password'             => $validated['password'],
            'must_change_password' => false,
        ]);

        return redirect()
            ->route('driver.dashboard', ['tab' => 'account-password'])
            ->with('success', 'Đã đổi mật khẩu thành công.');
    }

    public function updateAvailability(UpdateAvailabilityRequest $request)
    {
        $validated = $request->validated();

        $profile = DriverProfile::query()->where('user_id', Auth::id())->firstOrFail();

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
            return $this->errorResponse($request, $e->getMessage(), 'booking');
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

    public function cancelSchedule(CancelScheduleRequest $request, Schedule $schedule)
    {
        $validated = $request->validated();

        try {
            $this->workflow->cancelScheduleByDriver(
                $schedule,
                Auth::id(),
                (int) $validated['cancellation_reason_id'],
            );
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($request, $e->getMessage(), 'booking');
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
            return $this->errorResponse($request, $e->getMessage(), 'booking');
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('driver.dashboard')->with('success', 'Đã xác nhận — tiếp tục đến điểm đón.');
    }

    public function index(Request $request)
    {
        $this->pendingExpiry->expireStaleDrivers();

        $filter = in_array($request->query('filter'), ['pending', 'rejected'], true)
            ? $request->query('filter')
            : 'all';

        $query = DriverProfile::query()
            ->with(['user', 'operator', 'wallet', 'pendingChangeRequest']);

        if (Auth::user()->role === 'admin') {
            if ($filter === 'pending') {
                $query->pendingApproval();
            } elseif ($filter === 'rejected') {
                $query->where('approval_status', 'rejected')
                    ->whereHas('user', fn ($q) => $q->where('role', 'driver'));
            } else {
                // Danh sách chính: chỉ tài xế đã duyệt.
                $query->where('approval_status', 'approved')
                    ->whereHas('user', fn ($q) => $q->where('role', 'driver'));
            }
        } else {
            $query->forOperatorManagement(Auth::id());
            if ($filter === 'pending') {
                $query->pendingForOperator(Auth::id());
            } elseif ($filter === 'rejected') {
                $query->where('approval_status', 'rejected')
                    ->whereHas('user', fn ($q) => $q->where('role', 'driver'));
            } else {
                $query->where('approval_status', 'approved');
            }
        }

        $drivers = PageList::paginateCollection(
            $query->latest()->get()
                ->when($filter === 'all', fn ($c) => $c->sortBy(function ($d) {
                    return $d->pendingChangeRequest ? 0 : 1;
                })->values())
                ->when($filter !== 'all', fn ($c) => $c->values()),
            $request,
        );

        $this->driverAvailability->reconcileMany($drivers->items());
        foreach ($drivers->items() as $driver) {
            $driver->refresh();
            $driver->load(['user', 'wallet', 'pendingChangeRequest']);
        }

        // Tab Chờ duyệt: luôn hiện tổng số đang chờ.
        $pendingCount = Auth::user()->role === 'admin'
            ? (int) DriverProfile::query()->pendingApproval()->count()
            : DriverProfile::pendingCountForOperator(Auth::id());

        $docUpdateCount = Auth::user()->role === 'admin'
            ? (int) DriverProfile::query()->pendingDocumentUpdate()->count()
            : (int) DriverProfile::query()
                ->forOperatorManagement(Auth::id())
                ->pendingDocumentUpdate()
                ->count();

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
            'docUpdateCount',
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

        $driverProfile->load([
            'user',
            'operator',
            'pendingChangeRequest',
            'referralCode',
            'assignedCommissionCode',
        ]);
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

    public function storeInvite(UpdateDriverInviteRequest $request, DriverProfile $driverProfile)
    {
        if (! $this->canManageDriver($driverProfile)) {
            abort(403);
        }

        if ($driverProfile->isPendingApproval() || $driverProfile->isRejected()) {
            return back()->withErrors(['driver' => 'Hồ sơ đang chờ duyệt hoặc đã bị từ chối — chưa thể tạo QR.']);
        }

        $discount = (float) $request->validated()['customer_discount_percent'];

        try {
            $this->referralCodes->createForDriver($driverProfile, $discount);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['customer_discount_percent' => $e->getMessage()]);
        }

        $this->driverInbox->notifyPromoGranted($driverProfile, $discount);

        return $this->redirectAfterInviteManage($driverProfile)
            ->with('success', 'Đã tạo QR giới thiệu: giảm ' . number_format($discount, 1) . '%.');
    }

    public function updateInvite(UpdateDriverInviteRequest $request, DriverProfile $driverProfile)
    {
        if (! $this->canManageDriver($driverProfile)) {
            abort(403);
        }

        if ($driverProfile->isPendingApproval() || $driverProfile->isRejected()) {
            return back()->withErrors(['driver' => 'Hồ sơ đang chờ duyệt hoặc đã bị từ chối — chưa thể chỉnh khuyến mãi.']);
        }

        $discount = (float) $request->validated()['customer_discount_percent'];
        $existing = $this->referralCodes->forDriver($driverProfile);
        if (! $existing) {
            return back()->withErrors(['customer_discount_percent' => 'Chưa có mã QR — hãy tạo QR trước.']);
        }

        $previous = $existing->customerDiscountPercent();

        try {
            $this->referralCodes->updateDriverInviteDiscount($driverProfile, $discount);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['customer_discount_percent' => $e->getMessage()]);
        }

        if (abs($previous - $discount) >= 0.05) {
            $this->driverInbox->notifyPromoUpdated($driverProfile, $discount);
        }

        return $this->redirectAfterInviteManage($driverProfile)
            ->with('success', 'Đã cập nhật khuyến mãi QR giới thiệu: giảm '
                . number_format($discount, 1) . '%.');
    }

    public function destroyInvite(Request $request, DriverProfile $driverProfile)
    {
        if (! $this->canManageDriver($driverProfile)) {
            abort(403);
        }

        if ($driverProfile->isPendingApproval() || $driverProfile->isRejected()) {
            return back()->withErrors(['driver' => 'Hồ sơ đang chờ duyệt hoặc đã bị từ chối — chưa thể ngưng QR.']);
        }

        $previous = $this->referralCodes->suspendForDriver($driverProfile);
        if ($previous === null) {
            return $this->redirectAfterInviteManage($driverProfile)
                ->with('success', 'Không có mã QR đang dùng để tạm ngưng.');
        }

        $this->driverInbox->notifyPromoRemoved($driverProfile, $previous);

        return $this->redirectAfterInviteManage($driverProfile)
            ->with('success', 'Đã tạm ngưng QR giảm ' . number_format($previous, 1) . '% — không hiện ở Mời bạn bè.');
    }

    public function restoreInvite(Request $request, DriverProfile $driverProfile)
    {
        if (! $this->canManageDriver($driverProfile)) {
            abort(403);
        }

        if ($driverProfile->isPendingApproval() || $driverProfile->isRejected()) {
            return back()->withErrors(['driver' => 'Hồ sơ đang chờ duyệt hoặc đã bị từ chối — chưa thể bật lại QR.']);
        }

        $restored = $this->referralCodes->restoreForDriver($driverProfile);
        if (! $restored) {
            return $this->redirectAfterInviteManage($driverProfile)
                ->with('success', 'Không có mã QR đang tạm ngưng để bật lại.');
        }

        $discount = $restored->customerDiscountPercent();
        $this->driverInbox->notifyPromoGranted($driverProfile, $discount);

        return $this->redirectAfterInviteManage($driverProfile)
            ->with('success', 'Đã bật lại QR giảm ' . number_format($discount, 1) . '%.');
    }

    private function redirectAfterInviteManage(DriverProfile $driverProfile): \Illuminate\Http\RedirectResponse
    {
        return redirect()->route('admin.referrals', [
            'tab'           => 'codes',
            'invite_driver' => $driverProfile->id,
        ]);
    }

    public function update(UpdateDriverProfileRequest $request, DriverProfile $driverProfile)
    {
        if (! $this->canManageDriver($driverProfile)) {
            abort(403);
        }

        if ($driverProfile->isPendingApproval() || $driverProfile->isRejected()) {
            return back()->withErrors(['driver' => 'Hồ sơ đang chờ duyệt hoặc đã bị từ chối — chưa thể chỉnh sửa.']);
        }

        $validated = $request->validated();

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

        if (! $driverProfile->isApproved()) {
            return back()->withErrors(['driver' => 'Chỉ tạm ngưng tài xế đã được duyệt.']);
        }

        $this->profileSync->setAccountStatus($driverProfile, 'suspended');

        return back()->with('success', 'Đã tạm ngưng tài xế.');
    }

    /** Xóa nhiều hồ sơ tài xế đã từ chối (kể cả hết hạn chờ duyệt). */
    public function bulkDestroy(BulkDeleteRejectedRegistrationsRequest $request)
    {
        if (Auth::user()?->role !== 'admin') {
            abort(403);
        }

        $ids = array_map('intval', $request->validated('ids'));
        $deleted = 0;

        DriverProfile::query()
            ->whereIn('id', $ids)
            ->where('approval_status', 'rejected')
            ->whereHas('user', fn ($q) => $q->where('role', 'driver'))
            ->with('user')
            ->orderBy('id')
            ->each(function (DriverProfile $profile) use (&$deleted): void {
                if ($this->pendingExpiry->deleteDriverRegistration($profile)) {
                    $deleted++;
                }
            });

        if ($deleted < 1) {
            return back()->withErrors(['ids' => 'Không có hồ sơ từ chối hợp lệ để xóa.']);
        }

        return redirect()
            ->route('admin.drivers', ['filter' => 'rejected'])
            ->with('success', "Đã xóa {$deleted} hồ sơ tài xế bị từ chối.");
    }

    public function activate(DriverProfile $driverProfile)
    {
        if (! $this->canManageDriver($driverProfile)) {
            abort(403);
        }

        $driverProfile->loadMissing('user');

        if (! $driverProfile->isApproved()) {
            return back()->withErrors(['driver' => 'Chỉ mở lại tài xế đã được duyệt.']);
        }

        if ($driverProfile->status === 'active' && $driverProfile->user?->status === 'active') {
            return back()->with('success', 'Tài xế đang hoạt động.');
        }

        $this->profileSync->setAccountStatus($driverProfile, 'active');

        return back()->with('success', 'Đã mở lại tài xế. Họ có thể đăng nhập.');
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

        $validated = $request->validate(
            AdminIdentityApproval::driverRules(),
            AdminIdentityApproval::messages(),
        );

        $photoUpdates = AdminIdentityApproval::storeAdjustedIdCardPhotos(
            $request,
            $driverProfile,
            'drivers/'.$driverProfile->id,
            AdminIdentityApproval::driverPhotoFields(),
        );
        if ($photoUpdates !== []) {
            $driverProfile->update($photoUpdates);
        }

        $this->profileSync->approve(
            $driverProfile,
            Auth::id(),
            AdminIdentityApproval::userAttributes($validated),
        );
        $driverProfile->loadMissing('user');
        if ($driverProfile->user) {
            app(\App\Services\RegistrationService::class)->issueRegisterOtpAfterApproval($driverProfile->user);
            app(\App\Services\UserInboxService::class)->notifyRegistrationApproved($driverProfile->user);
        }

        return redirect()
            ->route('admin.authCodes')
            ->with('success', \App\Support\AuthOtp::approvedOtpReady());
    }

    public function reject(RejectDriverProfileRequest $request, DriverProfile $driverProfile)
    {
        if (! $this->canApproveDriver($driverProfile)) {
            abort(403);
        }

        if (! $driverProfile->isPendingApproval()) {
            return back()->withErrors(['driver' => 'Tài xế này không còn ở trạng thái chờ duyệt.']);
        }

        $validated = $request->validated();

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

    public function resetPassword(DriverProfile $driverProfile)
    {
        if (! $this->canManageDriver($driverProfile)) {
            abort(403);
        }

        if ($driverProfile->isPendingApproval() || $driverProfile->isRejected()) {
            return back()->withErrors(['driver' => 'Chỉ đặt lại mật khẩu cho tài xế đã được duyệt.']);
        }

        $driverProfile->loadMissing('user');
        $user = $driverProfile->user;

        if ($user === null) {
            return back()->withErrors(['driver' => 'Không tìm thấy tài khoản đăng nhập của tài xế.']);
        }

        $plain = DriverDefaultPassword::resetToRandom($user);

        return back()
            ->with('success', 'Đã đặt lại PIN 6 số cho tài xế.')
            ->with('driver_password_reset', [
                'password'    => $plain,
                'driver_name' => $user->name,
                'phone'       => $user->phone,
            ]);
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
