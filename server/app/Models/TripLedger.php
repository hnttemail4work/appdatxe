<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripLedger extends Model
{
    public const OUTCOME_COMPLETED = 'completed';

    public const OUTCOME_CANCELLED_CUSTOMER = 'cancelled_customer';

    public const OUTCOME_CANCELLED_DRIVER = 'cancelled_driver';

    public $timestamps = false;

    protected $table = 'trip_ledger';

    protected $fillable = [
        'trip_code',
        'outcome',
        'route_label',
        'actor_label',
        'actor_code',
        'amount',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }

}
