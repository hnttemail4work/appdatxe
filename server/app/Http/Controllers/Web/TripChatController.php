<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\CustomerChatMessagesRequest;
use App\Http\Requests\Chat\CustomerChatSendRequest;
use App\Http\Requests\Chat\DriverSendMessageRequest;
use App\Models\Booking;
use App\Services\CustomerInboxService;
use App\Services\DriverInboxService;
use App\Services\TripChatService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class TripChatController extends Controller
{
    public function __construct(
        private readonly TripChatService $chat,
        private readonly DriverInboxService $driverInbox,
        private readonly CustomerInboxService $customerInbox,
    ) {
    }

    public function customerMessages(CustomerChatMessagesRequest $request): JsonResponse
    {
        [$booking, $afterId] = $this->resolveCustomerBooking($request);
        $this->chat->markCustomerRead($booking);

        return $this->messagesResponse($booking, $afterId);
    }

    public function customerSend(CustomerChatSendRequest $request): JsonResponse
    {
        [$booking] = $this->resolveCustomerBooking($request);

        try {
            $message = $this->chat->send($booking, 'customer', (string) $request->input('body'), auth()->user());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => $this->chat->serialize($message),
        ], 201);
    }

    public function driverMessages(Request $request, Booking $booking): JsonResponse
    {
        $this->authorizeDriver($booking);
        $this->chat->markDriverRead($booking);

        return $this->messagesResponse($booking, max(0, (int) $request->query('after_id', 0)));
    }

    public function driverSend(DriverSendMessageRequest $request, Booking $booking): JsonResponse
    {
        $this->authorizeDriver($booking);
        $validated = $request->validated();

        try {
            $message = $this->chat->send($booking, 'driver', $validated['body'], auth()->user());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => $this->chat->serialize($message),
        ], 201);
    }

    /** @return array{0: Booking, 1: int} */
    private function resolveCustomerBooking(FormRequest $request): array
    {
        $validated = $request->validated();
        $user = $request->user();
        $booking = Booking::query()
            ->with('schedule')
            ->where('booking_reference', $validated['booking_reference'])
            ->firstOrFail();

        if (! $user || $user->role !== 'customer') {
            abort(403, 'Vui lòng đăng nhập tài khoản khách.');
        }

        $ownsBooking = (int) $booking->customer_id === (int) $user->id;
        $matchesPhone = $user->phone && $booking->matchesContactPhone((string) $user->phone);

        if (! $ownsBooking && ! $matchesPhone) {
            abort(403, 'Không xác thực được chuyến đi.');
        }

        if (! $ownsBooking && $matchesPhone && ! $booking->customer_id) {
            $booking->update(['customer_id' => $user->id]);
            $booking->refresh();
        }

        return [$booking, max(0, (int) ($validated['after_id'] ?? 0))];
    }

    private function authorizeDriver(Booking $booking): void
    {
        $user = auth()->user();
        if (! $user || ! $this->chat->driverCanAccess($booking, $user)) {
            abort(403);
        }
    }

    private function messagesResponse(Booking $booking, int $afterId): JsonResponse
    {
        $user = auth()->user();

        return response()->json([
            'open'           => $this->chat->isOpen($booking),
            'status_message' => $this->chat->statusMessage($booking),
            'messages'       => $this->chat->messages($booking, $afterId)
                ->map(fn ($message) => $this->chat->serialize($message))
                ->values(),
            'unread'         => $user && $user->role === 'driver'
                ? $this->chat->mergeInboxUnread(
                    $this->driverInbox->unreadCounts((int) $user->id),
                    (int) $user->id,
                )
                : ($user && $user->role === 'customer'
                    ? $this->chat->mergeCustomerInboxUnread(
                        $this->customerInbox->unreadCounts((int) $user->id),
                        (int) $user->id,
                    )
                    : null),
        ]);
    }
}
