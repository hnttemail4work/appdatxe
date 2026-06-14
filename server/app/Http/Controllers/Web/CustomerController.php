<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\DriverTripRequest;
use App\Models\Schedule;
use App\Services\BookingWorkflowService;
use App\Services\DriverTripRequestService;
use App\Services\TripListingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class CustomerController extends Controller
{
    public function __construct(
        private readonly BookingWorkflowService $workflow,
        private readonly TripListingService $tripListing,
        private readonly DriverTripRequestService $driverRequests,
    ) {
    }

    public function dashboard(Request $request)
    {
        $filters = $this->tripListing->filtersFromRequest($request);
        $schedules = $this->tripListing->query($filters);

        $bookings = Auth::user()
            ->bookings()
            ->with(['schedule.route', 'schedule.vehicle', 'paymentTransactions'])
            ->latest()
            ->get();

        $pendingDriverRequests = DriverTripRequest::query()
            ->where('customer_id', Auth::id())
            ->whereIn('status', ['pending', 'accepted'])
            ->where(fn ($q) => $q->where('status', 'accepted')->orWhere(fn ($p) => $p
                ->where('status', 'pending')
                ->where(fn ($e) => $e->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ))
            ->with(['driver', 'driverProfile'])
            ->get()
            ->keyBy('schedule_id');

        $routeOptions = Schedule::query()
            ->with('route')
            ->whereIn('status', ['scheduled', 'running'])
            ->where('departure_time', '>=', now()->startOfDay())
            ->get()
            ->pluck('route')
            ->unique('id');

        return view('customer.dashboard', compact(
            'schedules',
            'bookings',
            'filters',
            'pendingDriverRequests',
            'routeOptions',
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'schedule_id'     => ['required', 'exists:schedules,id'],
            'seat_numbers'    => ['required', 'string', 'max:255'],
            'pickup_address'  => ['nullable', 'string', 'max:255'],
            'dropoff_address' => ['nullable', 'string', 'max:255'],
            'notes'           => ['nullable', 'string'],
            'driver_code'     => ['nullable', 'string', 'max:20'],
        ]);

        $seatNumbers = collect(explode(',', $validated['seat_numbers']))
            ->map(fn ($s): string => trim((string) $s))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (count($seatNumbers) === 0) {
            return back()->withErrors(['seat_numbers' => 'Vui lòng nhập ít nhất một số ghế'])->withInput();
        }

        $schedule = Schedule::query()->with(['route', 'vehicle'])->findOrFail($validated['schedule_id']);

        $booking = $this->workflow->createBooking(
            $schedule,
            Auth::id(),
            $seatNumbers,
            $validated['pickup_address'] ?? null,
            $validated['dropoff_address'] ?? null,
            $validated['notes'] ?? null,
        );

        $driverCode = trim((string) ($validated['driver_code'] ?? ''));
        $flash = 'Đặt vé thành công! Mã vé: ' . $booking->ticket_code . '. ';

        if ($driverCode !== '') {
            try {
                $this->driverRequests->requestDriver($schedule->fresh(), Auth::id(), $driverCode);
                $flash .= 'Đã gửi yêu cầu tới tài xế — chờ phản hồi.';
            } catch (InvalidArgumentException $e) {
                $flash .= 'Vé đã tạo nhưng không gửi được yêu cầu tài xế: ' . $e->getMessage();
            }
        } else {
            $flash .= 'Quản lý sẽ tự phân bổ tài xế cho chuyến.';
        }

        return redirect()->route('customer.dashboard')->with('success', $flash);
    }

    public function cancelDriverRequest(Request $request, DriverTripRequest $driverTripRequest)
    {
        try {
            $this->driverRequests->cancelByCustomer($driverTripRequest, Auth::id());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['driver_request' => $e->getMessage()]);
        }

        return back()->with('success', 'Đã hủy yêu cầu mời tài xế.');
    }

    public function claimPayment(Request $request, Booking $booking)
    {
        if ($booking->customer_id !== Auth::id()) {
            abort(403);
        }

        try {
            $this->workflow->customerClaimPayment($booking, Auth::id());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect()->route('customer.dashboard')
            ->with('success', 'Đã gửi yêu cầu xác nhận thanh toán. Quản lý/admin sẽ kiểm tra và xác nhận.');
    }

    public function confirmTripComplete(Request $request, Booking $booking)
    {
        if ($booking->customer_id !== Auth::id()) {
            abort(403);
        }

        try {
            $this->workflow->customerConfirmTripComplete($booking, Auth::id());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect()->route('customer.dashboard')
            ->with('success', 'Đã xác nhận hoàn chuyến. Cảm ơn bạn đã sử dụng dịch vụ!');
    }

    public function cancel(Request $request, Booking $booking)
    {
        if ($booking->customer_id !== Auth::id()) {
            abort(403);
        }

        try {
            $this->workflow->cancelByCustomer($booking, Auth::id());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect()->route('customer.dashboard')->with('success', 'Đã hủy vé thành công.');
    }
}
