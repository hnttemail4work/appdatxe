<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected static function booted(): void
    {
        static::updated(function (Booking $booking): void {
            if (! $booking->wasChanged(['booking_status', 'trip_status'])) {
                return;
            }

            $cancelled = in_array($booking->booking_status, ['cancelled', 'rejected'], true)
                || $booking->trip_status === 'cancelled';

            if ($cancelled) {
                app(\App\Services\ReferralCodeService::class)->purgeForBooking($booking);
            }
        });
    }

    protected $fillable = [
        'contact_phone',
        'passenger_name',
        'passenger_gender',
        'passenger_age',
        'schedule_id',
        'seat_numbers',
        'trip_type',
        'booking_mode',
        'booking_reference',
        'applied_referral_code_id',
        'total_price',
        'payment_status',
        'trip_status',
        'booking_status',
        'pickup_address',
        'pickup_detail',
        'pickup_lat',
        'pickup_lng',
        'pickup_time',
        'dropoff_address',
        'dropoff_detail',
        'notes',
        'operator_confirmed_at',
        'hold_expires_at',
        'confirmed_at',
        'completed_at',
        'cancelled_at',
        'cancelled_by',
        'expired_at',
    ];

    protected function casts(): array
    {
        return [
            'seat_numbers'   => 'array',
            'passenger_age'  => 'integer',
            'pickup_lat'     => 'float',
            'pickup_lng'     => 'float',
            'total_price'    => 'decimal:2',
            'hold_expires_at' => 'datetime',
            'confirmed_at'   => 'datetime',
            'completed_at'   => 'datetime',
            'cancelled_at'   => 'datetime',
            'expired_at'     => 'datetime',
            'operator_confirmed_at' => 'datetime',
        ];
    }

    public function tripTypeLabel(): string
    {
        return app(\App\Services\TripPricingService::class)->tripTypeLabel($this->trip_type ?? 'one_way');
    }

    public function contactPhone(): ?string
    {
        return $this->contact_phone;
    }

    public function matchesContactPhone(string $phone): bool
    {
        $stored = preg_replace('/\D+/', '', (string) $this->contact_phone);
        $given = preg_replace('/\D+/', '', $phone);

        return $stored !== '' && $stored === $given;
    }

    public function passengerGenderLabel(): string
    {
        return ($this->passenger_gender ?? 'male') === 'female' ? 'Nữ' : 'Nam';
    }

    public function passengerAgeLabel(): ?string
    {
        if ($this->passenger_age === null) {
            return null;
        }

        return $this->passenger_age . ' tuổi';
    }

    public function passengerProfileDetail(): string
    {
        $parts = [$this->passengerGenderLabel()];
        if ($age = $this->passengerAgeLabel()) {
            $parts[] = $age;
        }

        return implode(', ', $parts);
    }

    public function cancelledByLabel(): ?string
    {
        if (! in_array($this->booking_status, ['cancelled', 'rejected'], true)
            && $this->trip_status !== 'cancelled') {
            return null;
        }

        return match ($this->cancelled_by) {
            'customer' => 'Khách hủy',
            'driver'   => 'Tài xế hủy',
            default    => 'Đã hủy',
        };
    }

    public function pickupLabel(): string
    {
        $city = trim((string) $this->pickup_address);
        $detail = trim((string) $this->pickup_detail);

        if ($city !== '' && $detail !== '') {
            return $city . ', ' . $detail;
        }

        return $detail !== '' ? $detail : ($city !== '' ? $city : '—');
    }

    /** Chỉ chi tiết đón — không ghép tỉnh/thành phố. */
    public function driverPickupDetailLabel(): string
    {
        $detail = trim((string) $this->pickup_detail);
        if ($detail !== '') {
            return $detail;
        }

        $city = trim((string) $this->pickup_address);

        return $city !== '' ? $city : 'liên hệ khách';
    }

    public function pickupTimeLabel(): ?string
    {
        if (! $this->pickup_time) {
            return null;
        }

        return \App\Support\DepartureTimeDisplay::label($this->pickup_time);
    }

    /** Chỉ chi tiết trả — nếu trống thì báo liên hệ khách. */
    public function driverDropoffDetailLabel(): string
    {
        $detail = trim((string) $this->dropoff_detail);
        if ($detail !== '') {
            return $detail;
        }

        return 'liên hệ khách';
    }

    public function dropoffLabel(): string
    {
        $city = trim((string) $this->dropoff_address);
        $detail = trim((string) $this->dropoff_detail);

        if ($city !== '' && $detail !== '') {
            return $city . ', ' . $detail;
        }

        return $detail !== '' ? $detail : ($city !== '' ? $city : '—');
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    public function tripReview()
    {
        return $this->hasOne(TripReview::class);
    }

    public function paymentTransactions()
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function isConfirmedForDriver(): bool
    {
        return $this->booking_status === 'confirmed'
            && $this->payment_status === 'paid'
            && $this->trip_status === 'confirmed';
    }

    public function isExpired(): bool
    {
        return $this->expired_at !== null;
    }

    public function seatReservations()
    {
        return $this->hasMany(SeatReservation::class);
    }

    public function referralCode()
    {
        return $this->hasOne(ReferralCode::class)->where('type', ReferralCode::TYPE_BOOKING_TEMP);
    }

    public function appliedReferralCode()
    {
        return $this->belongsTo(ReferralCode::class, 'applied_referral_code_id');
    }

    public function referralCommissionAmount(): int
    {
        $this->loadMissing('appliedReferralCode');
        if (! $this->appliedReferralCode || $this->trip_status !== 'completed') {
            return 0;
        }

        return (int) round((float) $this->total_price * $this->appliedReferralCode->commissionPercent() / 100, 0);
    }

    public function audits()
    {
        return $this->hasMany(BookingAudit::class);
    }

    public function hasDriverAccepted(): bool
    {
        $this->loadMissing('schedule');

        return $this->schedule && (bool) $this->schedule->driver_id;
    }

    public function needsOperatorConfirmation(): bool
    {
        return $this->operator_confirmed_at === null
            && ! in_array($this->booking_status, ['cancelled', 'rejected'], true);
    }

    public function bookingModeLabel(): string
    {
        return match ($this->booking_mode) {
            'whole_car' => 'Đặt cả xe',
            default     => 'Ghép xe',
        };
    }

    public function seatCount(): int
    {
        return count($this->seat_numbers ?? []);
    }

    /** Tổng tiền đơn theo loại đặt (cả xe / ghép × số ghế × loại chuyến). */
    public function chargedTotal(): float
    {
        $stored = (float) $this->total_price;
        $this->loadMissing('schedule');

        if (! $this->schedule) {
            return $stored;
        }

        return app(\App\Services\TripPricingService::class)->bookingTotal(
            $this->schedule,
            $this->trip_type ?? 'one_way',
            $this->booking_mode ?? 'shared',
            max($this->seatCount(), 1),
            $this->pickup_address,
            $this->dropoff_address,
        );
    }

    public function seatCountLabel(): string
    {
        if (($this->booking_mode ?? 'shared') === 'whole_car') {
            return 'Cả xe';
        }

        $count = $this->seatCount();

        return $count > 0 ? $count . ' ghế' : '';
    }

    /** Nhãn trạng thái thống nhất — luồng mới: khách → tài xế nhận → thu tiền trực tiếp. */
    public function primaryStatusLabel(): string
    {
        if ($this->isExpired()) {
            return 'Hết hạn';
        }

        if ($this->booking_status === 'cancelled') {
            return 'Đã hủy';
        }

        if ($this->booking_status === 'rejected') {
            return 'Từ chối';
        }

        if ($this->trip_status === 'completed') {
            return 'Hoàn tất';
        }

        if (! $this->hasDriverAccepted()) {
            return $this->needsOperatorConfirmation() ? 'Chờ QL xác nhận' : 'Chờ tài xế nhận';
        }

        $this->loadMissing('schedule');
        if ($this->schedule
            && ($this->schedule->status === 'running' || $this->schedule->departure_time <= now())) {
            return 'Đang phục vụ';
        }

        return 'Sắp chạy';
    }

    /** Nhãn theo dõi trên dashboard quản lý — có thêm bước kết chuyến / phí nền tảng. */
    public function operatorMonitorLabel(): string
    {
        if ($this->isExpired()) {
            return 'Hết hạn';
        }

        if ($this->booking_status === 'cancelled') {
            return 'Đã hủy';
        }

        if ($this->booking_status === 'rejected') {
            return 'Từ chối';
        }

        if ($this->trip_status === 'completed') {
            $this->loadMissing('schedule.tripSettlement');
            $settlement = $this->schedule?->tripSettlement;
            if ($settlement && $settlement->status !== 'completed') {
                return 'Chờ kết chuyến';
            }

            return 'Hoàn tất';
        }

        if (! $this->hasDriverAccepted()) {
            return $this->needsOperatorConfirmation() ? 'Chờ QL xác nhận' : 'Chờ tài xế nhận';
        }

        $this->loadMissing('schedule');
        if ($this->schedule
            && ($this->schedule->status === 'running' || $this->schedule->departure_time <= now())) {
            return 'Đang phục vụ';
        }

        return 'Sắp chạy';
    }

    public function primaryStatusColor(): string
    {
        return $this->statusColorForLabel($this->primaryStatusLabel());
    }

    public function operatorMonitorColor(): string
    {
        return $this->statusColorForLabel($this->operatorMonitorLabel());
    }

    private function statusColorForLabel(string $label): string
    {
        if ($label === 'Hết hạn') {
            return \App\Support\StatusBadge::NEUTRAL;
        }

        return match ($label) {
            'Đã hủy', 'Từ chối'     => \App\Support\StatusBadge::DANGER,
            'Hoàn tất'              => \App\Support\StatusBadge::SUCCESS,
            'Đang phục vụ'          => \App\Support\StatusBadge::GOLD,
            'Chờ kết chuyến'        => \App\Support\StatusBadge::PENDING,
            'Chờ QL xác nhận', 'Chờ tài xế nhận' => \App\Support\StatusBadge::PENDING,
            default                 => \App\Support\StatusBadge::NEUTRAL,
        };
    }

    public function tripDisplayLabel(): ?string
    {
        if ($this->isExpired()) {
            return null;
        }

        return match ($this->trip_status) {
            'completed'           => 'Hoàn tất',
            'awaiting_completion' => 'Chờ xác nhận hoàn',
            'cancelled'           => 'Đã hủy',
            'confirmed'           => $this->scheduleTripPhaseLabel(),
            default               => null,
        };
    }

    public function tripDisplayColor(): string
    {
        if ($this->isExpired()) {
            return \App\Support\StatusBadge::NEUTRAL;
        }

        $label = $this->tripDisplayLabel();

        return match ($label) {
            'Hoàn tất', 'Chạy xong' => \App\Support\StatusBadge::SUCCESS,
            'Chờ xác nhận hoàn'     => \App\Support\StatusBadge::INFO,
            'Đã hủy'                => \App\Support\StatusBadge::DANGER,
            'Đang chạy'             => \App\Support\StatusBadge::GOLD,
            'Sắp chạy'             => \App\Support\StatusBadge::PENDING,
            default                 => \App\Support\StatusBadge::NEUTRAL,
        };
    }

    private function scheduleTripPhaseLabel(): ?string
    {
        $this->loadMissing('schedule');

        if (! $this->schedule) {
            return 'Sắp chạy';
        }

        return match ($this->schedule->displayStatus()) {
            'completed' => 'Chạy xong',
            'running'   => 'Đang chạy',
            default     => 'Sắp chạy',
        };
    }
}
