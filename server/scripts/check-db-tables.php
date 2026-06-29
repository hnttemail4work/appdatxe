<?php

$base = dirname(__DIR__);
$envFile = $base . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\"'");
        if (str_starts_with($key, 'DB_')) {
            putenv("{$key}={$value}");
        }
    }
}
putenv('DB_CONNECTION=mysql');

require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$app['config']->set('database.default', 'mysql');
Illuminate\Support\Facades\DB::purge('mysql');
Illuminate\Support\Facades\DB::reconnect('mysql');

use Illuminate\Support\Facades\Schema;

echo config('database.connections.mysql.database') . PHP_EOL;
foreach (['driver_trip_requests', 'driver_wallets', 'driver_profiles', 'bookings', 'migrations'] as $t) {
    echo $t . ': ' . (Schema::hasTable($t) ? 'yes' : 'no') . PHP_EOL;
}
