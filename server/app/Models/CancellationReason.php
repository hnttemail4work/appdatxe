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

    public function audienceLabel(): string
    {
        return match ($this->audience) {
            'customer' => 'Khách hàng',
            'driver'   => 'Tài xế',
            default    => 'Cả hai',
        };
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

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
