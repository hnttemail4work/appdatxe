<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('operator_id')->constrained('users')->cascadeOnDelete();
            $table->string('license_plate')->unique();
            $table->enum('type', ['limousine', 'sedan', 'suv'])->index();
            $table->unsignedSmallInteger('capacity');
            $table->enum('status', ['active', 'maintenance', 'inactive'])->default('active')->index();
            $table->timestamps();

            $table->index(['operator_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
