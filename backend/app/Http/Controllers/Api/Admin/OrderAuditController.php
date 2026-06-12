<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;

class OrderAuditController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $status = $request->input('status');
        $paymentStatus = $request->input('payment_status');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $query = Booking::with([
            'customer' => function ($query) {
                $query->select('id', 'name', 'email', 'phone');
            },
            'schedule' => function ($query) {
                $query->with(['route' => function ($q) {
                    $q->select('id', 'departure_city', 'destination_city');
                }, 'vehicle' => function ($q) {
                    $q->select('id', 'license_plate', 'type');
                }]);
            },
        ]);

        if ($status) {
            $query->where('trip_status', $status);
        }

        if ($paymentStatus) {
            $query->where('payment_status', $paymentStatus);
        }

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $bookings = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $bookings->items(),
            'pagination' => [
                'total' => $bookings->total(),
                'per_page' => $bookings->perPage(),
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, Booking $booking)
    {
        $booking->load([
            'customer' => function ($query) {
                $query->select('id', 'name', 'email', 'phone', 'role', 'status');
            },
            'schedule' => function ($query) {
                $query->with(['route', 'vehicle']);
            },
            'seatReservations' => function ($query) {
                $query->select('id', 'booking_id', 'schedule_id', 'seat_number', 'status');
            },
            'paymentTransactions' => function ($query) {
                $query->select('id', 'booking_id', 'provider', 'amount', 'status', 'transaction_ref', 'created_at');
            },
            'audits' => function ($query) {
                $query->with(['actor' => function ($q) {
                    $q->select('id', 'name', 'role');
                }])->orderByDesc('created_at');
            },
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $booking->id,
                'ticket_code' => $booking->ticket_code,
                'booking_reference' => $booking->booking_reference,
                'customer' => $booking->customer,
                'schedule' => $booking->schedule,
                'seat_numbers' => json_decode($booking->seat_numbers, true),
                'reserved_seats' => $booking->seatReservations,
                'total_price' => $booking->total_price,
                'deposit_amount' => $booking->deposit_amount,
                'payment_status' => $booking->payment_status,
                'trip_status' => $booking->trip_status,
                'payment_transactions' => $booking->paymentTransactions,
                'audit_trail' => $booking->audits,
                'created_at' => $booking->created_at,
                'updated_at' => $booking->updated_at,
            ],
        ]);
    }
}
