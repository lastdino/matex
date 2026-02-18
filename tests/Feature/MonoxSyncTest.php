<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Lastdino\ProcurementFlow\Jobs\SyncStockMovementToMonox;
use Lastdino\ProcurementFlow\Models\Material;
use Lastdino\ProcurementFlow\Models\MaterialLot;
use Lastdino\ProcurementFlow\Models\StockMovement;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

test('monox sync job is dispatched when stock movement is created for synced material', function () {
    Queue::fake();

    $material = Material::create([
        'sku' => 'MONOX-001',
        'name' => 'Monox Item',
        'unit_stock' => 'pcs',
        'current_stock' => 10,
        'sync_to_monox' => true,
        'is_active' => true,
    ]);

    $lot = MaterialLot::create([
        'material_id' => $material->id,
        'lot_no' => 'LOT-001',
        'qty_on_hand' => 10,
    ]);

    // 在庫移動を作成
    $movement = StockMovement::create([
        'material_id' => $material->id,
        'lot_id' => $lot->id,
        'type' => 'out',
        'qty_base' => 5,
        'unit' => 'pcs',
        'occurred_at' => now(),
    ]);

    Queue::assertPushed(SyncStockMovementToMonox::class, function ($job) use ($movement) {
        return $job->movement->id === $movement->id;
    });
});

test('monox sync job is NOT dispatched when is_external_sync is true', function () {
    Queue::fake();

    $material = Material::create([
        'sku' => 'EXTERNAL-001',
        'name' => 'External Item',
        'unit_stock' => 'pcs',
        'current_stock' => 10,
        'sync_to_monox' => true,
        'is_active' => true,
    ]);

    StockMovement::create([
        'material_id' => $material->id,
        'type' => 'in',
        'qty_base' => 5,
        'unit' => 'pcs',
        'occurred_at' => now(),
        'is_external_sync' => true,
    ]);

    Queue::assertNotPushed(SyncStockMovementToMonox::class);
});

test('sync job calls MonoxApiService', function () {
    Http::fake([
        '*/api/monox/v1/inventory/sync' => Http::response(['message' => 'success'], 200),
    ]);

    config(['procurement_flow.monox.base_url' => 'https://monox.test']);
    config(['procurement_flow.monox.api_key' => 'secret-key']);

    $material = Material::create([
        'sku' => 'MONOX-002',
        'name' => 'Monox Item 2',
        'unit_stock' => 'pcs',
        'current_stock' => 10,
        'sync_to_monox' => true,
        'is_active' => true,
    ]);

    $movement = StockMovement::create([
        'material_id' => $material->id,
        'type' => 'in',
        'qty_base' => 10,
        'unit' => 'pcs',
        'occurred_at' => now(),
        'reason' => 'Job test',
    ]);

    $job = new SyncStockMovementToMonox($movement);
    $job->handle(new \Lastdino\ProcurementFlow\Services\MonoxApiService);

    Http::assertSent(function ($request) {
        return $request['sku'] === 'MONOX-002' &&
               $request['type'] === 'in' &&
               $request['qty'] === 10.0;
    });
});
