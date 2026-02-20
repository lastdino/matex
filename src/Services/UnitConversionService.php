<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Services;

use Lastdino\ProcurementFlow\Models\Material;
use Lastdino\ProcurementFlow\Models\UnitConversion;

class UnitConversionService
{
    public function factor(Material $material, string $from, string $to): float
    {
        if ($from === $to) {
            return 1.0;
        }

        /** @var UnitConversion|null $conv */
        $conv = UnitConversion::query()
            ->where('material_id', $material->id)
            ->where('from_unit', $from)
            ->where('to_unit', $to)
            ->first();

        if ($conv === null) {
            throw new \InvalidArgumentException("Unit conversion not defined from {$from} to {$to} for material {$material->id}");
        }

        return (float) $conv->factor;
    }

    /**
     * Get all available units for a material, including the stock unit.
     *
     * @return array<int, string>
     */
    public function getAvailableUnits(Material $material): array
    {
        $units = [$material->unit_stock];

        if ($material->unit_purchase_default && $material->unit_purchase_default !== $material->unit_stock) {
            $units[] = $material->unit_purchase_default;
        }

        $conversionUnits = UnitConversion::query()
            ->where('material_id', $material->id)
            ->pluck('from_unit')
            ->toArray();

        return array_values(array_unique(array_merge($units, $conversionUnits)));
    }
}
