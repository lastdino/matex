<?php

declare(strict_types=1);

namespace Lastdino\Matex\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lastdino\Matex\Enums\PurchaseOrderStatus;
use Lastdino\Matex\Models\PurchaseOrder;
use Lastdino\Matex\Models\Supplier;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('draft purchase order cannot be downloaded as PDF', function () {
    $user = User::factory()->create();
    $supplier = Supplier::create(['name' => 'Test Supplier']);

    $po = PurchaseOrder::create([
        'supplier_id' => $supplier->id,
        'status' => PurchaseOrderStatus::Draft,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('matex.purchase-orders.pdf', $po))
        ->assertStatus(403);
});

test('issued purchase order can be accessed (not 403)', function () {
    // Note: This might fail if Chrome/PDF generation is not set up in the test environment,
    // but at least it should not return 403 from our controller's logic.
    $user = User::factory()->create();
    $supplier = Supplier::create(['name' => 'Test Supplier']);

    $po = PurchaseOrder::create([
        'supplier_id' => $supplier->id,
        'status' => PurchaseOrderStatus::Issued,
        'created_by' => $user->id,
    ]);

    // Mock Chrome if necessary, but here we just check it's not 403 from the status check.
    // If it fails due to Chrome not being available, it will be 500, not 403.
    $response = $this->actingAs($user)
        ->get(route('matex.purchase-orders.pdf', $po));

    $response->assertStatus(200);
});

test('PDF download button is hidden for draft purchase orders in show page', function () {
    $user = User::factory()->create();
    $supplier = Supplier::create(['name' => 'Test Supplier']);

    $po = PurchaseOrder::create([
        'supplier_id' => $supplier->id,
        'status' => PurchaseOrderStatus::Draft,
        'created_by' => $user->id,
    ]);

    Livewire::actingAs($user)
        ->test('matex::matex.purchase-orders.show', ['po' => $po])
        ->assertDontSee(route('matex.purchase-orders.pdf', $po));
});

test('PDF download button is visible for issued purchase orders in show page', function () {
    $user = User::factory()->create();
    $supplier = Supplier::create(['name' => 'Test Supplier']);

    $po = PurchaseOrder::create([
        'supplier_id' => $supplier->id,
        'status' => PurchaseOrderStatus::Issued,
        'created_by' => $user->id,
    ]);

    Livewire::actingAs($user)
        ->test('matex::matex.purchase-orders.show', ['po' => $po])
        ->assertSee(route('matex.purchase-orders.pdf', $po));
});
