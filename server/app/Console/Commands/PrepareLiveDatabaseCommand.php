<?php

namespace App\Console\Commands;

use App\Support\AdminBootstrapAccount;
use Database\Seeders\PrepareLiveSeeder;
use Illuminate\Console\Command;

class PrepareLiveDatabaseCommand extends Command
{
    protected $signature = 'db:prepare-live {--force : Chạy không hỏi xác nhận}';

    protected $description = 'Xóa dữ liệu test/vận hành, chỉ giữ tài khoản admin mặc định';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Xóa toàn bộ đơn, tài xế, mã GT và dữ liệu test — chỉ giữ admin?', false)) {
            $this->comment('Đã hủy.');

            return self::SUCCESS;
        }

        $this->call('db:seed', [
            '--class' => PrepareLiveSeeder::class,
            '--force' => true,
        ]);

        $this->newLine();
        $this->info('Đã chuẩn bị DB cho live.');
        $this->line('Đăng nhập admin: ' . AdminBootstrapAccount::LOGIN);
        $this->line('Mật khẩu: ' . AdminBootstrapAccount::PASSWORD_PLAIN);

        return self::SUCCESS;
    }
}
