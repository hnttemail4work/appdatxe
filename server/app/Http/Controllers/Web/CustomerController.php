<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingAudit;
use App\Models\DriverProfile;
use App\Models\Schedule;
use App\Models\SeatReservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    public function dashboard(Request $request)
    {
        $schedules = collect();
        $searchPerformed = false;

        if ($request->filled(['departure', 'destination', 'date'])) {
            $searchPerformed = true;
            $validated = $request->validate([
                'departure'    => ['required', 'string', 'max:255'],
                'destination'  => ['required', 'string', 'max:255'],
                'date'         => ['required', 'date'],
                'vehicle_type' => ['nullable', 'string', 'in:limousine,sedan,suv'],
            ]);

            $schedules = Schedule::query()
                ->with(['route', 'vehicle', 'driver.driverProfile'])
                ->withCount([
                    'seatReservations as active_reservations_count' => function ($q): void {
                        $q->whereIn('status', ['held', 'booked'])
                            ->where(function ($n): void {
                                $n->whereNull('expires_at')->orWhere('expires_at', '>', now());
                            });
                    },
                ])
                ->whereIn('status', ['scheduled', 'running'])
                ->whereHas('route', function ($q) use ($validated): void {
                    $q->where('departure', $validated['departure'])
                      ->where('destination', $validated['destination']);
                })
                ->whereDate('departure_time', $validated['date'])
                ->when($validated['vehicle_type'] ?? null, function ($q, $type): void {
                    $q->whereHas('vehicle', fn ($v) => $v->where('type', $type));
                })
                ->orderBy('departure_time')
                ->get()
                ->map(function (Schedule $s): Schedule {
                    $s->available_seats = max((int) $s->vehicle->capacity - (int) $s->active_reservations_count, 0);
                    return $s;
                });
        }

        $bookings = Auth::user()
            ->bookings()
            ->with(['schedule.route', 'schedule.vehicle'])
            ->latest()
            ->get();

        $availableDrivers = DriverProfile::query()
            ->where('status', 'active')
            ->where('availability_status', 'available')
            ->with(['user', 'operator'])
            ->get();

        return view('customer.dashboard', compact('schedules', 'bookings', 'searchPerformed', 'availableDrivers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'schedule_id'     => ['required', 'exists:schedules,id'],
            'seat_numbers'    => ['required', 'string', 'max:255'],
            'pickup_address'  => ['nullable', 'string', 'max:255'],
            'dropoff_address' => ['nullable', 'string', 'max:255'],
            'notes'           => ['nullable', 'string'],
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

        $booking = DB::transaction(function () use ($validated, $seatNumbers) {
            $schedule = Schedule::query()
                ->with(['route', 'vehicle', 'seatReservations'])
                ->lockForUpdate()
                ->findOrFail($validated['schedule_id']);

            $this->assertSeatsAvailable($schedule, $seatNumbers);

            $totalPrice  = round((float) $schedule->route->base_price * count($seatNumbers), 2);
            $holdExpires = now()->addMinutes(15);

            $booking = Booking::query()->create([
                'customer_id'     => Auth::id(),
                'schedule_id'     => $schedule->id,
                'seat_numbers'    => $seatNumbers,
                'ticket_code'     => 'TCK-' . Str::upper(Str::random(10)),
                'booking_reference' => 'BK-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(6)),
                'total_price'     => $totalPrice,
                'payment_status'  => 'unpaid',
                'trip_status'     => 'confirmed',
                'booking_status'  => 'pending',
                'pickup_address'  => $validated['pickup_address'] ?? null,
                'dropoff_address' => $validated['dropoff_address'] ?? null,
                'notes'           => $validated['notes'] ?? null,
                'hold_expires_at' => $holdExpires,
            ]);

            foreach ($seatNumbers as $seat) {
                SeatReservation::query()->create([
                    'schedule_id'       => $schedule->id,
                    'booking_id'        => $booking->id,
                    'customer_id'       => Auth::id(),
                    'seat_number'       => $seat,
                    'reservation_token' => (string) Str::uuid(),
                    'status'            => 'held',
                    'expires_at'        => $holdExpires,
                ]);
            }

            $this->syncSeats($schedule);
            $this->audit($booking, Auth::id(), 'booking_created', null, $booking->toArray());

            return $booking;
        });

        return redirect()->route('customer.dashboard')
            ->with('success', 'Đặt vé thành công! Mã vé: ' . $booking->ticket_code);
    }

    public function markPaid(Request $request, Booking $booking)
    {
        if ($booking->customer_id !== Auth::id()) {
            abort(403);
        }

        if ($booking->booking_status === 'cancelled') {
            return back()->withErrors(['booking' => 'Vé đã bị hủy.']);
        }

        DB::transaction(function () use ($booking) {
            $booking->update([
                'payment_status' => 'paid',
                'booking_status' => 'confirmed',
                'confirmed_at'   => now(),
            ]);

            $booking->seatReservations()->update(['status' => 'booked', 'expires_at' => null]);
            $this->syncSeats($booking->schedule()->with(['vehicle', 'seatReservations'])->first());
            $this->audit($booking, Auth::id(), 'payment_marked_paid', null, $booking->fresh()->toArray());
        });

        return redirect()->route('customer.dashboard')
            ->with('success', 'Đã xác nhận thanh toán. Vé của bạn đã được xác nhận.');
    }

    public function cancel(Request $request, Booking $booking)
    {
        if ($booking->customer_id !== Auth::id()) {
            abort(403);
        }

        if (in_array($booking->booking_status, ['cancelled', 'rejected'])) {
            return back()->withErrors(['booking' => 'Vé này đã hủy rồi.']);
        }

        DB::transaction(function () use ($booking) {
            $before = $booking->toArray();

            $booking->update([
                'booking_status' => 'cancelled',
                'trip_status'    => 'cancelled',
                'payment_status' => $booking->payment_status === 'paid' ? 'refunded' : 'unpaid',
                'cancelled_at'   => now(),
            ]);

            $booking->seatReservations()->update(['status' => 'released', 'expires_at' => now()]);
            $this->syncSeats($booking->schedule()->with(['vehicle', 'seatReservations'])->first());
            $this->audit($booking, Auth::id(), 'booking_cancelled', $before, $booking->fresh()->toArray());
        });

        return redirect()->route('customer.dashboard')->with('success', 'Đã hủy vé thành công.');
    }

    private function assertSeatsAvailable(Schedule $schedule, array $seats): void
    {
        $capacity = (int) $schedule->vehicle->capacity;
        foreach ($seats as $s) {
            if (! is_numeric($s) || (int) $s < 1 || (int) $s > $capacity) {
                abort(422, 'Số ghế không hợp lệ: ' . $s);
            }
        }

        $taken = SeatReservation::query()
            ->where('schedule_id', $schedule->id)
            ->whereIn('seat_number', $seats)
            ->whereIn('status', ['held', 'booked'])
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();

        if ($taken) {
            abort(422, 'Một hoặc nhiều ghế đã được đặt trước.');
        }
    }

    private function syncSeats(Schedule $schedule): void
    {
        $schedule->loadMissing(['vehicle', 'seatReservations']);
        $active = $schedule->seatReservations
            ->filter(fn ($r) => in_array($r->status, ['held', 'booked'], true)
                && (! $r->expires_at || $r->expires_at->isFuture()))
            ->count();
        $schedule->forceFill(['available_seats' => max((int) $schedule->vehicle->capacity - $active, 0)])->save();
    }

    private function audit(Booking $booking, ?int $actor, string $action, ?array $before, ?array $after): void
    {
        BookingAudit::query()->create([
            'booking_id'   => $booking->id,
            'actor_id'     => $actor,
            'action'       => $action,
            'before_state' => $before,
            'after_state'  => $after,
        ]);
    }
}
