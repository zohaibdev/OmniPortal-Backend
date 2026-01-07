<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => 'OmniPortal API',
        'version' => '1.0.0',
    ]);
});
