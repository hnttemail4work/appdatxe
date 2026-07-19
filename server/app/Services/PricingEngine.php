<?php

namespace App\Services;

use App\Models\PricingSurchargeRule;
use App\Models\PricingToll;
use App\Support\PlatformFees;
use App\Support\PriceQuote;
use App\Support\PricingConfig;
use App\Support\VehicleTypePricing;
use Carbon\CarbonInterface;

/** Pipeline tính giá cả xe: km → loại xe → phụ phí → thu phí. */
class PricingEngine
{
    public function quoteForRoute(
        string $pickup,
        string $dropoff,
        int $distanceKm,
        ?string $vehicleType = null,
        ?int $capacity = null,
        ?CarbonInterface $at = null,
    ): PriceQuote {
        $distanceKm = max(0, $distanceKm);
        $at = $at ?? now();
        $typeKey = VehicleTypePricing::normalizeTypeKey($vehicleType);
        $multiplier = VehicleTypePricing::multiplierFor($typeKey, $capacity);

        $usedFlat = $this->isIntraProvinceRoute($pickup, $dropoff)
            && $distanceKm > 0
            && $distanceKm <= PricingConfig::intraFlatMaxKm();

        if ($usedFlat) {
            $priceBase = PricingConfig::intraFlatPrice();
            $priceVehicle = $priceBase;
            $multiplier = 1.0;
        } else {
            $priceBase = PlatformFees::wholeCarBaseFromDistanceKm($distanceKm);
            $priceVehicle = PlatformFees::roundDisplayPrice($priceBase * $multiplier);
        }

        $surcharges = $this->computeSurcharges($priceVehicle, $at);
        $toll = PricingToll::amountFor($pickup, $dropoff);

        $subtotal = $priceVehicle
            + $surcharges['holiday']
            + $surcharges['peak']
            + $surcharges['rain']
            + $toll;

        return new PriceQuote(
            distanceKm: $distanceKm,
            priceBase: $priceBase,
            usedIntraFlat: $usedFlat,
            vehicleTypeKey: $typeKey,
            vehicleMultiplier: $multiplier,
            priceVehicle: $priceVehicle,
            surchargeHoliday: $surcharges['holiday'],
            surchargePeak: $surcharges['peak'],
            surchargeRain: $surcharges['rain'],
            tollAmount: $toll,
            priceSubtotal: $subtotal,
            totalPrice: PlatformFees::roundDisplayPrice($subtotal),
            surchargeLines: $surcharges['lines'],
            meta: [
                'km_rate_under_100' => PricingConfig::kmRateUnder100(),
                'km_rate_over_100'  => PricingConfig::kmRateOver100(),
                'rounding_unit'     => PricingConfig::roundingUnit(),
                'quoted_at'         => $at->toIso8601String(),
            ],
        );
    }

    /**
     * @return array{holiday: int, peak: int, rain: int, lines: list<array<string, mixed>>}
     */
    private function computeSurcharges(int $coreAmount, CarbonInterface $at): array
    {
        $holiday = 0;
        $peak = 0;
        $rain = 0;
        $lines = [];

        $rules = PricingSurchargeRule::activeCached();

        foreach ($rules->where('type', PricingSurchargeRule::TYPE_HOLIDAY) as $rule) {
            if (! $this->matchesHoliday($rule, $at)) {
                continue;
            }
            $amount = $this->ruleAmount($rule, $coreAmount);
            if ($amount <= 0) {
                continue;
            }
            $holiday += $amount;
            $lines[] = $this->line($rule, $amount);
        }

        foreach ($rules->where('type', PricingSurchargeRule::TYPE_PEAK) as $rule) {
            if (! $this->matchesPeak($rule, $at)) {
                continue;
            }
            $amount = $this->ruleAmount($rule, $coreAmount);
            if ($amount <= 0) {
                continue;
            }
            $peak += $amount;
            $lines[] = $this->line($rule, $amount);
        }

        if (PricingConfig::rainSurchargeEnabled()) {
            foreach ($rules->where('type', PricingSurchargeRule::TYPE_RAIN) as $rule) {
                if (! $this->matchesRain($rule, $at)) {
                    continue;
                }
                $amount = $this->ruleAmount($rule, $coreAmount);
                if ($amount <= 0) {
                    continue;
                }
                $rain += $amount;
                $lines[] = $this->line($rule, $amount);
            }
        }

        return [
            'holiday' => $holiday,
            'peak'    => $peak,
            'rain'    => $rain,
            'lines'   => $lines,
        ];
    }

    private function ruleAmount(PricingSurchargeRule $rule, int $coreAmount): int
    {
        if ($rule->isPercent()) {
            return (int) round($coreAmount * ((float) $rule->value) / 100);
        }

        return max(0, (int) round((float) $rule->value));
    }

    /** @return array<string, mixed> */
    private function line(PricingSurchargeRule $rule, int $amount): array
    {
        return [
            'rule_id' => $rule->id,
            'type'    => $rule->type,
            'name'    => $rule->name,
            'mode'    => $rule->mode,
            'value'   => (float) $rule->value,
            'amount'  => $amount,
        ];
    }

    private function matchesHoliday(PricingSurchargeRule $rule, CarbonInterface $at): bool
    {
        $payload = $rule->payload ?? [];
        $start = isset($payload['starts_on']) ? (string) $payload['starts_on'] : '';
        $end = isset($payload['ends_on']) ? (string) $payload['ends_on'] : $start;

        if ($start === '') {
            return false;
        }

        $day = $at->timezone(config('app.timezone', 'Asia/Ho_Chi_Minh'))->toDateString();

        return $day >= $start && $day <= ($end !== '' ? $end : $start);
    }

    private function matchesPeak(PricingSurchargeRule $rule, CarbonInterface $at): bool
    {
        $payload = $rule->payload ?? [];
        $local = $at->timezone(config('app.timezone', 'Asia/Ho_Chi_Minh'));
        $dow = (int) $local->dayOfWeek; // 0=Sun … 6=Sat
        $dows = $payload['days_of_week'] ?? null;

        if (is_array($dows) && $dows !== [] && ! in_array($dow, array_map('intval', $dows), true)) {
            return false;
        }

        $start = (string) ($payload['start_time'] ?? '00:00');
        $end = (string) ($payload['end_time'] ?? '23:59');
        $time = $local->format('H:i');

        if ($start <= $end) {
            return $time >= $start && $time <= $end;
        }

        // Qua đêm: 22:00–06:00
        return $time >= $start || $time <= $end;
    }

    private function matchesRain(PricingSurchargeRule $rule, CarbonInterface $at): bool
    {
        $payload = $rule->payload ?? [];
        if (empty($payload['start_time']) && empty($payload['end_time'])) {
            return true;
        }

        return $this->matchesPeak($rule, $at);
    }

    private function isIntraProvinceRoute(?string $pickup, ?string $dropoff): bool
    {
        $pickup = trim((string) $pickup);
        $dropoff = trim((string) $dropoff);

        return $pickup !== '' && $pickup === $dropoff;
    }
}
