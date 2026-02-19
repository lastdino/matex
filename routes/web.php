<?php

use Illuminate\Support\Facades\Route;
use Lastdino\ProcurementFlow\Http\Controllers\MaterialSdsDownloadController;
use Lastdino\ProcurementFlow\Http\Controllers\PurchaseOrderPdfController;

Route::group([
    'prefix' => config('procurement_flow.route_prefix', 'procurement'),
    'middleware' => config('procurement_flow.middleware', ['web', 'auth']),
], function () {
    // Dashboard
    Route::livewire('/', 'procflow::procurement.dashboard')->name('procurement.dashboard');

    // Purchase Orders
    Route::livewire('/purchase-orders', 'procflow::procurement.purchase-orders.index')
        ->name('procurement.purchase-orders.index');

    // Purchase Order detail
    Route::livewire('/purchase-orders/{po}', 'procflow::procurement.purchase-orders.show')
        ->name('procurement.purchase-orders.show');

    // Purchase Order PDF download
    Route::get('/purchase-orders/{po}/pdf', PurchaseOrderPdfController::class)
        ->name('procurement.purchase-orders.pdf');

    // Pending Receiving
    Route::livewire('/pending-receiving', 'procflow::procurement.pending-receiving.index')
        ->name('procurement.pending-receiving.index');

    // Materials
    Route::livewire('/materials', 'procflow::procurement.materials.index')
        ->name('procurement.materials.index');
    Route::livewire('/materials/{material}', 'procflow::procurement.materials.show')
        ->name('procurement.materials.show');
    Route::livewire('/materials/{material}/issue', 'procflow::procurement.materials.issue')
        ->name('procurement.materials.issue');
    // Material SDS secure download (signed + auth)
    Route::get('/materials/{material}/sds', MaterialSdsDownloadController::class)
        ->middleware('signed')
        ->name('procurement.materials.sds.download');

    // Suppliers
    Route::livewire('/suppliers', 'procflow::procurement.suppliers.index')
        ->name('procurement.suppliers.index');

    // Receiving scan page
    Route::livewire('/receivings/scan', 'procflow::procurement.receiving.scan')
        ->name('procurement.receiving.scan');

    // Options settings
    Route::livewire('/settings/options', 'procflow::procurement.settings.options.index')
        ->name('procurement.settings.options');

    // Approval settings (flowId selection for POs)
    Route::livewire('/settings/approval', 'procflow::procurement.settings.approval.index')
        ->name('procurement.settings.approval');

    // Taxes settings
    Route::livewire('/settings/taxes', 'procflow::procurement.settings.taxes.index')
        ->name('procurement.settings.taxes');

    // Material Categories settings
    Route::livewire('/settings/categories', 'procflow::procurement.settings.categories.index')
        ->name('procurement.settings.categories');

    // Storage Locations settings
    Route::livewire('/settings/storage-locations', 'procflow::procurement.settings.storage-locations.index')
        ->name('procurement.settings.storage-locations');

    // PDF settings
    Route::livewire('/settings/pdf', 'procflow::procurement.settings.pdf.index')
        ->name('procurement.settings.pdf');

    // Display settings (decimals & currency)
    Route::livewire('/settings/display', 'procflow::procurement.settings.display.index')
        ->name('procurement.settings.display');

    // Ordering Tokens settings (CRUD)
    Route::livewire('/settings/tokens', 'procflow::procurement.settings.tokens.index')
        ->name('procurement.settings.tokens');

    // Token Labels (printable)
    Route::livewire('/settings/labels', 'procflow::procurement.settings.tokens.labels')
        ->name('procurement.settings.labels');

    // Ordering scan page (QR→発注ドラフト作成)
    Route::livewire('/ordering/scan', 'procflow::procurement.ordering.scan')
        ->name('procurement.ordering.scan');
});
