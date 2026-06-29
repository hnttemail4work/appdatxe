<?php

namespace App\Http\Controllers\Api\Operator;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;

class PassengerController extends Controller
{
    public function index(Request $request)
    {
        $operatorId = $request->user()->id;

        $passengers = Booking::query()
            ->with(['schedule.vehicle', 'schedule.route'])
            ->whereHas('schedule.vehicle', fn ($q) => $q->where('operator_id', $operatorId))
            ->whereHas('schedule', fn ($q) => $q->where('departure_time', '>=', now()))
            ->where('trip_status', '!=', 'cancelled')
            ->join('schedules', 'bookings.schedule_id', '=', 'schedules.id')
            ->orderBy('schedules.departure_time')
            ->select('bookings.*')
            ->paginate(15);

        $data = collect($passengers->items())->map(fn (Booking $b) => [
            'id'                => $b->id,
            'trip_code'         => $b->schedule?->shortTripCode(),
            'booking_reference' => $b->booking_reference,
            'contact_phone'     => $b->contact_phone,
            'passenger_name'    => $b->passenger_name,
            'pickup_time'       => $b->pickupTimeLabel(),
            'pickup_label'      => $b->driverPickupDetailLabel(),
            'dropoff_label'     => $b->driverDropoffDetailLabel(),
            'seat_numbers'      => $b->seat_numbers,
            'total_price'       => $b->total_price,
            'payment_status'    => $b->payment_status,
            'trip_status'       => $b->trip_status,
            'departure_time'    => $b->schedule?->departure_time?->toIso8601String(),
        ]);

        return response()->json([
            'success'    => true,
            'data'       => $data,
            'pagination' => [
                'total'        => $passengers->total(),
                'per_page'     => $passengers->perPage(),
                'current_page' => $passengers->currentPage(),
                'last_page'    => $passengers->lastPage(),
            ],
        ]);
    }
}
