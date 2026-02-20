<?php

use Illuminate\Support\Facades\Route;
use Lastdino\Matex\Http\Controllers\Api\StockMovementController;

Route::group([
    'prefix' => 'api/matex',
    'middleware' => config('matex.api_middleware', ['api']),
], function () {
    Route::post('/stock-in', [StockMovementController::class, 'stockIn'])
        ->name('matex.api.stock-in');
    Route::post('/stock-out', [StockMovementController::class, 'stockOut'])
        ->name('matex.api.stock-out');
    Route::get('/stock-movements', [StockMovementController::class, 'history'])
        ->name('matex.api.stock-movements');
});
