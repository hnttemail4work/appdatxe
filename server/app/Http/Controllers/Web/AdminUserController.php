<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PageList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Quản lý tài khoản khách hàng (vô hiệu hóa / kích hoạt). */
class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $status = (string) $request->query('status', 'all');
        $q = trim((string) $request->query('q', ''));

        $query = User::query()
            ->where('role', 'customer')
            ->orderByDesc('id');

        if (in_array($status, ['active', 'inactive', 'suspended'], true)) {
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

        if ($user->status === 'inactive') {
            return back()->with('success', 'Tài khoản đã ở trạng thái vô hiệu hóa.');
        }

        $user->update(['status' => 'inactive']);

        return back()->with('success', 'Đã vô hiệu hóa tài khoản khách. Họ sẽ không đăng nhập được.');
    }

    public function activate(User $user)
    {
        $this->assertManagedCustomer($user);

        if ($user->status === 'active') {
            return back()->with('success', 'Tài khoản đã ở trạng thái hoạt động.');
        }

        $user->update(['status' => 'active']);

        return back()->with('success', 'Đã kích hoạt lại tài khoản khách.');
    }

    private function assertManagedCustomer(User $user): void
    {
        if (Auth::user()?->role !== 'admin' || ! $user->isCustomer()) {
            abort(403);
        }

        if ((int) $user->id === (int) Auth::id()) {
            abort(403, 'Không thể tự vô hiệu hóa tài khoản đang đăng nhập.');
        }
    }
}
