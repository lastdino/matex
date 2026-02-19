<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lastdino\ProcurementFlow\Support\Tables;

class StorageLocation extends Model
{
    protected $fillable = [
        'name',
        'fire_service_law_category',
        'max_specified_quantity_ratio',
        'description',
        'is_active',
    ];

    public function getTable()
    {
        return Tables::name('storage_locations');
    }

    protected function casts(): array
    {
        return [
            'max_specified_quantity_ratio' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function lots(): HasMany
    {
        return $this->hasMany(MaterialLot::class, 'storage_location_id');
    }

    /**
     * Calculate the current total specified quantity ratio for this location.
     * sum(lot.qty_on_hand / material.specified_quantity)
     */
    public function currentSpecifiedQuantityRatio(): float
    {
        $totalRatio = 0.0;

        $this->lots()->with('material')->get()->each(function (MaterialLot $lot) use (&$totalRatio) {
            $material = $lot->material;
            if ($material && $material->is_chemical && $material->specified_quantity > 0) {
                $totalRatio += (float) $lot->qty_on_hand / (float) $material->specified_quantity;
            }
        });

        return $totalRatio;
    }

    /**
     * Check if this location is over the specified quantity limit.
     */
    public function isOverLimit(): bool
    {
        if ($this->max_specified_quantity_ratio === null || (float) $this->max_specified_quantity_ratio <= 0) {
            return false;
        }

        return $this->currentSpecifiedQuantityRatio() >= (float) $this->max_specified_quantity_ratio;
    }

    /**
     * Get the usage percentage of the specified quantity limit.
     */
    public function limitUsagePercentage(): float
    {
        if ($this->max_specified_quantity_ratio === null || (float) $this->max_specified_quantity_ratio <= 0) {
            return 0.0;
        }

        return ($this->currentSpecifiedQuantityRatio() / (float) $this->max_specified_quantity_ratio) * 100;
    }
}
