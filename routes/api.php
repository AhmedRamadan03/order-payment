<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->controller(AuthController::class)->group(function (): void {
    Route::post('/register', 'register')->middleware('throttle:10,1');
    Route::post('/login', 'login')->middleware('throttle:10,1');
    Route::middleware('auth:api')->group(function (): void {
        Route::get('/me', 'me');
        Route::post('/logout', 'logout');
        Route::post('/refresh', 'refresh');
    });
});

Route::middleware('auth:api')->group(function (): void {
    Route::apiResource('orders', OrderController::class);
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);
    Route::get('/orders/{order}/payments', [PaymentController::class, 'forOrder']);
    Route::post('/orders/{order}/payments', [PaymentController::class, 'store']);
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::get('/payments/{payment}', [PaymentController::class, 'show']);
});
