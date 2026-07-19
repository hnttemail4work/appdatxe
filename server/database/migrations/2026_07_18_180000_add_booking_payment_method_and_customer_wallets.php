<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('bookings', 'payment_method')) {
                $table->string('payment_method', 32)->default('cash')->after('payment_status');
            }
            if (! Schema::hasColumn('bookings', 'payment_proof_path')) {
                $table->string('payment_proof_path')->nullable()->after('payment_method');
            }
        });

        if (! Schema::hasTable('customer_wallets')) {
            Schema::create('customer_wallets', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->unsignedBigInteger('balance')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('customer_wallet_transactions')) {
            Schema::create('customer_wallet_transactions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('customer_wallet_id')->constrained('customer_wallets')->cascadeOnDelete();
                $table->string('type', 32)->default('deposit');
                $table->unsignedBigInteger('amount');
                $table->string('status', 32)->default('pending')->index();
                $table->string('transfer_ref', 64)->nullable();
                $table->string('proof_image_path')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['customer_wallet_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_wallet_transactions');
        Schema::dropIfExists('customer_wallets');

        Schema::table('bookings', function (Blueprint $table): void {
            if (Schema::hasColumn('bookings', 'payment_proof_path')) {
                $table->dropColumn('payment_proof_path');
            }
            if (Schema::hasColumn('bookings', 'payment_method')) {
                $table->dropColumn('payment_method');
            }
        });
    }
};
