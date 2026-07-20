<?php

namespace App\Services;

use App\Models\CustomerInboxMessage;
use App\Models\DriverInboxMessage;
use App\Models\User;
use App\Support\Money;
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

    public function notifyDepositResult(User $user, int $amount, bool $approved): void
    {
        $amountLabel = Money::vnd($amount);
        $title = $approved ? 'Nạp ví thành công' : 'Nạp ví không thành công';
        $body = $approved
            ? 'Yêu cầu nạp '.$amountLabel.' đã được duyệt và cộng vào ví.'
            : 'Yêu cầu nạp '.$amountLabel.' không được duyệt. Liên hệ hỗ trợ nếu cần.';
        $isDriver = $user->role === 'driver';

        $this->notify(
            $user,
            $isDriver ? DriverInboxMessage::CATEGORY_NOTICE : CustomerInboxMessage::CATEGORY_NOTICE,
            $title,
            $body,
            [
                'type'   => $approved ? 'wallet_deposit_approved' : 'wallet_deposit_rejected',
                'amount' => $amount,
            ],
            push: true,
        );
    }

    public function notifyRegistrationSuccess(User $user): void
    {
        $isDriver = $user->role === 'driver';
        $body = $isDriver
            ? 'Đăng ký tài xế thành công. Hồ sơ đang chờ admin duyệt — sau khi duyệt bạn đăng nhập bằng OTP.'
            : 'Đăng ký thành công. Hồ sơ đang chờ duyệt CCCD — sau khi duyệt bạn đăng nhập bằng OTP.';

        $this->notify(
            $user,
            $isDriver ? DriverInboxMessage::CATEGORY_NOTICE : CustomerInboxMessage::CATEGORY_NOTICE,
            'Đăng ký',
            $body,
            ['type' => 'registration_success'],
            push: false,
        );
    }

    /** Admin vừa duyệt — sửa tin “chờ duyệt” cũ + gửi tin đã duyệt (khách + tài xế). */
    public function notifyRegistrationApproved(User $user): void
    {
        $isDriver = $user->role === 'driver';
        $title = 'Duyệt hồ sơ';
        $body = $isDriver
            ? 'Duyệt hồ sơ thành công. Đăng nhập bằng OTP (admin gửi mã) để bắt đầu nhận chuyến.'
            : 'Duyệt hồ sơ CCCD thành công. Đăng nhập bằng OTP (admin gửi mã) để bắt đầu đặt xe.';

        $this->rewriteInboxByTypes(
            $user,
            ['registration_success', 'registration_approved'],
            $title,
            $body,
            'registration_approved',
        );

        // Nếu chưa có tin nào để sửa (mất tin cũ) → tạo mới.
        if (! $this->hasInboxType($user, 'registration_approved') && ! $this->hasInboxType($user, 'registration_success')) {
            $this->notify(
                $user,
                $isDriver ? DriverInboxMessage::CATEGORY_NOTICE : CustomerInboxMessage::CATEGORY_NOTICE,
                $title,
                $body,
                ['type' => 'registration_approved'],
                push: false,
            );
        }
    }

    public function notifyRegisterOtpVerified(User $user): void
    {
        $isDriver = $user->role === 'driver';
        $title = 'Xác minh OTP';
        $body = $isDriver
            ? 'Xác minh OTP thành công. Hồ sơ đã được duyệt — bạn có thể nhận chuyến.'
            : 'Xác minh OTP thành công. Hồ sơ đã được duyệt — bạn có thể đặt xe.';

        // Ghi đè tin đăng ký / đã duyệt cũ để không còn chữ “chờ duyệt”.
        $this->rewriteInboxByTypes(
            $user,
            ['registration_success', 'registration_approved', 'register_otp_verified'],
            $title,
            $body,
            'register_otp_verified',
        );

        if (! $this->hasInboxType($user, 'register_otp_verified')) {
            $this->notify(
                $user,
                $isDriver ? DriverInboxMessage::CATEGORY_NOTICE : CustomerInboxMessage::CATEGORY_NOTICE,
                $title,
                $body,
                ['type' => 'register_otp_verified'],
                push: false,
            );
        }
    }

    /** @param  list<string>  $types */
    private function rewriteInboxByTypes(User $user, array $types, string $title, string $body, string $newType): void
    {
        $payload = [
            'title' => $title,
            'body'  => $body,
            'meta'  => json_encode(['type' => $newType], JSON_UNESCAPED_UNICODE),
        ];

        if ($user->role === 'customer') {
            CustomerInboxMessage::query()
                ->where('user_id', $user->id)
                ->whereIn('meta->type', $types)
                ->update($payload);

            return;
        }

        if ($user->role === 'driver') {
            DriverInboxMessage::query()
                ->where('user_id', $user->id)
                ->whereIn('meta->type', $types)
                ->update($payload);
        }
    }

    private function hasInboxType(User $user, string $type): bool
    {
        if ($user->role === 'customer') {
            return CustomerInboxMessage::query()
                ->where('user_id', $user->id)
                ->where('meta->type', $type)
                ->exists();
        }

        if ($user->role === 'driver') {
            return DriverInboxMessage::query()
                ->where('user_id', $user->id)
                ->where('meta->type', $type)
                ->exists();
        }

        return false;
    }
}
