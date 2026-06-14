<?php

namespace App\Console\Commands;

use App\Services\ScheduleLifecycleService;
use Illuminate\Console\Command;

class SyncSchedulesCommand extends Command
{
    protected $signature = 'schedules:sync';

    protected $description = 'Tạo chuyến theo ngày, cập nhật trạng thái và dọn ghế hết hạn';

    public function handle(ScheduleLifecycleService $lifecycle): int
    {
        $lifecycle->sync();

        $this->info('Đã đồng bộ lịch chuyến.');

        return self::SUCCESS;
    }
}
