<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CustomerInboxMessage;
use App\Services\CustomerAccountService;
use App\Services\CustomerInboxService;
use App\Services\CustomerProfileChangeService;
use App\Services\CustomerWalletService;
use App\Services\TripChatService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerAccountService $accounts,
        private readonly CustomerProfileChangeService $profileChanges,
        private readonly CustomerInboxService $inbox,
        private readonly CustomerWalletService $wallets,
        private readonly TripChatService $tripChat,
    ) {
    }

    public function account(Request $request)
    {
        $user = $request->user();
        $tab = $request->query('tab', 'account');

        if (in_array($tab, ['trips', 'reviews'], true)) {
            return redirect()->route('booking.trips');
        }
        if (! in_array($tab, ['account', 'profile', 'info', 'update', 'password', 'inbox', 'wallet'], true)) {
            $tab = 'account';
        }

        $this->accounts->linkExistingBookings($user);

        $inboxUnread = $this->tripChat->mergeCustomerInboxUnread(
            $this->inbox->unreadCounts((int) $user->id),
            (int) $user->id,
        );
        $inboxTab = in_array($request->query('inbox_tab'), [
            CustomerInboxMessage::CATEGORY_INFO,
            CustomerInboxMessage::CATEGORY_NOTICE,
        ], true)
            ? (string) $request->query('inbox_tab')
            : CustomerInboxMessage::CATEGORY_INFO;

        $wallet = null;
        $pendingDeposits = collect();
        $walletHistory = collect();
        if ($tab === 'wallet') {
            $wallet = $this->wallets->walletFor($user);
            $pendingDeposits = $wallet->transactions()
                ->where('type', 'deposit')
                ->where('status', 'pending')
                ->latest('id')
                ->get();
            $walletHistory = $wallet->transactions()
                ->where('type', 'deposit')
                ->latest('id')
                ->limit(30)
                ->get();
        }

        return view('customer.account', [
            'user'           => $user,
            'profile'        => $this->accounts->profileSummary($user),
            'pendingChange'  => $this->profileChanges->pendingFor($user),
            'activeTab'      => $tab,
            'inboxUnread'    => $inboxUnread,
            'inboxTab'       => $inboxTab,
            'inboxNoticeMessages' => $tab === 'inbox'
                ? $this->inbox->listFor((int) $user->id, CustomerInboxMessage::CATEGORY_NOTICE)
                : collect(),
            'inboxInfoMessages' => $tab === 'inbox'
                ? $this->inbox->listFor((int) $user->id, CustomerInboxMessage::CATEGORY_INFO)
                : collect(),
            'inboxChatThreads' => $tab === 'inbox'
                ? $this->tripChat->recentThreadsForCustomer((int) $user->id)
                : collect(),
            'wallet' => $wallet,
            'pendingDeposits' => $pendingDeposits,
            'walletHistory' => $walletHistory,
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
            ->route('customer.account', ['tab' => 'update'])
            ->with('success', 'Đã gửi yêu cầu cập nhật. Admin sẽ duyệt trước khi áp dụng.');
    }

    public function updateInfo(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'gender'         => ['required', 'in:male,female'],
            'date_of_birth'  => ['required', 'date', 'before:today'],
        ], [
            'name.required'          => 'Vui lòng nhập họ tên.',
            'gender.required'        => 'Vui lòng chọn giới tính.',
            'date_of_birth.required' => 'Vui lòng chọn ngày sinh.',
            'date_of_birth.before'   => 'Ngày sinh không hợp lệ.',
        ]);

        $name = trim($validated['name']);
        if ($name === '' || preg_match('/^[\d\s.+()-]+$/', $name)) {
            throw ValidationException::withMessages([
                'name' => 'Vui lòng nhập họ tên thật (không dùng số điện thoại).',
            ]);
        }

        $user->forceFill([
            'name'          => $name,
            'gender'        => $validated['gender'],
            'date_of_birth' => $validated['date_of_birth'],
        ])->save();

        return redirect()
            ->route('customer.account', ['tab' => 'info'])
            ->with('success', 'Đã cập nhật thông tin cá nhân.');
    }
}
