<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guard hasTable(): migration này từng nằm sai thứ tự (sau các migration ALTER
        // bảng driver_trip_settlements) và đã được đổi lại tên file cho đúng thứ tự thời gian.
        // Trên môi trường đã từng chạy migration cũ, các bảng này có thể đã tồn tại — bỏ qua
        // an toàn để không lỗi "table already exists" khi Laravel chạy lại theo tên file mới.
        if (Schema::hasTable('driver_wallets')) {
            return;
        }

        Schema::create('driver_wallets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('driver_profile_id')->unique()->constrained('driver_profiles')->cascadeOnDelete();
            $table->unsignedBigInteger('balance')->default(0);
            $table->unsignedBigInteger('cumulative_revenue')->default(0);
            $table->unsignedSmallInteger('completed_settlements_count')->default(0);
            $table->boolean('wallet_gate_enabled')->default(false);
            $table->timestamp('platform_fee_deadline_at')->nullable();
            $table->timestamp('accept_trips_blocked_at')->nullable();
            $table->string('accept_trips_block_reason', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('driver_trip_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('driver_wallet_id')->constrained('driver_wallets')->cascadeOnDelete();
            $table->foreignId('booking_id')->unique()->constrained('bookings')->cascadeOnDelete();
            $table->unsignedBigInteger('revenue_amount');
            $table->unsignedBigInteger('platform_fee_amount');
            $table->enum('category', ['under_threshold', 'first_over_threshold', 'over_threshold']);
            $table->enum('status', [
                'pending_settle',
                'pending_admin_transfer',
                'pending_operator_fee',
                'pending_deposit',
                'completed',
            ])->default('pending_settle');
            $table->string('transfer_ref', 100)->nullable();
            $table->timestamp('driver_settled_at')->nullable();
            $table->timestamp('admin_confirmed_at')->nullable();
            $table->timestamp('operator_approved_at')->nullable();
            $table->foreignId('admin_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('operator_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('driver_wallet_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('driver_wallet_id')->constrained('driver_wallets')->cascadeOnDelete();
            $table->enum('type', ['deposit']);
            $table->unsignedBigInteger('amount');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('transfer_ref', 100)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_wallet_transactions');
        Schema::dropIfExists('driver_trip_settlements');
        Schema::dropIfExists('driver_wallets');
    }
};
