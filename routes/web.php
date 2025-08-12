<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebhookTestController;

Route::get('/', function () {
    return view('welcome');
});

// Routes pour tester le package de webhooks GitHub
Route::prefix('webhook-admin')->group(function () {
    Route::get('/stats', [WebhookTestController::class, 'index'])->name('webhook.stats');
    Route::post('/test', [WebhookTestController::class, 'test'])->name('webhook.test');
});
