<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->enum('provider', ['vietqr', 'momo', 'bank_transfer', 'cash', 'manual'])->default('vietqr')->index();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('VND');
            $table->enum('status', ['pending', 'succeeded', 'failed', 'refunded'])->default('pending')->index();
            $table->string('transaction_ref')->nullable()->unique();
            $table->json('payload')->nullable();
            $table->timestamp('paid_at')->nullable()->index();
            $table->timestamps();

            $table->index(['booking_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
