<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lastdino\Matex\Models\AppSetting;
use Lastdino\Matex\Support\Settings;

uses(\Tests\TestCase::class, RefreshDatabase::class);

test('taxCodes returns empty array when no rates defined in config or app settings', function () {
    // Ensure config is empty for this test
    config(['matex.item_tax.rates' => []]);

    expect(Settings::taxCodes())->toBeArray()->toBeEmpty();
});

test('taxCodes returns keys from config when no app settings exist', function () {
    config(['matex.item_tax.rates' => [
        'standard' => 0.10,
        'reduced' => 0.08,
    ]]);

    expect(Settings::taxCodes())->toBe(['standard', 'reduced']);
});

test('taxCodes returns keys from app settings when they exist', function () {
    config(['matex.item_tax.rates' => [
        'standard' => 0.10,
    ]]);

    AppSetting::setArray('matex.item_tax', [
        'rates' => [
            'custom1' => 0.05,
            'custom2' => 0.15,
        ],
    ]);

    expect(Settings::taxCodes())->toBe(['custom1', 'custom2']);
});
