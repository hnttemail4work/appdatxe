<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_trip_settlements', function (Blueprint $table): void {
            $table->string('settlement_code', 20)->nullable()->after('transfer_ref');
            $table->timestamp('settlement_code_expires_at')->nullable()->after('settlement_code');
            $table->timestamp('operator_code_issued_at')->nullable()->after('settlement_code_expires_at');
            $table->foreignId('operator_code_issued_by')->nullable()->after('operator_code_issued_at')
                ->constrained('users')->nullOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE driver_trip_settlements MODIFY COLUMN status ENUM(
                'pending_settle',
                'pending_driver_code',
                'pending_admin_transfer',
                'pending_operator_fee',
                'pending_deposit',
                'completed'
            ) NOT NULL DEFAULT 'pending_settle'");
        }

        DB::table('driver_trip_settlements')
            ->where('status', 'pending_operator_fee')
            ->update(['status' => 'pending_settle']);
    }

    public function down(): void
    {
        Schema::table('driver_trip_settlements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('operator_code_issued_by');
            $table->dropColumn([
                'settlement_code',
                'settlement_code_expires_at',
                'operator_code_issued_at',
            ]);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE driver_trip_settlements MODIFY COLUMN status ENUM(
                'pending_settle',
                'pending_admin_transfer',
                'pending_operator_fee',
                'pending_deposit',
                'completed'
            ) NOT NULL DEFAULT 'pending_settle'");
        }
    }
};
