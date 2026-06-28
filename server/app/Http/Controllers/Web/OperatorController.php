<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\DriverTripSettlement;
use App\Models\DriverWalletTransaction;
use App\Models\Schedule;
use App\Models\User;
use App\Services\DriverTripRequestService;
use App\Services\DriverWalletService;
use App\Services\ScheduleLifecycleService;
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

        $todayTrips = Schedule::query()
            ->with(['route', 'vehicle', 'driver', 'template'])
            ->withCount([
                'bookings as active_bookings_count' => fn ($q) => $q->whereNotIn('booking_status', ['cancelled', 'rejected']),
            ])
            ->whereHas('bookings', fn ($q) => $q->whereNotIn('booking_status', ['cancelled', 'rejected']))
            ->whereHas('vehicle', fn ($q) => $q->where('operator_id', $user->id))
            ->whereDate('service_date', today())
            ->orderBy('departure_time')
            ->get();

        $passengers = Booking::query()
            ->with(['schedule.route', 'schedule.vehicle', 'schedule.template', 'schedule.driver', 'schedule.tripSettlement'])
            ->whereHas('schedule.vehicle', fn ($q) => $q->where('operator_id', $user->id))
            ->where(function ($q): void {
                $q->whereNotIn('booking_status', ['cancelled', 'rejected'])
                    ->orWhereNotNull('expired_at');
            })
            ->latest()
            ->limit(50)
            ->get();

        $referralBookings = Booking::query()
            ->with(['schedule.route', 'schedule.vehicle'])
            ->whereNotNull('referral_code')
            ->where('referral_code', '!=', '')
            ->whereNotIn('booking_status', ['cancelled', 'rejected'])
            ->whereHas('schedule.vehicle', fn ($q) => $q->where('operator_id', $user->id))
            ->latest()
            ->limit(100)
            ->get();

        $pendingSettleCount = $this->driverWallet->settlementsAwaitingCodeForOperator($user->id)->count();

        return view('operator.dashboard', compact('drivers', 'driverList', 'passengers', 'referralBookings', 'todayTrips', 'pendingDriverCount', 'pendingSettleCount'));
    }

    public function driverWallet()
    {
        $this->driverWallet->enforceDeadlines();

        $awaitingCode = $this->driverWallet->settlementsAwaitingCodeForOperator(Auth::id());
        $codesIssued = $this->driverWallet->codesAwaitingDriverForOperator(Auth::id());
        $depositsPending = $this->driverWallet->pendingDepositsForOperator(Auth::id());

        return view('operator.driver-wallet', compact('awaitingCode', 'codesIssued', 'depositsPending'));
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

        return back()->with('success', 'Đã cộng tiền vào ví tài xế — tài xế có thể nhận cuốc bình thường.');
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

        return back()->with('success', 'Chuyến đã có tài xế nhận.');
    }
}
