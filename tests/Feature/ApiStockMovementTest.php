<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lastdino\ProcurementFlow\Models\Material;
use Lastdino\ProcurementFlow\Models\MaterialLot;
use Lastdino\ProcurementFlow\Models\StockMovement;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    // APIルートが登録されているか確認（ServiceProvider経由で登録されているはず）
    if (! Route::has('procurement.api.stock-in')) {
        $this->markTestSkipped('API routes are not registered.');
    }

    // テスト用にAPIキーを設定
    config(['procurement_flow.api_key' => 'test-api-key']);
});

test('unauthorized if api key is missing', function () {
    $response = $this->postJson(route('procurement.api.stock-in'), [
        'sku' => 'SKU-ANY',
        'lot_no' => 'LOT-ANY',
        'qty' => 10,
    ]);

    $response->assertStatus(401);
});

test('unauthorized if api key is invalid', function () {
    $response = $this->postJson(route('procurement.api.stock-in'), [
        'sku' => 'SKU-ANY',
        'lot_no' => 'LOT-ANY',
        'qty' => 10,
    ], ['X-API-KEY' => 'wrong-key']);

    $response->assertStatus(401);
});

test('can stock in via api', function () {
    $material = Material::create([
        'sku' => 'SKU-TEST-001',
        'name' => 'Test Material',
        'unit_stock' => 'pcs',
        'current_stock' => 0,
        'is_active' => true,
    ]);

    $response = $this->postJson(route('procurement.api.stock-in'), [
        'sku' => 'SKU-TEST-001',
        'lot_no' => 'LOT-2026-001',
        'qty' => 10,
        'reason' => 'Initial Stock',
    ], ['X-API-KEY' => 'test-api-key']);

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Stock in recorded successfully',
            'sku' => 'SKU-TEST-001',
            'lot_no' => 'LOT-2026-001',
            'new_lot_qty' => 10,
            'new_material_stock' => 10,
        ]);

    $lot = MaterialLot::where('lot_no', 'LOT-2026-001')->first();
    expect($lot)->not->toBeNull()
        ->and((float) $lot->qty_on_hand)->toBe(10.0)
        ->and($lot->material_id)->toBe($material->id);

    $movement = StockMovement::where('lot_id', $lot->id)->first();
    expect($movement)->not->toBeNull()
        ->and($movement->type)->toBe('in')
        ->and((float) $movement->qty_base)->toBe(10.0);

    $material->refresh();
    expect((float) $material->current_stock)->toBe(10.0);
});

test('can stock out via api', function () {
    $material = Material::create([
        'sku' => 'SKU-TEST-002',
        'name' => 'Test Material 2',
        'unit_stock' => 'pcs',
        'current_stock' => 50,
        'is_active' => true,
    ]);

    $lot = MaterialLot::create([
        'material_id' => $material->id,
        'lot_no' => 'LOT-EXISTING',
        'qty_on_hand' => 50,
    ]);

    $response = $this->postJson(route('procurement.api.stock-out'), [
        'sku' => 'SKU-TEST-002',
        'lot_no' => 'LOT-EXISTING',
        'qty' => 20,
        'reason' => 'Usage',
    ], ['X-API-KEY' => 'test-api-key']);

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Stock out recorded successfully',
            'sku' => 'SKU-TEST-002',
            'lot_no' => 'LOT-EXISTING',
            'new_lot_qty' => 30,
            'new_material_stock' => 30,
        ]);

    $lot->refresh();
    expect((float) $lot->qty_on_hand)->toBe(30.0);

    $material->refresh();
    expect((float) $material->current_stock)->toBe(30.0);

    $movement = StockMovement::where('type', 'out')->first();
    expect($movement)->not->toBeNull()
        ->and((float) $movement->qty_base)->toBe(20.0);
});

test('returns 404 for unknown sku', function () {
    $response = $this->postJson(route('procurement.api.stock-in'), [
        'sku' => 'NON-EXISTENT',
        'lot_no' => 'LOT-001',
        'qty' => 10,
    ], ['X-API-KEY' => 'test-api-key']);

    $response->assertStatus(404);
});

test('returns 422 for insufficient stock in lot', function () {
    $material = Material::create([
        'sku' => 'SKU-TEST-003',
        'name' => 'Test Material 3',
        'unit_stock' => 'pcs',
        'current_stock' => 10,
        'is_active' => true,
    ]);

    $lot = MaterialLot::create([
        'material_id' => $material->id,
        'lot_no' => 'LOT-LITTLE',
        'qty_on_hand' => 5,
    ]);

    $response = $this->postJson(route('procurement.api.stock-out'), [
        'sku' => 'SKU-TEST-003',
        'lot_no' => 'LOT-LITTLE',
        'qty' => 10,
    ], ['X-API-KEY' => 'test-api-key']);

    $response->assertStatus(422)
        ->assertJsonFragment(['message' => 'Insufficient stock in lot']);
});
