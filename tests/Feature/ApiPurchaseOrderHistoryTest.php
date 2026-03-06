<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lastdino\Matex\Models\Department;
use Lastdino\Matex\Models\Material;
use Lastdino\Matex\Models\PurchaseOrder;
use Lastdino\Matex\Models\PurchaseOrderItem;
use Lastdino\Matex\Models\Receiving;
use Lastdino\Matex\Models\ReceivingItem;
use Lastdino\Matex\Models\Supplier;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    if (! Route::has('matex.api.purchase-orders.history')) {
        $this->markTestSkipped('API routes are not registered.');
    }

    config(['matex.api_key' => 'test-api-key']);
});

test('can get purchase order history via api (receiving date)', function () {
    $supplier = Supplier::create(['name' => 'Test Supplier', 'is_active' => true]);
    $department = Department::create(['code' => 'DEPT-001', 'name' => 'Test Dept', 'is_active' => true]);
    $material = Material::create(['sku' => 'MAT-001', 'name' => 'Test Material', 'is_active' => true, 'unit_stock' => 'pcs']);

    $po = PurchaseOrder::create([
        'po_number' => 'PO-001',
        'supplier_id' => $supplier->id,
        'department_id' => $department->id,
        'issue_date' => now()->subDays(5),
        'status' => \Lastdino\Matex\Enums\PurchaseOrderStatus::Issued,
    ]);

    $poi = PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'material_id' => $material->id,
        'qty_ordered' => 100,
        'price_unit' => 50,
        'line_total' => 5000,
        'unit_purchase' => 'pcs',
    ]);

    $receiving = Receiving::create([
        'purchase_order_id' => $po->id,
        'received_at' => now()->subDays(2),
    ]);

    ReceivingItem::create([
        'receiving_id' => $receiving->id,
        'purchase_order_item_id' => $poi->id,
        'material_id' => $material->id,
        'qty_received' => 100,
        'unit_purchase' => 'pcs',
        'qty_base' => 100,
    ]);

    $response = $this->getJson(route('matex.api.purchase-orders.history', [
        'start_date' => now()->subDays(3)->toDateString(),
        'end_date' => now()->toDateString(),
        'date_type' => 'receiving',
    ]), ['X-API-KEY' => 'test-api-key']);

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment([
            'po_number' => 'PO-001',
            'supplier_name' => 'Test Supplier',
            'sku' => 'MAT-001',
            'qty_received' => 100.0,
        ]);
});

test('can get purchase order history via api (issue date)', function () {
    $supplier = Supplier::create(['name' => 'Test Supplier', 'is_active' => true]);
    $department = Department::create(['code' => 'DEPT-002', 'name' => 'Test Dept', 'is_active' => true]);
    $material = Material::create(['sku' => 'MAT-002', 'name' => 'Test Material 2', 'is_active' => true, 'unit_stock' => 'pcs']);

    $po = PurchaseOrder::create([
        'po_number' => 'PO-002',
        'supplier_id' => $supplier->id,
        'department_id' => $department->id,
        'issue_date' => now()->subDays(1),
        'status' => \Lastdino\Matex\Enums\PurchaseOrderStatus::Issued,
    ]);

    PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'material_id' => $material->id,
        'qty_ordered' => 50,
        'price_unit' => 10,
        'line_total' => 500,
        'unit_purchase' => 'pcs',
    ]);

    $response = $this->getJson(route('matex.api.purchase-orders.history', [
        'start_date' => now()->subDays(2)->toDateString(),
        'end_date' => now()->toDateString(),
        'date_type' => 'issue',
    ]), ['X-API-KEY' => 'test-api-key']);

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment([
            'po_number' => 'PO-002',
            'sku' => 'MAT-002',
            'qty_ordered' => 50.0,
        ]);
});
