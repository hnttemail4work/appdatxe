<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE referral_codes MODIFY COLUMN status ENUM('pending', 'active', 'suspended') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::table('referral_codes')->where('status', 'suspended')->update(['status' => 'active']);
        DB::statement("ALTER TABLE referral_codes MODIFY COLUMN status ENUM('pending', 'active') NOT NULL DEFAULT 'pending'");
    }
};
