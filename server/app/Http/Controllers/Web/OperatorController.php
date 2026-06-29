<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\DriverTripSettlement;
use App\Models\DriverWalletTransaction;
use App\Models\User;
use App\Services\DriverTripRequestService;
use App\Services\DriverWalletService;
use App\Services\ScheduleLifecycleService;
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
            ->with('schedule')
            ->whereHas('schedule.vehicle', fn ($q) => $q->where('operator_id', $user->id))
            ->whereNotIn('booking_status', ['cancelled', 'rejected'])
            ->get()
            ->filter(fn (Booking $b): bool => ! $b->isExpired()
                && $b->booking_status === 'pending'
                && ($b->needsOperatorConfirmation() || ! $b->hasDriverAccepted()))
            ->count();

        $passengers = Booking::query()
            ->with([
                'schedule.route',
                'schedule.vehicle',
                'schedule.template',
                'schedule.driver',
                'schedule.driverTripRequests',
                'schedule.tripSettlement',
            ])
            ->whereHas('schedule.vehicle', fn ($q) => $q->where('operator_id', $user->id))
            ->where(function ($q): void {
                $q->whereNotIn('booking_status', ['cancelled', 'rejected'])
                    ->orWhereNotNull('expired_at');
            })
            ->latest()
            ->paginate(PageList::PER_PAGE)
            ->withQueryString();

        $pendingSettleCount = $this->driverWallet->pendingWalletRequestCounts($user->id)['total'];

        return view('operator.dashboard', compact('drivers', 'driverList', 'passengers', 'pendingDriverCount', 'pendingSettleCount', 'pendingBookingsCount'));
    }

    public function driverWallet(Request $request)
    {
        $this->driverWallet->enforceDeadlines();

        $operatorId = Auth::id();
        $awaitingCodeAll = $this->driverWallet->settlementsAwaitingCodeForOperator($operatorId);
        $codesIssuedAll = $this->driverWallet->codesAwaitingDriverForOperator($operatorId);
        $depositsPendingAll = $this->driverWallet->pendingDepositsForOperator($operatorId);
        $walletHistoryAll = $this->driverWallet->operatorWalletActivityHistory($operatorId);

        $awaitingCode = PageList::paginateCollection($awaitingCodeAll, $request, 'settle_page');
        $codesIssued = PageList::paginateCollection($codesIssuedAll, $request, 'issued_page');
        $depositsPending = PageList::paginateCollection($depositsPendingAll, $request, 'deposit_page');
        $walletHistory = PageList::paginateCollection($walletHistoryAll, $request, 'history_page');

        $counts = $this->driverWallet->pendingWalletRequestCounts($operatorId);

        $defaultTab = match ($request->query('tab')) {
            'deposits', 'settlements', 'issued' => $request->query('tab'),
            default => $depositsPendingAll->isNotEmpty()
                ? 'deposits'
                : ($awaitingCodeAll->isNotEmpty() ? 'settlements' : 'deposits'),
        };

        return view('operator.driver-wallet', compact(
            'awaitingCode',
            'codesIssued',
            'depositsPending',
            'walletHistory',
            'counts',
            'defaultTab',
        ));
    }

    public function issueSettlementCode(DriverTripSettlement $settlement)
    {
        $settlement->loadMissing('wallet.driverProfile');
        if ((int) $settlement->wallet->driverProfile->operator_id !== Auth::id()) {
            abort(403);
        }

        try {
            $code = $this->driverWallet->issueSettlementCode($settlement, Auth::id());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['wallet' => $e->getMessage()]);
        }

        return back()->with('success', 'Đã cấp mã kết chuyến: ' . $code . ' — gửi cho tài xế (hiệu lực 24 giờ).');
    }

    public function approveSettlement(DriverTripSettlement $settlement)
    {
        $settlement->loadMissing('wallet.driverProfile');
        if ((int) $settlement->wallet->driverProfile->operator_id !== Auth::id()) {
            abort(403);
        }

        try {
            $this->driverWallet->approveUnderThresholdSettlement($settlement, Auth::id());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['wallet' => $e->getMessage()]);
        }

        return back()->with('success', 'Đã xác nhận kết chuyến — tài xế có thể nhận cuốc mới.');
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
            ->route('operator.driverWallet', ['tab' => 'deposits'])
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

            $booking->update(['operator_confirmed_at' => now()]);

            try {
                $this->tripRequests->requestDriver(
                    $booking->schedule->fresh(['route']),
                    $driverCode,
                    (string) $booking->contact_phone,
                );
            } catch (InvalidArgumentException $e) {
                $booking->update(['operator_confirmed_at' => null]);

                return back()->withErrors(['driver_code' => $e->getMessage()])->withInput();
            }

            return back()->with('success', 'Đã xác nhận và giao chuyến cho tài xế ' . $profile->user->name . '.');
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
