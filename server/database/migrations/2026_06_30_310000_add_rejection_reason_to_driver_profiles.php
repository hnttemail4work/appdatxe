<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table): void {
            if (! Schema::hasColumn('driver_profiles', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('approval_status');
            }
            if (! Schema::hasColumn('driver_profiles', 'rejection_reason_at')) {
                $table->timestamp('rejection_reason_at')->nullable()->after('rejection_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table): void {
            if (Schema::hasColumn('driver_profiles', 'rejection_reason_at')) {
                $table->dropColumn('rejection_reason_at');
            }
            if (Schema::hasColumn('driver_profiles', 'rejection_reason')) {
                $table->dropColumn('rejection_reason');
            }
        });
    }
};
