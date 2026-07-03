<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table): void {
            if (! Schema::hasColumn('driver_profiles', 'cuoc_offer_count')) {
                $table->unsignedInteger('cuoc_offer_count')->default(0);
            }
            if (! Schema::hasColumn('driver_profiles', 'cuoc_reject_count')) {
                $table->unsignedInteger('cuoc_reject_count')->default(0);
            }
            if (! Schema::hasColumn('driver_profiles', 'cancel_rate_percent')) {
                $table->decimal('cancel_rate_percent', 5, 1)->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table): void {
            $columns = ['cuoc_offer_count', 'cuoc_reject_count', 'cancel_rate_percent'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('driver_profiles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
