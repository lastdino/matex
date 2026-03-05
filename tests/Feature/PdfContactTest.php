<?php

declare(strict_types=1);

namespace Lastdino\Matex\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Lastdino\Matex\Enums\PurchaseOrderStatus;
use Lastdino\Matex\Models\PurchaseOrder;
use Lastdino\Matex\Models\Supplier;
use Lastdino\Matex\Models\SupplierContact;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('PDF contains supplier contact person name from supplier model', function () {
    $user = User::factory()->create();
    $supplier = Supplier::create([
        'name' => 'Test Supplier',
        'contact_person_name' => 'Supplier Contact Name',
    ]);

    $po = PurchaseOrder::create([
        'supplier_id' => $supplier->id,
        'status' => PurchaseOrderStatus::Issued,
        'created_by' => $user->id,
        'po_number' => 'PO-123',
    ]);

    // リレーションをロードせずにBladeをレンダリングして、名前が含まれているか確認
    $html = Blade::render('matex::pdf.purchase-order', ['po' => $po]);
    // file_put_contents('test_pdf.html', $html);

    expect($html)->toContain('Supplier Contact Name');
});

test('PDF contains supplier contact person name from contact relation', function () {
    $user = User::factory()->create();
    $supplier = Supplier::create([
        'name' => 'Test Supplier',
        'contact_person_name' => 'Supplier Contact Name',
    ]);

    $contact = SupplierContact::create([
        'supplier_id' => $supplier->id,
        'name' => 'Specific Contact Name',
        'department' => 'Sales Dept',
    ]);

    $po = PurchaseOrder::create([
        'supplier_id' => $supplier->id,
        'supplier_contact_id' => $contact->id,
        'status' => PurchaseOrderStatus::Issued,
        'created_by' => $user->id,
        'po_number' => 'PO-456',
    ]);

    // コントローラーでの状況を再現
    $po = PurchaseOrder::find($po->id)->load(['supplier', 'contact']);

    $html = Blade::render('matex::pdf.purchase-order', ['po' => $po]);

    expect($html)->toContain('Specific Contact Name');
    expect($html)->toContain('Sales Dept');
});

test('PDF contains unit_purchase next to quantity', function () {
    $user = User::factory()->create();
    $supplier = Supplier::create(['name' => 'Test Supplier']);

    $po = PurchaseOrder::create([
        'supplier_id' => $supplier->id,
        'status' => PurchaseOrderStatus::Issued,
        'created_by' => $user->id,
    ]);

    $po->items()->create([
        'description' => 'Test Item',
        'qty_ordered' => 10,
        'unit_purchase' => 'kg',
        'price_unit' => 100,
        'tax_rate' => 0.1,
    ]);

    // Load items for the view
    $po->load('items');

    $html = Blade::render('matex::pdf.purchase-order', ['po' => $po]);

    expect($html)->toContain('単位');
    expect($html)->toContain('kg');
});
