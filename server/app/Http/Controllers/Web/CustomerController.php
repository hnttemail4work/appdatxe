<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CustomerInboxMessage;
use App\Services\CustomerAccountService;
use App\Services\CustomerInboxService;
use App\Services\CustomerProfileChangeService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerAccountService $accounts,
        private readonly CustomerProfileChangeService $profileChanges,
        private readonly CustomerInboxService $inbox,
    ) {
    }

    public function account(Request $request)
    {
        $user = $request->user();
        $tab = $request->query('tab', 'profile');

        if (! in_array($tab, ['profile', 'trips', 'reviews', 'inbox'], true)) {
            $tab = 'profile';
        }

        $this->accounts->linkExistingBookings($user);

        $inboxUnread = $this->inbox->unreadCounts((int) $user->id);
        $inboxTab = in_array($request->query('inbox_tab'), [
            CustomerInboxMessage::CATEGORY_INFO,
            CustomerInboxMessage::CATEGORY_NOTICE,
        ], true)
            ? (string) $request->query('inbox_tab')
            : CustomerInboxMessage::CATEGORY_NOTICE;

        return view('customer.account', [
            'user'           => $user,
            'profile'        => $this->accounts->profileSummary($user),
            'pendingChange'  => $this->profileChanges->pendingFor($user),
            'activeTab'      => $tab,
            'tripHistory'    => $tab === 'trips'
                ? $this->accounts->tripHistory($user, (int) $request->query('page', 1))
                    ->through(fn ($booking) => $this->accounts->serializeTrip($booking))
                : null,
            'reviews'        => $tab === 'reviews'
                ? $this->accounts->reviews($user, (int) $request->query('page', 1))
                : null,
            'recentTrips'    => $tab === 'profile'
                ? $this->accounts->recentTrips($user, 5)->map(fn ($booking) => $this->accounts->serializeTrip($booking))
                : collect(),
            'inboxUnread'    => $inboxUnread,
            'inboxTab'       => $inboxTab,
            'inboxNoticeMessages' => $this->inbox->listFor((int) $user->id, CustomerInboxMessage::CATEGORY_NOTICE),
            'inboxInfoMessages' => $this->inbox->listFor((int) $user->id, CustomerInboxMessage::CATEGORY_INFO),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        try {
            $this->profileChanges->submit($user, $request);
        } catch (ValidationException $e) {
            throw $e;
        }

        return redirect()
            ->route('customer.account', ['tab' => 'profile'])
            ->with('success', 'Đã gửi yêu cầu cập nhật. Admin sẽ duyệt trước khi áp dụng.');
    }
}
