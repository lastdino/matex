<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Lastdino\Matex\Models\Material;

uses(\Tests\TestCase::class, RefreshDatabase::class);

it('downloads SDS via signed route', function () {
    Storage::fake('local');

    // create user and login (routes are behind auth middleware)
    $user = User::factory()->create();
    $this->actingAs($user);

    // create material and attach an SDS PDF to the media collection
    $material = Material::create([
        'sku' => 'SKU-100',
        'name' => 'Material with SDS',
        'unit_stock' => 'pcs',
        'current_stock' => 0,
    ]);

    $pdfContent = '%PDF-1.4 Fake PDF content for testing';
    $material->addMediaFromString($pdfContent)
        ->usingFileName('sds.pdf')
        ->withCustomProperties(['mime_type' => 'application/pdf'])
        ->toMediaCollection('sds');

    $url = URL::signedRoute('matex.materials.sds.download', ['material' => $material->getKey()]);

    $response = $this->get($url);

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    // Streamed download responses set content-disposition; ensure a file is being downloaded
    $response->assertHeader('content-disposition');
});
