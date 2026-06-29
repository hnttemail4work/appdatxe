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

    public function outcomeLabel(): string
    {
        return match ($this->outcome) {
            self::OUTCOME_COMPLETED => 'Chạy thành công',
            self::OUTCOME_CANCELLED_CUSTOMER => 'Khách hủy',
            self::OUTCOME_CANCELLED_DRIVER => 'Tài xế hủy',
            default => $this->outcome,
        };
    }

    public function outcomeColor(): string
    {
        return match ($this->outcome) {
            self::OUTCOME_COMPLETED => 'success',
            self::OUTCOME_CANCELLED_CUSTOMER => 'warning',
            self::OUTCOME_CANCELLED_DRIVER => 'danger',
            default => 'neutral',
        };
    }

    public function actorSummary(): string
    {
        if ($this->outcome === self::OUTCOME_COMPLETED) {
            $who = trim(($this->actor_code ? $this->actor_code . ' — ' : '') . ($this->actor_label ?? ''));

            return $who !== '' ? $who : '—';
        }

        if ($this->outcome === self::OUTCOME_CANCELLED_CUSTOMER) {
            $phone = $this->actor_code ?? '';
            $name = $this->actor_label ?? '';

            return trim($name . ($phone !== '' ? ' (' . $phone . ')' : '')) ?: '—';
        }

        if ($this->outcome === self::OUTCOME_CANCELLED_DRIVER) {
            $who = trim(($this->actor_code ? $this->actor_code . ' — ' : '') . ($this->actor_label ?? ''));

            return $who !== '' ? $who : '—';
        }

        return '—';
    }
}
