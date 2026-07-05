<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$admin = User::query()->where('email', 'gozvietadmin')->first()
    ?? User::query()->where('email', 'admin@appdatxe.test')->first()
    ?? User::query()->where('role', 'admin')->first();

$adminData = [
    'email'    => 'gozvietadmin',
    'name'     => 'Admin',
    'password' => Hash::make('2026g0zv!3tm@n@g3r'),
    'phone'    => null,
    'role'     => 'admin',
    'status'   => 'active',
];

if ($admin) {
    $admin->update($adminData);
} else {
    $admin = User::query()->create($adminData);
}

echo 'Admin #' . $admin->id . ' login=' . $admin->email . PHP_EOL;
