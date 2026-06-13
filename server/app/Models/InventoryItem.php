<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    protected $fillable = [
        'operator_id',
        'vehicle_id',
        'name',
        'unit',
        'quantity',
        'unit_price',
        'type',
        'category',
        'note',
        'transaction_date',
    ];

    protected function casts(): array
    {
        return [
            'quantity'         => 'decimal:2',
            'unit_price'       => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    public function operator()
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function getTotalValueAttribute(): float
    {
        return (float) $this->quantity * (float) $this->unit_price;
    }
}
