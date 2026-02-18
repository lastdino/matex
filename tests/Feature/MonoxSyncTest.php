<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Lastdino\ProcurementFlow\Models\Material;
use Lastdino\ProcurementFlow\Models\MaterialLot;
use Lastdino\ProcurementFlow\Models\StockMovement;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

test('monox api is called when stock movement is created for synced material', function () {
    Http::fake([
        '*/api/monox/v1/inventory/sync' => Http::response(['message' => 'success'], 200),
    ]);

    config(['procurement_flow.monox.base_url' => 'https://monox.test']);
    config(['procurement_flow.monox.api_key' => 'secret-key']);

    $material = Material::create([
        'sku' => 'MONOX-001',
        'name' => 'Monox Item',
        'unit_stock' => 'pcs',
        'current_stock' => 10,
        'sync_to_monox' => true,
        'monox_item_id' => 'MX-123',
        'is_active' => true,
    ]);

    $lot = MaterialLot::create([
        'material_id' => $material->id,
        'lot_no' => 'LOT-001',
        'qty_on_hand' => 10,
    ]);

    // 在庫移動を作成（オブザーバーが発火するはず）
    StockMovement::create([
        'material_id' => $material->id,
        'lot_id' => $lot->id,
        'type' => 'out',
        'qty_base' => 5,
        'unit' => 'pcs',
        'occurred_at' => now(),
        'reason' => 'Test sync',
    ]);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://monox.test/api/monox/v1/inventory/sync' &&
               $request->header('X-API-KEY')[0] === 'secret-key' &&
               $request['sku'] === 'MONOX-001' &&
               $request['type'] === 'out' &&
               $request['qty'] === 5.0 &&
               $request['reason'] === 'Test sync';
    });
});

test('monox api is NOT called when material is not synced', function () {
    Http::fake();

    config(['procurement_flow.monox.base_url' => 'https://monox.test']);

    $material = Material::create([
        'sku' => 'NORMAL-001',
        'name' => 'Normal Item',
        'unit_stock' => 'pcs',
        'current_stock' => 10,
        'sync_to_monox' => false, // 同期オフ
        'is_active' => true,
    ]);

    StockMovement::create([
        'material_id' => $material->id,
        'type' => 'in',
        'qty_base' => 5,
        'unit' => 'pcs',
        'occurred_at' => now(),
    ]);

    Http::assertNothingSent();
});

test('monox api is called with SKU even if monox_item_id is empty', function () {
    Http::fake([
        '*/api/monox/v1/inventory/sync' => Http::response(['message' => 'success'], 200),
    ]);

    config(['procurement_flow.monox.base_url' => 'https://monox.test']);
    config(['procurement_flow.monox.api_key' => 'secret-key']);

    $material = Material::create([
        'sku' => 'SKU-ONLY-001',
        'name' => 'SKU Only Item',
        'unit_stock' => 'pcs',
        'current_stock' => 10,
        'sync_to_monox' => true,
        'monox_item_id' => null, // IDなし
        'is_active' => true,
    ]);

    StockMovement::create([
        'material_id' => $material->id,
        'type' => 'in',
        'qty_base' => 100,
        'unit' => 'pcs',
        'occurred_at' => now(),
    ]);

    Http::assertSent(function ($request) {
        return $request['sku'] === 'SKU-ONLY-001' &&
               $request['type'] === 'in' &&
               $request['qty'] === 100.0 &&
               $request['reason'] === 'procurement-flow からの同期';
    });
});

test('monox api is NOT called when is_external_sync is true', function () {
    Http::fake();

    config(['procurement_flow.monox.base_url' => 'https://monox.test']);

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
        'is_external_sync' => true, // 外部同期フラグを立てる
    ]);

    Http::assertNothingSent();
});
