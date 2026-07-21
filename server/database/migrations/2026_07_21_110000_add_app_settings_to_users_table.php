<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'locale')) {
                $table->string('locale', 8)->default('vi')->after('gender');
            }
            if (! Schema::hasColumn('users', 'sound_enabled')) {
                $table->boolean('sound_enabled')->default(true)->after('locale');
            }
            if (! Schema::hasColumn('users', 'sound_preset')) {
                $table->string('sound_preset', 20)->default('tone1')->after('sound_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['locale', 'sound_enabled', 'sound_preset'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
