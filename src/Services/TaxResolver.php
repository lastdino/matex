<?php

declare(strict_types=1);

namespace Lastdino\Matex\Services;

use Carbon\CarbonInterface;
use Lastdino\Matex\Models\Material;
use Lastdino\Matex\Support\Settings;

final class TaxResolver
{
    public function resolveRate(?Material $material, ?CarbonInterface $at = null): float
    {
        $itemTax = Settings::itemTax($at);
        $rate = (float) ($itemTax['default_rate'] ?? 0.10);
        if ($material) {
            $code = (string) ($material->getAttribute('tax_code') ?? '');
            if ($code !== '' && isset($itemTax['rates'][$code])) {
                $rate = (float) $itemTax['rates'][$code];
            }
        }

        return $rate;
    }
}
