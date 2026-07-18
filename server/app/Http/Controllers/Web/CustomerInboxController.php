<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CustomerInboxMessage;
use App\Services\CustomerInboxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CustomerInboxController extends Controller
{
    public function __construct(
        private readonly CustomerInboxService $inbox,
    ) {}

    public function markRead(Request $request)
    {
        $validated = $request->validate([
            'category' => ['nullable', 'string', Rule::in([
                CustomerInboxMessage::CATEGORY_INFO,
                CustomerInboxMessage::CATEGORY_NOTICE,
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

        $counts = $this->inbox->unreadCounts($userId);

        if ($request->expectsJson()) {
            return response()->json([
                'ok'     => true,
                'unread' => $counts,
            ]);
        }

        return redirect()->route('customer.account', [
            'tab'       => 'inbox',
            'inbox_tab' => $category === 'all' ? 'notice' : $category,
        ]);
    }
}
