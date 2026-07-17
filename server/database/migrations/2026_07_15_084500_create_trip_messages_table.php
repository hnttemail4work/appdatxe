<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('sender_role', ['customer', 'driver']);
            $table->text('body');
            $table->timestamps();

            $table->index(['booking_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_messages');
    }
};
