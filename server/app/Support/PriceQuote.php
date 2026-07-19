<?php

namespace App\Support;

/** Kết quả quote giá cả xe — dùng chung cho API + snapshot booking. */
class PriceQuote
{
    public function __construct(
        public int $distanceKm,
        public int $priceBase,
        public bool $usedIntraFlat,
        public ?string $vehicleTypeKey,
        public float $vehicleMultiplier,
        public int $priceVehicle,
        public int $surchargeHoliday,
        public int $surchargePeak,
        public int $surchargeRain,
        public int $tollAmount,
        public int $priceSubtotal,
        public float $referralDiscountPercent = 0.0,
        public int $referralDiscountAmount = 0,
        public int $totalPrice = 0,
        /** @var list<array<string, mixed>> */
        public array $surchargeLines = [],
        /** @var array<string, mixed> */
        public array $meta = [],
    ) {
        if ($this->totalPrice <= 0) {
            $this->totalPrice = $this->priceSubtotal;
        }
    }

    public function withReferral(float $percent): self
    {
        $percent = max(0.0, min(100.0, $percent));
        $clone = clone $this;
        $clone->referralDiscountPercent = $percent;

        if ($percent <= 0 || $this->priceSubtotal <= 0) {
            $clone->referralDiscountAmount = 0;
            $clone->totalPrice = PlatformFees::roundDisplayPrice($this->priceSubtotal);

            return $clone;
        }

        $discounted = $this->priceSubtotal * (1 - $percent / 100);
        $clone->totalPrice = PlatformFees::roundDisplayPrice($discounted);
        $clone->referralDiscountAmount = max(0, $this->priceSubtotal - $clone->totalPrice);

        return $clone;
    }

    /** @return array<string, mixed> */
    public function toBreakdownArray(): array
    {
        return [
            'distance_km'               => $this->distanceKm,
            'price_base'                => $this->priceBase,
            'used_intra_flat'           => $this->usedIntraFlat,
            'vehicle_type_key'          => $this->vehicleTypeKey,
            'vehicle_multiplier'        => $this->vehicleMultiplier,
            'price_vehicle'             => $this->priceVehicle,
            'surcharge_holiday'         => $this->surchargeHoliday,
            'surcharge_peak'            => $this->surchargePeak,
            'surcharge_rain'            => $this->surchargeRain,
            'surcharge_lines'           => $this->surchargeLines,
            'toll_amount'               => $this->tollAmount,
            'price_subtotal'            => $this->priceSubtotal,
            'referral_discount_percent' => $this->referralDiscountPercent,
            'referral_discount_amount'  => $this->referralDiscountAmount,
            'total_price'               => $this->totalPrice,
            'meta'                      => $this->meta,
        ];
    }

    /** @return array<string, mixed> columns for Booking::create/update */
    public function toBookingColumns(): array
    {
        return [
            'distance_km'               => $this->distanceKm,
            'price_base'                => $this->priceBase,
            'vehicle_type_key'          => $this->vehicleTypeKey,
            'vehicle_multiplier'        => $this->vehicleMultiplier,
            'surcharge_holiday'         => $this->surchargeHoliday,
            'surcharge_peak'            => $this->surchargePeak,
            'surcharge_rain'            => $this->surchargeRain,
            'toll_amount'               => $this->tollAmount,
            'price_subtotal'            => $this->priceSubtotal,
            'referral_discount_percent' => $this->referralDiscountPercent > 0 ? $this->referralDiscountPercent : null,
            'referral_discount_amount'  => $this->referralDiscountAmount,
            'price_breakdown'           => $this->toBreakdownArray(),
            'total_price'               => $this->totalPrice,
        ];
    }

    /** @return array<string, mixed> API quote shape (compat + breakdown) */
    public function toApiArray(): array
    {
        return array_merge($this->toBreakdownArray(), [
            'whole_car_price'      => $this->priceSubtotal,
            'unit_price'           => $this->priceSubtotal,
            'subtotal'             => $this->priceSubtotal,
            'total_after_discount' => $this->totalPrice,
        ]);
    }
}
