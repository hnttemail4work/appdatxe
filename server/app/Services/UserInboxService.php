<?php

namespace App\Services;

use App\Models\CustomerInboxMessage;
use App\Models\DriverInboxMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Entry point hộp thư (khách + tài xế). Luồng nghiệp vụ chỉ gọi facade này.
 */
class UserInboxService
{
    public function __construct(
        private readonly CustomerInboxService $customers,
        private readonly DriverInboxService $drivers,
    ) {}

    /**
     * @param  array<string, mixed>  $meta
     */
    public function notify(
        User $user,
        string $category,
        string $title,
        string $body,
        array $meta = [],
        ?int $createdBy = null,
        bool $push = false,
    ): ?Model {
        if ($user->role === 'customer') {
            return $this->customers->notify(
                (int) $user->id,
                $category,
                $title,
                $body,
                $meta,
                $createdBy,
            );
        }

        if ($user->role === 'driver') {
            return $this->drivers->notify(
                (int) $user->id,
                $category,
                $title,
                $body,
                $meta,
                $createdBy,
                $push,
            );
        }

        return null;
    }

    public function notifyRegistrationSuccess(User $user): void
    {
        $isDriver = $user->role === 'driver';
        $body = $isDriver
            ? 'Tài khoản tài xế đã tạo thành công. Hồ sơ đang chờ duyệt — bạn có thể xem app, nhận chuyến sau khi được duyệt.'
            : 'Tài khoản đã tạo thành công. Hồ sơ đang chờ duyệt CCCD — bạn có thể xem trang chủ, đặt xe sau khi được duyệt.';

        $this->notify(
            $user,
            $isDriver ? DriverInboxMessage::CATEGORY_NOTICE : CustomerInboxMessage::CATEGORY_NOTICE,
            'Đăng ký thành công',
            $body,
            ['type' => 'registration_success'],
            push: false,
        );
    }
}
