<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('phone')->nullable()->index();
            $table->enum('role', ['customer', 'operator', 'admin', 'driver'])->default('customer')->index();
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active')->index();
            $table->string('address')->nullable();
            $table->string('id_number')->nullable()->comment('CCCD/CMND');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
