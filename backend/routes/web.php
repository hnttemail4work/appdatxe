<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'service' => 'Long-distance car booking backend',
        'status' => 'ok',
    ]);
});
