<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_wallets', function (Blueprint $table): void {
            if (! Schema::hasColumn('driver_wallets', 'wallet_activated_at')) {
                $table->timestamp('wallet_activated_at')->nullable()->after('wallet_gate_enabled');
            }
            if (! Schema::hasColumn('driver_wallets', 'total_approved_deposits')) {
                $table->unsignedBigInteger('total_approved_deposits')->default(0)->after('wallet_activated_at');
            }
        });

        Schema::table('bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('bookings', 'operator_dismissed_at')) {
                $table->timestamp('operator_dismissed_at')->nullable()->after('needs_operator_help_at');
            }
            if (! Schema::hasColumn('bookings', 'repeat_cancel_flag')) {
                $table->boolean('repeat_cancel_flag')->default(false)->after('operator_dismissed_at');
            }
        });

        if (Schema::hasTable('driver_wallet_transactions')) {
            foreach (DB::table('driver_wallets')->orderBy('id')->get() as $wallet) {
                $total = (int) DB::table('driver_wallet_transactions')
                    ->where('driver_wallet_id', $wallet->id)
                    ->where('type', 'deposit')
                    ->where('status', 'approved')
                    ->sum('amount');

                $updates = ['total_approved_deposits' => $total];

                if ($wallet->wallet_activated_at === null && ($total >= 100_000 || (int) $wallet->balance >= 100_000)) {
                    $updates['wallet_activated_at'] = now();
                }

                DB::table('driver_wallets')->where('id', $wallet->id)->update($updates);
            }
        }
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            if (Schema::hasColumn('bookings', 'repeat_cancel_flag')) {
                $table->dropColumn('repeat_cancel_flag');
            }
            if (Schema::hasColumn('bookings', 'operator_dismissed_at')) {
                $table->dropColumn('operator_dismissed_at');
            }
        });

        Schema::table('driver_wallets', function (Blueprint $table): void {
            if (Schema::hasColumn('driver_wallets', 'total_approved_deposits')) {
                $table->dropColumn('total_approved_deposits');
            }
            if (Schema::hasColumn('driver_wallets', 'wallet_activated_at')) {
                $table->dropColumn('wallet_activated_at');
            }
        });
    }
};
