<?php

namespace App\Services;

use App\Http\Resources\CancellationReasonResource;
use App\Models\CancellationReason;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class CancellationReasonService
{
    /** @return Collection<int, CancellationReason> */
    public function forAudience(string $audience): Collection
    {
        return CancellationReason::query()
            ->active()
            ->forAudience($audience)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();
    }

    /** @return list<array{id: int, label: string, requires_note: bool}> */
    public function serializeForAudience(string $audience): array
    {
        return CancellationReasonResource::collection($this->forAudience($audience))
            ->toArray(request());
    }

    public function resolveForCancel(int $reasonId, string $audience): CancellationReason
    {
        $reason = CancellationReason::query()
            ->active()
            ->forAudience($audience)
            ->find($reasonId);

        if (! $reason) {
            throw new InvalidArgumentException('Vui lòng chọn lý do hủy hợp lệ.');
        }

        return $reason;
    }

    /** Nhãn lưu trên booking — «Lý do khác» kèm nội dung người dùng nhập. */
    public function composeLabel(CancellationReason $reason, ?string $note = null): string
    {
        $label = (string) $reason->label;
        if (! $reason->requiresNote()) {
            return mb_substr($label, 0, 200);
        }

        $note = trim((string) $note);
        if ($note === '') {
            throw new InvalidArgumentException('Vui lòng nhập lý do hủy.');
        }

        $note = mb_substr($note, 0, 160);

        return mb_substr($label.': '.$note, 0, 200);
    }

    /** @param array{label: string, audience: string, sort_order?: int} $data */
    public function create(array $data): CancellationReason
    {
        return CancellationReason::query()->create([
            'label'      => trim($data['label']),
            'audience'   => $this->normalizeAudience($data['audience']),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active'  => true,
        ]);
    }

    public function delete(CancellationReason $reason): void
    {
        if ($reason->bookings()->exists()) {
            $reason->update(['is_active' => false]);

            return;
        }

        $reason->delete();
    }

    private function normalizeAudience(string $audience): string
    {
        return in_array($audience, ['customer', 'driver', 'both'], true) ? $audience : 'both';
    }
}
