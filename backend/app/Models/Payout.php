<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payout extends Model
{
    protected $fillable = [
        'operator_id',
        'period_start',
        'period_end',
        'gross_amount',
        'commission_rate',
        'commission_amount',
        'net_amount',
        'status',
        'generated_at',
        'paid_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'gross_amount' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'generated_at' => 'datetime',
            'paid_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function operator()
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
