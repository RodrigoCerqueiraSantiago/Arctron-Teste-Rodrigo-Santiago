<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\WooCommerceWebhookController;
use App\Http\Middleware\NoStore;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', NoStore::class])->group(function () {
    Route::get('/csrf', [AuthController::class, 'csrf']);
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::middleware('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/purchases', [PurchaseController::class, 'index']);
        Route::post('/purchases', [PurchaseController::class, 'store'])->can('manage-purchases');
        Route::get('/dashboard', DashboardController::class);
    });
});
Route::post('/webhooks/woocommerce', WooCommerceWebhookController::class);
Route::post('/integrations/woocommerce/webhook', WooCommerceWebhookController::class);
