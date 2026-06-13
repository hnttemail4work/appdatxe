<?php

namespace App\Http\Controllers\Api\Operator;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PassengerController extends Controller
{
    public function index(Request $request)
    {
        $operatorId = $request->user()->id;

        $passengers = DB::table('bookings')
            ->join('schedules', 'bookings.schedule_id', '=', 'schedules.id')
            ->join('vehicles', 'schedules.vehicle_id', '=', 'vehicles.id')
            ->join('users', 'bookings.customer_id', '=', 'users.id')
            ->where('vehicles.operator_id', $operatorId)
            ->where('schedules.departure_time', '>=', now())
            ->where('bookings.trip_status', '!=', 'cancelled')
            ->select(
                'bookings.id',
                'bookings.ticket_code',
                'bookings.booking_reference',
                'bookings.seat_numbers',
                'bookings.total_price',
                'bookings.payment_status',
                'bookings.trip_status',
                'users.id as customer_id',
                'users.name as customer_name',
                'users.phone',
                'schedules.departure_time',
                'schedules.available_seats',
                DB::raw('(SELECT GROUP_CONCAT(sr.seat_number) FROM seat_reservations sr WHERE sr.booking_id = bookings.id AND sr.status = "booked") as reserved_seats')
            )
            ->orderBy('schedules.departure_time')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $passengers->items(),
            'pagination' => [
                'total' => $passengers->total(),
                'per_page' => $passengers->perPage(),
                'current_page' => $passengers->currentPage(),
                'last_page' => $passengers->lastPage(),
            ],
        ]);
    }
}
