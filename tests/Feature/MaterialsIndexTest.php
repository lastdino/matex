<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lastdino\ProcurementFlow\Models\Material;
use Livewire\Livewire;

uses(\Tests\TestCase::class, RefreshDatabase::class);

test('materials index livewire component can be rendered', function () {
    Livewire::test('procflow::procurement.materials.index')
        ->assertStatus(200);
});

test('materials index component has sdsUpload property and it is used correctly in modal', function () {
    $material = Material::create([
        'sku' => 'SKU-001',
        'name' => 'Test Material',
        'unit_stock' => 'pcs',
        'current_stock' => 10,
    ]);

    Livewire::test('procflow::procurement.materials.index')
        ->call('openSdsModal', $material->id)
        ->assertSet('showSdsModal', true)
        ->assertSet('sdsMaterialId', $material->id)
        ->assertSet('sdsUpload', null);
});

test('materials index component can toggle active status', function () {
    $material = Material::create([
        'sku' => 'SKU-001',
        'name' => 'Test Material',
        'unit_stock' => 'pcs',
        'current_stock' => 10,
        'is_active' => true,
    ]);

    Livewire::test('procflow::procurement.materials.index')
        ->call('toggleActive', $material->id)
        ->assertDispatched('toast', type: 'success', message: 'Material deactivated');

    expect($material->refresh()->is_active)->toBeFalse();

    Livewire::test('procflow::procurement.materials.index')
        ->call('toggleActive', $material->id)
        ->assertDispatched('toast', type: 'success', message: 'Material activated');

    expect($material->refresh()->is_active)->toBeTrue();
});
