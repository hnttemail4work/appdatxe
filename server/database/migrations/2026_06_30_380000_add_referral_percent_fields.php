<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_codes', function (Blueprint $table): void {
            if (! Schema::hasColumn('referral_codes', 'commission_percent')) {
                $table->decimal('commission_percent', 5, 2)->nullable()->after('status');
            }
            if (! Schema::hasColumn('referral_codes', 'customer_discount_percent')) {
                $table->decimal('customer_discount_percent', 5, 2)->nullable()->after('commission_percent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('referral_codes', function (Blueprint $table): void {
            if (Schema::hasColumn('referral_codes', 'customer_discount_percent')) {
                $table->dropColumn('customer_discount_percent');
            }
            if (Schema::hasColumn('referral_codes', 'commission_percent')) {
                $table->dropColumn('commission_percent');
            }
        });
    }
};
