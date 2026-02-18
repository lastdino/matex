<?php

use Illuminate\Support\Facades\Route;
use Lastdino\ProcurementFlow\Http\Controllers\Api\StockMovementController;

Route::group([
    'prefix' => 'api/procurement',
    'middleware' => config('procurement_flow.api_middleware', ['api']),
], function () {
    Route::post('/stock-in', [StockMovementController::class, 'stockIn'])
        ->name('procurement.api.stock-in');
    Route::post('/stock-out', [StockMovementController::class, 'stockOut'])
        ->name('procurement.api.stock-out');
    Route::get('/stock-movements', [StockMovementController::class, 'history'])
        ->name('procurement.api.stock-movements');
});
