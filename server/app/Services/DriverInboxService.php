<?php

namespace App\Services;

use App\Models\DriverInboxMessage;
use App\Models\DriverProfile;
use App\Models\ReferralCode;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DriverInboxService
{
    /** Số tin tối đa mỗi loại (thông báo / thông tin) trên mỗi tài xế. */
    public const PER_CATEGORY_LIMIT = 10;

    public function __construct(
        private readonly PushNotificationService $push,
    ) {}

    public function unreadCount(int $userId): int
    {
        return DriverInboxMessage::query()
            ->where('user_id', $userId)
            ->unread()
            ->count();
    }

    /** @return array{info: int, notice: int, total: int} */
    public function unreadCounts(int $userId): array
    {
        $rows = DriverInboxMessage::query()
            ->where('user_id', $userId)
            ->unread()
            ->selectRaw('category, COUNT(*) as aggregate')
            ->groupBy('category')
            ->pluck('aggregate', 'category');

        $info = (int) ($rows[DriverInboxMessage::CATEGORY_INFO] ?? 0);
        $notice = (int) ($rows[DriverInboxMessage::CATEGORY_NOTICE] ?? 0);

        return [
            'info'   => $info,
            'notice' => $notice,
            'total'  => $info + $notice,
        ];
    }

    /** @return Collection<int, DriverInboxMessage> */
    public function listFor(int $userId, string $category, ?int $limit = null): Collection
    {
        $limit ??= self::PER_CATEGORY_LIMIT;
        $this->pruneCategoryToLimit($userId, $category);

        return DriverInboxMessage::query()
            ->where('user_id', $userId)
            ->forCategory($category)
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function markCategoryRead(int $userId, string $category): int
    {
        return DriverInboxMessage::query()
            ->where('user_id', $userId)
            ->forCategory($category)
            ->unread()
            ->update(['read_at' => now()]);
    }

    public function markAllRead(int $userId): int
    {
        return DriverInboxMessage::query()
            ->where('user_id', $userId)
            ->unread()
            ->update(['read_at' => now()]);
    }

    /** Đánh dấu 1 tin đã đọc (chỉ khi user bấm vào dòng). */
    public function markMessageRead(int $userId, int $messageId): bool
    {
        $message = DriverInboxMessage::query()
            ->where('user_id', $userId)
            ->whereKey($messageId)
            ->first();

        if (! $message) {
            return false;
        }

        if ($message->read_at === null) {
            $message->forceFill(['read_at' => now()])->save();
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function notify(
        int $userId,
        string $category,
        string $title,
        string $body,
        array $meta = [],
        ?int $createdBy = null,
        bool $push = true,
        ?string $eventKey = null,
        ?string $url = null,
        ?string $dedupKey = null,
    ): DriverInboxMessage {
        $category = $category === DriverInboxMessage::CATEGORY_INFO
            ? DriverInboxMessage::CATEGORY_INFO
            : DriverInboxMessage::CATEGORY_NOTICE;

        $message = DriverInboxMessage::query()->create([
            'user_id'    => $userId,
            'category'   => $category,
            'title'      => $title,
            'body'       => $body,
            'meta'       => $meta ?: null,
            'created_by' => $createdBy,
        ]);

        $this->pruneCategoryToLimit($userId, $category);

        if ($push) {
            $eventKey ??= $category === DriverInboxMessage::CATEGORY_INFO
                ? 'driver.inbox_info'
                : 'driver.inbox_notice';
            $url ??= '/driver/dashboard?tab=inbox';
            $dedupKey ??= 'driver-inbox:' . $message->id;

            $unreadTotal = app(TripChatService::class)->mergeInboxUnread(
                $this->unreadCounts($userId),
                $userId,
            )['total'] ?? $this->unreadCount($userId);

            $this->push->notifyDriverUser(
                $userId,
                $eventKey,
                $title,
                $body,
                $url,
                $dedupKey,
                [
                    'inbox_id'       => $message->id,
                    'inbox_category' => $category,
                    'unread_total'   => (int) $unreadTotal,
                ],
            );
        }

        return $message;
    }

    /** Giữ tối đa PER_CATEGORY_LIMIT tin mới nhất; xóa tin cũ hơn. */
    public function pruneCategoryToLimit(int $userId, string $category): int
    {
        $category = $category === DriverInboxMessage::CATEGORY_INFO
            ? DriverInboxMessage::CATEGORY_INFO
            : DriverInboxMessage::CATEGORY_NOTICE;

        $count = DriverInboxMessage::query()
            ->where('user_id', $userId)
            ->forCategory($category)
            ->count();

        if ($count <= self::PER_CATEGORY_LIMIT) {
            return 0;
        }

        $keepIds = DriverInboxMessage::query()
            ->where('user_id', $userId)
            ->forCategory($category)
            ->latest('id')
            ->limit(self::PER_CATEGORY_LIMIT)
            ->pluck('id');

        if ($keepIds->isEmpty()) {
            return 0;
        }

        return DriverInboxMessage::query()
            ->where('user_id', $userId)
            ->forCategory($category)
            ->whereNotIn('id', $keepIds)
            ->delete();
    }

    public function notifyProfileChangeApproved(DriverProfile $profile): void
    {
        $userId = (int) $profile->user_id;
        if ($userId < 1) {
            return;
        }

        $this->notify(
            $userId,
            DriverInboxMessage::CATEGORY_NOTICE,
            'Cập nhật hồ sơ',
            'Cập nhật hồ sơ thành công. Yêu cầu của bạn đã được duyệt và áp dụng.',
            ['type' => 'profile_change_approved', 'driver_profile_id' => $profile->id],
            dedupKey: 'driver-inbox:profile-approved:' . $profile->id . ':' . now()->format('YmdHi'),
        );
    }

    public function notifyProfileChangeRejected(DriverProfile $profile, string $fieldsLabel = ''): void
    {
        $userId = (int) $profile->user_id;
        if ($userId < 1) {
            return;
        }

        $fields = trim($fieldsLabel);
        $body = $fields !== ''
            ? 'Yêu cầu thay đổi thông tin ' . $fields . ' không được duyệt. Vui lòng liên hệ tổng đài để biết thêm thông tin.'
            : 'Yêu cầu thay đổi thông tin không được duyệt. Vui lòng liên hệ tổng đài để biết thêm thông tin.';

        $this->notify(
            $userId,
            DriverInboxMessage::CATEGORY_NOTICE,
            'Cập nhật hồ sơ',
            $body,
            ['type' => 'profile_change_rejected', 'driver_profile_id' => $profile->id],
            dedupKey: 'driver-inbox:profile-rejected:' . $profile->id . ':' . now()->format('YmdHi'),
        );
    }

    public function notifyPromoGranted(DriverProfile $profile, float $discountPercent): void
    {
        $userId = (int) $profile->user_id;
        if ($userId < 1) {
            return;
        }

        $label = number_format($discountPercent, 1);
        $this->notify(
            $userId,
            DriverInboxMessage::CATEGORY_NOTICE,
            'Cấp QR giảm giá',
            'Cấp QR giảm giá thành công: khách giảm ' . $label . '%. Mã QR mới đã hiện trong mục Mời bạn bè.',
            [
                'type' => 'promo_granted',
                'customer_discount_percent' => $discountPercent,
            ],
            url: '/driver/dashboard?tab=invite',
            dedupKey: 'driver-inbox:promo-grant:' . $profile->id . ':' . now()->format('YmdHi'),
        );
    }

    public function notifyPromoUpdated(DriverProfile $profile, float $discountPercent): void
    {
        $userId = (int) $profile->user_id;
        if ($userId < 1) {
            return;
        }

        $label = number_format($discountPercent, 1);
        $this->notify(
            $userId,
            DriverInboxMessage::CATEGORY_NOTICE,
            'Cập nhật khuyến mãi giới thiệu',
            'Khuyến mãi QR của bạn đã đổi: khách giảm ' . $label . '%.',
            [
                'type' => 'promo_updated',
                'customer_discount_percent' => $discountPercent,
            ],
            url: '/driver/dashboard?tab=invite',
            dedupKey: 'driver-inbox:promo:' . $profile->id . ':' . now()->format('YmdHi'),
        );
    }

    public function notifyPromoRemoved(DriverProfile $profile, float $previousPercent): void
    {
        $userId = (int) $profile->user_id;
        if ($userId < 1) {
            return;
        }

        $label = number_format($previousPercent, 1);
        $this->notify(
            $userId,
            DriverInboxMessage::CATEGORY_NOTICE,
            'Thu hồi QR giảm giá khách',
            'QR giảm ' . $label . '% không còn áp dụng — đã ẩn khỏi Mời bạn bè.',
            [
                'type' => 'promo_removed',
                'customer_discount_percent' => $previousPercent,
            ],
            url: '/driver/dashboard?tab=invite',
            dedupKey: 'driver-inbox:promo-remove:' . $profile->id . ':' . now()->format('YmdHi'),
        );
    }

    public function notifyCommissionCodeAssigned(DriverProfile $profile, ReferralCode $referral): void
    {
        $userId = (int) $profile->user_id;
        if ($userId < 1) {
            return;
        }

        $percent = number_format($referral->commissionPercent(), 1);
        $this->notify(
            $userId,
            DriverInboxMessage::CATEGORY_NOTICE,
            'Nhận mã hoa hồng',
            'Nhận mã hoa hồng thành công: mã ' . $referral->code . ' (hoa hồng ' . $percent
                . '%). Mã QR mới đã hiện trong mục Mời bạn bè.',
            [
                'type' => 'commission_code_assigned',
                'referral_code_id' => $referral->id,
                'code' => $referral->code,
            ],
            url: '/driver/dashboard?tab=invite',
            dedupKey: 'driver-inbox:hh-assign:' . $referral->id . ':' . $profile->id . ':' . now()->format('YmdHi'),
        );
    }

    public function notifyCommissionCodeRevoked(DriverProfile $profile, ReferralCode $referral): void
    {
        $userId = (int) $profile->user_id;
        if ($userId < 1) {
            return;
        }

        $this->notify(
            $userId,
            DriverInboxMessage::CATEGORY_NOTICE,
            'Thu hồi mã hoa hồng giới thiệu',
            'Mã ' . $referral->code . ' đã được thu hồi khỏi tài khoản của bạn.',
            [
                'type' => 'commission_code_revoked',
                'referral_code_id' => $referral->id,
                'code' => $referral->code,
            ],
            url: '/driver/dashboard?tab=invite',
            dedupKey: 'driver-inbox:hh-revoke:' . $referral->id . ':' . $profile->id . ':' . now()->format('YmdHi'),
        );
    }

    /**
     * Lưu thông báo hệ thống (cuốc…) — không gửi push lần 2 (push đã gửi riêng).
     *
     * @param  array<string, mixed>  $meta
     */
    public function storeNoticeWithoutPush(int $userId, string $title, string $body, array $meta = []): DriverInboxMessage
    {
        return $this->notify(
            $userId,
            DriverInboxMessage::CATEGORY_NOTICE,
            $title,
            $body,
            $meta,
            push: false,
        );
    }

    /**
     * @param  list<int>|null  $userIds  null = tất cả tài xế đã duyệt
     * @return int số tin đã tạo
     */
    public function broadcast(
        string $category,
        string $title,
        string $body,
        ?array $userIds,
        int $createdBy,
    ): int {
        $category = $category === DriverInboxMessage::CATEGORY_INFO
            ? DriverInboxMessage::CATEGORY_INFO
            : DriverInboxMessage::CATEGORY_NOTICE;

        $query = User::query()
            ->where('role', 'driver')
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', 'active');
            });
        if ($userIds !== null) {
            $query->whereIn('id', $userIds);
        } else {
            $query->whereHas('driverProfile', fn ($q) => $q->where('approval_status', 'approved'));
        }

        $ids = $query->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($ids === []) {
            return 0;
        }

        $count = 0;
        DB::transaction(function () use ($ids, $category, $title, $body, $createdBy, &$count): void {
            foreach ($ids as $userId) {
                $this->notify(
                    $userId,
                    $category,
                    $title,
                    $body,
                    ['type' => 'admin_broadcast'],
                    $createdBy,
                    true,
                );
                $count++;
            }
        });

        return $count;
    }
}
