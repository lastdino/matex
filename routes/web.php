<?php

use Illuminate\Support\Facades\Route;
use Lastdino\Matex\Http\Controllers\MaterialSdsDownloadController;
use Lastdino\Matex\Http\Controllers\PurchaseOrderPdfController;

Route::group([
    'prefix' => config('matex.route_prefix', 'procurement'),
    'middleware' => config('matex.middleware', ['web', 'auth']),
], function () {
    // Dashboard
    Route::livewire('/', 'matex::matex.dashboard')->name('matex.dashboard');

    // Purchase Orders
    Route::livewire('/purchase-orders', 'matex::matex.purchase-orders.index')
        ->name('matex.purchase-orders.index');

    // Purchase Order detail
    Route::livewire('/purchase-orders/{po}', 'matex::matex.purchase-orders.show')
        ->name('matex.purchase-orders.show');

    // Purchase Order PDF download
    Route::get('/purchase-orders/{po}/pdf', PurchaseOrderPdfController::class)
        ->name('matex.purchase-orders.pdf');

    // Pending Receiving
    Route::livewire('/pending-receiving', 'matex::matex.pending-receiving.index')
        ->name('matex.pending-receiving.index');

    // Materials
    Route::livewire('/materials', 'matex::matex.materials.index')
        ->name('matex.materials.index');
    Route::livewire('/materials/{material}', 'matex::matex.materials.show')
        ->name('matex.materials.show');
    Route::livewire('/materials/{material}/issue', 'matex::matex.materials.issue')
        ->name('matex.materials.issue');
    // Material SDS secure download (signed + auth)
    Route::get('/materials/{material}/sds', MaterialSdsDownloadController::class)
        ->middleware('signed')
        ->name('matex.materials.sds.download');

    // Suppliers
    Route::livewire('/suppliers', 'matex::matex.suppliers.index')
        ->name('matex.suppliers.index');

    // Receiving scan page
    Route::livewire('/receivings/scan', 'matex::matex.receiving.scan')
        ->name('matex.receiving.scan');

    // Options settings
    Route::livewire('/settings/options', 'matex::matex.settings.options.index')
        ->name('matex.settings.options');

    // Approval settings (flowId selection for POs)
    Route::livewire('/settings/approval', 'matex::matex.settings.approval.index')
        ->name('matex.settings.approval');

    // Taxes settings
    Route::livewire('/settings/taxes', 'matex::matex.settings.taxes.index')
        ->name('matex.settings.taxes');

    // Material Categories settings
    Route::livewire('/settings/categories', 'matex::matex.settings.categories.index')
        ->name('matex.settings.categories');

    // Storage Locations settings
    Route::livewire('/settings/storage-locations', 'matex::matex.settings.storage-locations.index')
        ->name('matex.settings.storage-locations');

    // PDF settings
    Route::livewire('/settings/pdf', 'matex::matex.settings.pdf.index')
        ->name('matex.settings.pdf');

    // Display settings (decimals & currency)
    Route::livewire('/settings/display', 'matex::matex.settings.display.index')
        ->name('matex.settings.display');

    // Ordering Tokens settings (CRUD)
    Route::livewire('/settings/tokens', 'matex::matex.settings.tokens.index')
        ->name('matex.settings.tokens');

    // Token Labels (printable)
    Route::livewire('/settings/labels', 'matex::matex.settings.tokens.labels')
        ->name('matex.settings.labels');

    // Ordering scan page (QR→発注ドラフト作成)
    Route::livewire('/ordering/scan', 'matex::matex.ordering.scan')
        ->name('matex.ordering.scan');
});
