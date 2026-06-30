<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$admin = User::query()->updateOrCreate(
    ['email' => 'admin@appdatxe.test'],
    [
        'name'     => 'Admin',
        'password' => Hash::make('password'),
        'phone'    => '0981952856',
        'role'     => 'admin',
        'status'   => 'active',
    ],
);

echo 'Admin #' . $admin->id . ' phone=' . $admin->phone . PHP_EOL;
