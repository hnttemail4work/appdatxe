<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingWorkflowService;
use App\Services\GuestTripWatchService;
use App\Services\TripReviewService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class GuestTripWatchController extends Controller
{
    public function __construct(
        private readonly GuestTripWatchService $watchlist,
        private readonly TripReviewService $reviews,
        private readonly BookingWorkflowService $workflow,
    ) {
    }

    public function index()
    {
        return response()->json([
            'synced_at'        => now()->toIso8601String(),
            'watchlist_count'  => $this->watchlist->watchlistCount(),
            'trips'            => $this->watchlist->visibleTrips(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'booking_ref'   => ['required', 'string', 'max:64'],
            'contact_phone' => ['required', 'string', 'max:30'],
            'sentiment'     => ['required', 'in:like,dislike'],
            'comment'       => ['nullable', 'string', 'max:500'],
        ]);

        $booking = Booking::query()
            ->with(['schedule', 'tripReview'])
            ->where('booking_reference', $validated['booking_ref'])
            ->first();

        if (! $booking) {
            return response()->json(['message' => 'Không tìm thấy vé.'], 404);
        }

        try {
            $this->reviews->submit(
                $booking,
                $validated['contact_phone'],
                $validated['sentiment'],
                $validated['comment'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok'      => true,
            'message' => 'Cảm ơn bạn đã phản hồi!',
        ]);
    }

    public function cancelBooking(Request $request)
    {
        $validated = $request->validate([
            'booking_ref'            => ['required', 'string', 'max:64'],
            'contact_phone'          => ['required', 'string', 'max:30'],
            'cancellation_reason_id' => ['nullable', 'integer', 'exists:cancellation_reasons,id'],
        ]);

        $booking = Booking::query()
            ->with(['schedule', 'tripReview'])
            ->where('booking_reference', $validated['booking_ref'])
            ->first();

        if (! $booking) {
            return response()->json(['message' => 'Không tìm thấy vé.'], 404);
        }

        if (! $this->watchlist->bookingInWatchlist($booking, $validated['contact_phone'])) {
            return response()->json(['message' => 'Không tìm thấy chuyến trong phiên đặt vé của bạn.'], 422);
        }

        if (! $this->watchlist->canCancel($booking)) {
            return response()->json(['message' => 'Chuyến này không thể hủy.'], 422);
        }

        try {
            $this->workflow->cancelByPhone(
                $booking,
                $validated['contact_phone'],
                isset($validated['cancellation_reason_id']) ? (int) $validated['cancellation_reason_id'] : null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok'      => true,
            'message' => 'Đã hủy chuyến thành công.',
        ]);
    }
}
