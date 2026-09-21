<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PingController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', PingController::class);

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});
