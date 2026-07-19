<?php

use App\Models\PlatformSetting;
use App\Support\DriverVehicleOptions;
use App\Support\VehicleCapacityPricing;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Engine tính giá: loại xe DB, rule phụ phí, thu phí tuyến, snapshot trên booking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_types', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('label', 120);
            $table->unsignedTinyInteger('seats')->nullable();
            $table->string('family', 32)->default('other');
            $table->decimal('price_percent', 6, 2)->default(100);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('pricing_surcharge_rules', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20); // holiday|peak|rain
            $table->string('name', 120);
            $table->string('mode', 16)->default('percent'); // percent|fixed
            $table->decimal('value', 12, 2)->default(0);
            $table->json('payload')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['type', 'is_active']);
        });

        Schema::create('pricing_tolls', function (Blueprint $table) {
            $table->id();
            $table->string('from_province', 120);
            $table->string('to_province', 120);
            $table->unsignedInteger('amount_vnd')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['from_province', 'to_province']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedInteger('distance_km')->nullable()->after('total_price');
            $table->unsignedInteger('price_base')->nullable()->after('distance_km');
            $table->string('vehicle_type_key', 64)->nullable()->after('price_base');
            $table->decimal('vehicle_multiplier', 8, 4)->nullable()->after('vehicle_type_key');
            $table->unsignedInteger('surcharge_holiday')->default(0)->after('vehicle_multiplier');
            $table->unsignedInteger('surcharge_peak')->default(0)->after('surcharge_holiday');
            $table->unsignedInteger('surcharge_rain')->default(0)->after('surcharge_peak');
            $table->unsignedInteger('toll_amount')->default(0)->after('surcharge_rain');
            $table->unsignedInteger('price_subtotal')->nullable()->after('toll_amount');
            $table->decimal('referral_discount_percent', 6, 2)->nullable()->after('price_subtotal');
            $table->unsignedInteger('referral_discount_amount')->default(0)->after('referral_discount_percent');
            $table->json('price_breakdown')->nullable()->after('referral_discount_amount');
        });

        $this->seedPricingDefaults();
        $this->seedVehicleTypes();
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'distance_km',
                'price_base',
                'vehicle_type_key',
                'vehicle_multiplier',
                'surcharge_holiday',
                'surcharge_peak',
                'surcharge_rain',
                'toll_amount',
                'price_subtotal',
                'referral_discount_percent',
                'referral_discount_amount',
                'price_breakdown',
            ]);
        });

        Schema::dropIfExists('pricing_tolls');
        Schema::dropIfExists('pricing_surcharge_rules');
        Schema::dropIfExists('vehicle_types');

        foreach ([
            'pricing_km_rate_under_100',
            'pricing_km_rate_over_100',
            'pricing_intra_flat_max_km',
            'pricing_intra_flat_price',
            'pricing_rounding_unit',
            'app_commission_percentage',
            'referral_commission_first_percentage',
            'referral_commission_repeat_percentage',
            'driver_invite_qr_discount_percentage',
            'rain_surcharge_enabled',
        ] as $key) {
            PlatformSetting::query()->where('setting_key', $key)->delete();
        }
    }

    private function seedPricingDefaults(): void
    {
        PlatformSetting::setValue('pricing_km_rate_under_100', ['value' => 13000], 'finance');
        PlatformSetting::setValue('pricing_km_rate_over_100', ['value' => 10000], 'finance');
        PlatformSetting::setValue('pricing_intra_flat_max_km', ['value' => 3], 'finance');
        PlatformSetting::setValue('pricing_intra_flat_price', ['value' => 30000], 'finance');
        PlatformSetting::setValue('pricing_rounding_unit', ['value' => 10000], 'finance');
        PlatformSetting::setValue('app_commission_percentage', ['value' => 2], 'finance');
        PlatformSetting::setValue('commission_percentage', ['value' => 2], 'finance');
        PlatformSetting::setValue('referral_commission_first_percentage', ['value' => 8], 'finance');
        PlatformSetting::setValue('referral_commission_repeat_percentage', ['value' => 2], 'finance');
        PlatformSetting::setValue('driver_invite_qr_discount_percentage', ['value' => 2], 'finance');
        PlatformSetting::setValue('rain_surcharge_enabled', ['value' => false], 'finance');
    }

    private function seedVehicleTypes(): void
    {
        $sort = 0;
        foreach (DriverVehicleOptions::OPTIONS as $key => $meta) {
            $seats = $meta['seats'];
            $percent = 100.0;
            if ($seats !== null) {
                $percent = VehicleCapacityPricing::defaultPercentForCapacity((int) $seats);
            }

            \App\Models\VehicleType::query()->create([
                'key'           => $key,
                'label'         => $meta['label'],
                'seats'         => $seats,
                'family'        => $meta['family'],
                'price_percent' => $percent,
                'sort_order'    => $sort++,
                'is_active'     => true,
            ]);
        }
    }
};
