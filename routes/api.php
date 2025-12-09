<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lastdino\ProcurementFlow\Http\Controllers\PurchaseOrderController;
use Lastdino\ProcurementFlow\Http\Controllers\PurchaseOrderIssueController;
use Lastdino\ProcurementFlow\Http\Controllers\ReceivingController;
use Lastdino\ProcurementFlow\Http\Controllers\ScanReceivingController;
use Lastdino\ProcurementFlow\Http\Controllers\IssueController;
use Lastdino\ProcurementFlow\Models\PurchaseOrder;
use Lastdino\ProcurementFlow\Models\Material;

Route::middleware('api')->prefix('api')->group(function () {
    Route::model('po', PurchaseOrder::class);
    Route::model('material', Material::class);
    Route::apiResource('purchase-orders', PurchaseOrderController::class)->only(['store', 'index', 'show']);
    Route::post('purchase-orders/{po}/issue', PurchaseOrderIssueController::class);
    Route::post('purchase-orders/{po}/receivings', [ReceivingController::class, 'store']);

    // Scan-based receiving endpoints
    Route::get('receivings/scan/{token}', [ScanReceivingController::class, 'info']);
    Route::post('receivings/scan', [ScanReceivingController::class, 'store']);

    // Issue (stock out) API
    Route::post('materials/{material}/issue', [IssueController::class, 'store']);
});
