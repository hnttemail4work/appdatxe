<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class BookingActionController extends Controller
{
    public function __construct(private readonly BookingWorkflowService $workflow)
    {
    }

    public function driverCompleteTrip(Request $request, Booking $booking): JsonResponse
    {
        try {
            $this->workflow->driverCompleteTrip($booking, $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Trip marked complete.', 'data' => $booking->fresh()]);
    }
}
