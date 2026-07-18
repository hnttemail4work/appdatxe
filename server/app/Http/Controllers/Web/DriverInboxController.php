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
            'category' => ['nullable', 'string', Rule::in([
                DriverInboxMessage::CATEGORY_INFO,
                DriverInboxMessage::CATEGORY_NOTICE,
                'all',
            ])],
        ]);

        $userId = (int) Auth::id();
        $category = $validated['category'] ?? 'all';

        if ($category === 'all') {
            $this->inbox->markAllRead($userId);
        } else {
            $this->inbox->markCategoryRead($userId, $category);
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
            'inbox_tab' => $category === 'all' ? 'notice' : $category,
        ]);
    }
}
