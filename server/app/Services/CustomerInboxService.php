<?php

namespace App\Services;

use App\Models\CustomerInboxMessage;
use Illuminate\Support\Collection;

class CustomerInboxService
{
    /** Số tin tối đa mỗi loại (thông báo / thông tin) trên mỗi khách. */
    public const PER_CATEGORY_LIMIT = 10;

    public function unreadCount(int $userId): int
    {
        return CustomerInboxMessage::query()
            ->where('user_id', $userId)
            ->unread()
            ->count();
    }

    /** @return array{info: int, notice: int, total: int} */
    public function unreadCounts(int $userId): array
    {
        $rows = CustomerInboxMessage::query()
            ->where('user_id', $userId)
            ->unread()
            ->selectRaw('category, COUNT(*) as aggregate')
            ->groupBy('category')
            ->pluck('aggregate', 'category');

        $info = (int) ($rows[CustomerInboxMessage::CATEGORY_INFO] ?? 0);
        $notice = (int) ($rows[CustomerInboxMessage::CATEGORY_NOTICE] ?? 0);

        return [
            'info'   => $info,
            'notice' => $notice,
            'total'  => $info + $notice,
        ];
    }

    /** @return Collection<int, CustomerInboxMessage> */
    public function listFor(int $userId, string $category, ?int $limit = null): Collection
    {
        $limit ??= self::PER_CATEGORY_LIMIT;
        $this->pruneCategoryToLimit($userId, $category);

        return CustomerInboxMessage::query()
            ->where('user_id', $userId)
            ->forCategory($category)
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function markCategoryRead(int $userId, string $category): int
    {
        return CustomerInboxMessage::query()
            ->where('user_id', $userId)
            ->forCategory($category)
            ->unread()
            ->update(['read_at' => now()]);
    }

    public function markAllRead(int $userId): int
    {
        return CustomerInboxMessage::query()
            ->where('user_id', $userId)
            ->unread()
            ->update(['read_at' => now()]);
    }

    /** Đánh dấu 1 tin đã đọc (chỉ khi user bấm vào dòng). */
    public function markMessageRead(int $userId, int $messageId): bool
    {
        $message = CustomerInboxMessage::query()
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
    ): CustomerInboxMessage {
        $category = $category === CustomerInboxMessage::CATEGORY_INFO
            ? CustomerInboxMessage::CATEGORY_INFO
            : CustomerInboxMessage::CATEGORY_NOTICE;

        $message = CustomerInboxMessage::query()->create([
            'user_id'    => $userId,
            'category'   => $category,
            'title'      => $title,
            'body'       => $body,
            'meta'       => $meta ?: null,
            'created_by' => $createdBy,
        ]);

        $this->pruneCategoryToLimit($userId, $category);

        return $message;
    }

    public function pruneCategoryToLimit(int $userId, string $category): int
    {
        $category = $category === CustomerInboxMessage::CATEGORY_INFO
            ? CustomerInboxMessage::CATEGORY_INFO
            : CustomerInboxMessage::CATEGORY_NOTICE;

        $count = CustomerInboxMessage::query()
            ->where('user_id', $userId)
            ->forCategory($category)
            ->count();

        if ($count <= self::PER_CATEGORY_LIMIT) {
            return 0;
        }

        $keepIds = CustomerInboxMessage::query()
            ->where('user_id', $userId)
            ->forCategory($category)
            ->latest('id')
            ->limit(self::PER_CATEGORY_LIMIT)
            ->pluck('id');

        if ($keepIds->isEmpty()) {
            return 0;
        }

        return CustomerInboxMessage::query()
            ->where('user_id', $userId)
            ->forCategory($category)
            ->whereNotIn('id', $keepIds)
            ->delete();
    }
}
