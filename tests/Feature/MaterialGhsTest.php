<?php

declare(strict_types=1);

use Lastdino\ProcurementFlow\Models\Material;

test('ghsIconNames converts GHS mark keys to flux icon names', function () {
    $material = new Material([
        'ghs_mark' => 'GHS01, GHS07 | GHS09',
    ]);

    expect($material->ghsIconNames())->toBe(['ghs-01', 'ghs-07', 'ghs-09']);
});

test('ghsIconNames handles lowercase and spaces', function () {
    $material = new Material([
        'ghs_mark' => ' ghs02 , GHS05 ',
    ]);

    expect($material->ghsIconNames())->toBe(['ghs-02', 'ghs-05']);
});

test('ghsIconNames returns empty array for empty ghs_mark', function () {
    $material = new Material([
        'ghs_mark' => null,
    ]);

    expect($material->ghsIconNames())->toBe([]);
});
