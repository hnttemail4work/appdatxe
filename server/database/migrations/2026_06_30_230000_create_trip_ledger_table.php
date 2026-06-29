<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_ledger', function (Blueprint $table) {
            $table->id();
            $table->string('trip_code', 30)->unique();
            $table->enum('outcome', ['completed', 'cancelled_customer', 'cancelled_driver'])->index();
            $table->timestamp('recorded_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_ledger');
    }
};
