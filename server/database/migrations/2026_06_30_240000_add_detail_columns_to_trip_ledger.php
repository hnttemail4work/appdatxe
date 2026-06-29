<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_ledger', function (Blueprint $table) {
            $table->string('route_label', 120)->nullable()->after('outcome');
            $table->string('actor_label', 120)->nullable()->after('route_label');
            $table->string('actor_code', 40)->nullable()->after('actor_label');
            $table->unsignedInteger('amount')->nullable()->after('actor_code');
        });
    }

    public function down(): void
    {
        Schema::table('trip_ledger', function (Blueprint $table) {
            $table->dropColumn(['route_label', 'actor_label', 'actor_code', 'amount']);
        });
    }
};
