<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('schedule_merge_requests')) {
            return;
        }

        if ($this->indexExists('schedule_merge_requests', 'sched_merge_pair_status_idx')) {
            return;
        }

        Schema::table('schedule_merge_requests', function (Blueprint $table): void {
            $table->index(['target_schedule_id', 'source_schedule_id', 'status'], 'sched_merge_pair_status_idx');
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        if ($connection->getDriverName() === 'sqlite') {
            return false;
        }

        $result = $connection->select(
            'SELECT COUNT(*) AS c FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $index],
        );

        return ((int) ($result[0]->c ?? 0)) > 0;
    }

    public function down(): void
    {
        if (! Schema::hasTable('schedule_merge_requests')) {
            return;
        }

        Schema::table('schedule_merge_requests', function (Blueprint $table): void {
            $table->dropIndex('sched_merge_pair_status_idx');
        });
    }
};
