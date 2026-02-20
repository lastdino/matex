<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lastdino\ProcurementFlow\Models\Material;
use Lastdino\ProcurementFlow\Models\MaterialLot;
use Lastdino\ProcurementFlow\Models\StorageLocation;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('moving a lot to a new location succeeds when multiple lots of the same material exist', function () {
    $loc1 = StorageLocation::create(['name' => 'Location 1', 'is_active' => true]);
    $loc2 = StorageLocation::create(['name' => 'Location 2', 'is_active' => true]);

    $material = Material::create([
        'sku' => 'SKU-001',
        'name' => 'Test Material',
        'unit_stock' => 'L',
    ]);

    $lot = MaterialLot::create([
        'material_id' => $material->id,
        'lot_no' => 'LOT-A',
        'qty_on_hand' => 10,
        'storage_location_id' => $loc1->id,
    ]);

    // This should work if the unique constraint includes storage_location_id.
    // However, if the current constraint is only (material_id, lot_no),
    // this test will fail during setup or during the transfer action.

    Livewire::test('procflow::procurement.materials.show', ['material' => $material])
        ->call('openTransferModal', $lot->id)
        ->set('transferForm.to_storage_location_id', $loc2->id)
        ->set('transferForm.qty', 5)
        ->call('transfer')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('procurement_flow_material_lots', [
        'material_id' => $material->id,
        'lot_no' => 'LOT-A',
        'storage_location_id' => $loc1->id,
        'qty_on_hand' => 5,
    ]);

    $this->assertDatabaseHas('procurement_flow_material_lots', [
        'material_id' => $material->id,
        'lot_no' => 'LOT-A',
        'storage_location_id' => $loc2->id,
        'qty_on_hand' => 5,
    ]);
});
