<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DriverInboxMessage;
use App\Services\DriverInboxService;
use App\Services\TripChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class DriverInboxController extends Controller
{
    public function __construct(
        private readonly DriverInboxService $inbox,
        private readonly TripChatService $tripChat,
    ) {}

    public function markRead(Request $request)
    {
        $validated = $request->validate([
            'message_id' => ['nullable', 'integer', 'min:1'],
            'category'   => ['nullable', 'string', Rule::in([
                DriverInboxMessage::CATEGORY_INFO,
                DriverInboxMessage::CATEGORY_NOTICE,
                'all',
            ])],
        ]);

        $userId = (int) Auth::id();

        if (! empty($validated['message_id'])) {
            $this->inbox->markMessageRead($userId, (int) $validated['message_id']);
        } elseif (($validated['category'] ?? null) === 'all') {
            $this->inbox->markAllRead($userId);
        } elseif (! empty($validated['category'])) {
            $this->inbox->markCategoryRead($userId, $validated['category']);
        }

        $counts = $this->tripChat->mergeInboxUnread(
            $this->inbox->unreadCounts($userId),
            $userId,
        );

        if ($request->expectsJson()) {
            return response()->json([
                'ok'     => true,
                'unread' => $counts,
            ]);
        }

        return redirect()->route('driver.dashboard', [
            'tab'       => 'inbox',
            'inbox_tab' => 'notice',
        ]);
    }
}
