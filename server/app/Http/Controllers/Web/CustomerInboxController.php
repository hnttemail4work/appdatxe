<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CustomerInboxMessage;
use App\Services\CustomerInboxService;
use App\Services\TripChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CustomerInboxController extends Controller
{
    public function __construct(
        private readonly CustomerInboxService $inbox,
        private readonly TripChatService $tripChat,
    ) {}

    public function poll()
    {
        $userId = (int) Auth::id();
        $counts = $this->tripChat->mergeCustomerInboxUnread(
            $this->inbox->unreadCounts($userId),
            $userId,
        );

        return response()->json([
            'ok'     => true,
            'unread' => $counts,
        ]);
    }

    public function markRead(Request $request)
    {
        $validated = $request->validate([
            'message_id' => ['nullable', 'integer', 'min:1'],
            'category'   => ['nullable', 'string', Rule::in([
                CustomerInboxMessage::CATEGORY_INFO,
                CustomerInboxMessage::CATEGORY_NOTICE,
                'all',
            ])],
        ]);

        $userId = (int) Auth::id();

        if (! empty($validated['message_id'])) {
            $this->inbox->markMessageRead($userId, (int) $validated['message_id']);
        } elseif (($validated['category'] ?? null) === 'all') {
            // Giữ API cũ — UI chính không còn mark cả tab khi mở.
            $this->inbox->markAllRead($userId);
        } elseif (! empty($validated['category'])) {
            $this->inbox->markCategoryRead($userId, $validated['category']);
        }

        $counts = $this->tripChat->mergeCustomerInboxUnread(
            $this->inbox->unreadCounts($userId),
            $userId,
        );

        if ($request->expectsJson()) {
            return response()->json([
                'ok'     => true,
                'unread' => $counts,
            ]);
        }

        return redirect()->route('customer.account', [
            'tab'       => 'inbox',
            'inbox_tab' => 'info',
        ]);
    }
}
