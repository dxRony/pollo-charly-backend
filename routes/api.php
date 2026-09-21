<?php

use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PingController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ComplementController;
use App\Http\Controllers\DishController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', PingController::class);

Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword']);
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);

Route::prefix('2fa')->group(function () {
    Route::post('/verify', [TwoFactorController::class, 'verify']);
    Route::post('/resend', [TwoFactorController::class, 'resend']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::prefix('2fa')->group(function () {
        Route::get('/status', [TwoFactorController::class, 'status']);
        Route::post('/enable', [TwoFactorController::class, 'enable']);
        Route::post('/disable', [TwoFactorController::class, 'disable']);
    });

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/dishes', [DishController::class, 'index']);
    Route::get('/dishes/{dish}', [DishController::class, 'show']);
    Route::get('/complements', [ComplementController::class, 'index']);
    Route::get('/complements/{complement}', [ComplementController::class, 'show']);

    Route::middleware('role:Administrador')->group(function () {
        Route::get('/roles', [RoleController::class, 'index']);
        Route::apiResource('users', UserController::class);
        Route::patch('/users/{user}/status', [UserController::class, 'toggleStatus']);

        Route::post('/dishes', [DishController::class, 'store']);
        Route::put('/dishes/{dish}', [DishController::class, 'update']);
        Route::delete('/dishes/{dish}', [DishController::class, 'destroy']);
        Route::patch('/dishes/{dish}/status', [DishController::class, 'toggleStatus']);

        Route::post('/complements', [ComplementController::class, 'store']);
        Route::put('/complements/{complement}', [ComplementController::class, 'update']);
        Route::delete('/complements/{complement}', [ComplementController::class, 'destroy']);
        Route::patch('/complements/{complement}/status', [ComplementController::class, 'toggleStatus']);
    });
});

