<?php

declare(strict_types=1);

namespace Lastdino\Matex\Tests\Feature;

use App\Models\User;
use App\Models\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lastdino\Matex\Enums\PurchaseOrderStatus;
use Lastdino\Matex\Models\PurchaseOrder;
use Lastdino\Matex\Models\Supplier;
use Tests\TestCase;
use Carbon\Carbon;

uses(TestCase::class, RefreshDatabase::class);

test('exportExcel works with issue date base', function () {
    $user = User::factory()->create();
    $supplier = Supplier::create(['name' => 'Test Supplier']);
    $department = Department::create(['name' => 'Sales', 'code' => 'SALES']);

    // Create a PO issued yesterday
    $yesterday = Carbon::yesterday();
    $po = PurchaseOrder::create([
        'supplier_id' => $supplier->id,
        'department_id' => $department->id,
        'status' => PurchaseOrderStatus::Issued,
        'created_by' => $user->id,
        'po_number' => 'PO-ISSUE-1',
        'issue_date' => $yesterday,
    ]);

    $po->items()->create([
        'description' => 'Test Item',
        'qty_ordered' => 5,
        'unit_purchase' => 'pcs',
        'price_unit' => 200,
        'tax_rate' => 0.1,
    ]);

    // Livewire component test
    $component = Livewire::actingAs($user)
        ->test('matex.purchase-orders.index')
        ->set('exportDateType', 'issue')
        ->set('receivingDate', [
            'start' => $yesterday->format('Y-m-d'),
            'end' => $yesterday->format('Y-m-d'),
        ])
        ->set('aggregateType', 'ordered_amount');

    // Run export
    $response = $component->call('exportExcel');

    // Assert success
    $response->assertStatus(200);
});

test('exportExcel validation works for issue date', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('matex.purchase-orders.index')
        ->set('exportDateType', 'issue')
        ->set('receivingDate', [
            'start' => '',
            'end' => '',
        ])
        ->call('exportExcel');

    $component->assertHasErrors(['receivingDate']);
});
