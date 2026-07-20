<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CancellationReason extends Model
{
    protected $fillable = [
        'label',
        'audience',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active'  => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForAudience($query, string $audience)
    {
        return $query->where(function ($q) use ($audience): void {
            $q->where('audience', $audience)->orWhere('audience', 'both');
        });
    }

    /** «Lý do khác» — cần nhập thêm nội dung. */
    public function requiresNote(): bool
    {
        $normalized = mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $this->label) ?? ''));

        return $normalized === 'lý do khác' || $normalized === 'khác';
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
