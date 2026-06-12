<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('app:status', function (): void {
    $this->comment('Long-distance car booking backend is ready.');
})->purpose('Show backend status');
