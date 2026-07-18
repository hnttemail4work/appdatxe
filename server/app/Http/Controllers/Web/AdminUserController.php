<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CustomerProfileChangeRequest;
use App\Models\User;
use App\Services\CustomerProfileChangeService;
use App\Support\DriverDefaultPassword;
use App\Support\PageList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Quản lý tài khoản khách hàng (duyệt đăng ký / vô hiệu hóa / duyệt cập nhật hồ sơ). */
class AdminUserController extends Controller
{
    public function __construct(
        private readonly CustomerProfileChangeService $profileChanges,
    ) {
    }

    public function index(Request $request)
    {
        $status = (string) $request->query('status', 'all');
        $q = trim((string) $request->query('q', ''));

        $query = User::query()
            ->where('role', 'customer')
            ->with(['customerProfileChangeRequests' => function ($builder): void {
                $builder->where('status', CustomerProfileChangeRequest::STATUS_PENDING)->latest('id');
            }])
            ->orderByDesc('id');

        if ($status === 'pending') {
            $query->where('approval_status', User::APPROVAL_PENDING);
        } elseif (in_array($status, ['active', 'inactive', 'suspended'], true)) {
            $query->where('status', $status);
        }

        if ($q !== '') {
            $query->where(function ($builder) use ($q): void {
                $builder->where('name', 'like', '%' . $q . '%')
                    ->orWhere('phone', 'like', '%' . $q . '%')
                    ->orWhere('email', 'like', '%' . $q . '%');
            });
        }

        $users = $query->paginate(PageList::PER_PAGE)->withQueryString();

        return view('admin.users.index', [
            'users'  => $users,
            'status' => $status,
            'q'      => $q,
        ]);
    }

    public function deactivate(User $user)
    {
        $this->assertManagedCustomer($user);

        if ($user->status === 'inactive' && $user->approval_status !== User::APPROVAL_PENDING) {
            return back()->with('success', 'Tài khoản đã ở trạng thái vô hiệu hóa.');
        }

        $user->update(['status' => 'inactive']);

        return back()->with('success', 'Đã vô hiệu hóa tài khoản khách. Họ sẽ không đăng nhập được.');
    }

    public function activate(User $user)
    {
        $this->assertManagedCustomer($user);

        $user->update([
            'status'          => 'active',
            'approval_status' => User::APPROVAL_APPROVED,
        ]);

        return back()->with('success', 'Đã duyệt / kích hoạt tài khoản khách.');
    }

    public function reject(User $user)
    {
        $this->assertManagedCustomer($user);

        $user->update([
            'status'          => 'inactive',
            'approval_status' => User::APPROVAL_REJECTED,
        ]);

        return back()->with('success', 'Đã từ chối hồ sơ đăng ký khách.');
    }

    public function approveChange(CustomerProfileChangeRequest $change)
    {
        $this->assertManagedCustomer($change->user);

        $this->profileChanges->approve($change, Auth::user());

        return back()->with('success', 'Đã duyệt cập nhật thông tin khách.');
    }

    public function rejectChange(Request $request, CustomerProfileChangeRequest $change)
    {
        $this->assertManagedCustomer($change->user);

        $note = trim((string) $request->input('admin_note', ''));
        $this->profileChanges->reject($change, Auth::user(), $note !== '' ? $note : null);

        return back()->with('success', 'Đã từ chối yêu cầu cập nhật thông tin.');
    }

    public function resetPassword(User $user)
    {
        $this->assertManagedCustomer($user);

        $plain = DriverDefaultPassword::resetToRandom($user, true);

        return back()
            ->with('success', 'Đã đặt lại PIN cho khách hàng.')
            ->with('customer_password_reset', [
                'password' => $plain,
                'name'     => $user->preferredDisplayName(),
                'phone'    => $user->phone,
            ]);
    }

    private function assertManagedCustomer(?User $user): void
    {
        if (Auth::user()?->role !== 'admin' || ! $user || ! $user->isCustomer()) {
            abort(403);
        }

        if ((int) $user->id === (int) Auth::id()) {
            abort(403, 'Không thể tự vô hiệu hóa tài khoản đang đăng nhập.');
        }
    }
}
