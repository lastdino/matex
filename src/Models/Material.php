<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lastdino\ProcurementFlow\Support\Tables;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Material extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'sku', 'name', 'tax_code', 'unit_stock', 'unit_purchase_default', 'min_stock', 'max_stock', 'category_id', 'current_stock', 'preferred_supplier_id',
        'manufacturer_name', 'applicable_regulation', 'ghs_mark', 'protective_equipment', 'unit_price',
        // 発注制約
        'moq', 'pack_size',
        // shipping
        'separate_shipping', 'shipping_fee_per_order',
        // is chemical
        'is_chemical',
        'cas_no', 'physical_state', 'ghs_hazard_details', 'specified_quantity', 'emergency_contact', 'disposal_method',
        // activation
        'is_active',
        // monox integration
        'sync_to_monox',
    ];

    public function getTable()
    {
        return Tables::name('materials');
    }

    protected function casts(): array
    {
        return [
            'min_stock' => 'decimal:6',
            'max_stock' => 'decimal:6',
            'current_stock' => 'decimal:6',
            'unit_price' => 'decimal:2',
            'moq' => 'decimal:6',
            'pack_size' => 'decimal:6',
            'separate_shipping' => 'boolean',
            'shipping_fee_per_order' => 'decimal:2',
            'is_chemical' => 'boolean',
            'specified_quantity' => 'decimal:6',
            'is_active' => 'boolean',
            'sync_to_monox' => 'boolean',
        ];
    }

    /**
     * Scope: only active materials.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MaterialCategory::class, 'category_id');
    }

    public function preferredSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'preferred_supplier_id');
    }

    public function lots(): HasMany
    {
        return $this->hasMany(MaterialLot::class, 'material_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'material_id');
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(UnitConversion::class, 'material_id');
    }

    public function riskAssessments(): HasMany
    {
        return $this->hasMany(MaterialRiskAssessment::class, 'material_id');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(MaterialInspection::class, 'material_id');
    }

    /**
     * Register media collections for this model.
     */
    public function registerMediaCollections(): void
    {
        // SDS (Safety Data Sheet) – PDF only, single file per material
        $this->addMediaCollection('sds')
            ->acceptsMimeTypes(['application/pdf'])
            ->useDisk('local')
            ->singleFile();
    }

    /**
     * Parse the ghs_mark string into an array of keys.
     */
    public function ghsMarkList(): array
    {
        $raw = (string) ($this->ghs_mark ?? '');
        if ($raw === '') {
            return [];
        }
        /** @var array<int, string> $tokens */
        $tokens = array_map('trim', preg_split('/[\s,|]+/', $raw) ?: []);

        return array_values(array_filter($tokens, static fn (string $t): bool => $t !== ''));
    }

    /**
     * Build public URLs for GHS images (deprecated - using icons instead).
     *
     * @return array<int, string>
     */
    public function ghsImageUrls(): array
    {
        return [];
    }

    /**
     * Get the Flux icon names for the GHS marks.
     *
     * @return array<int, string>
     */
    public function ghsIconNames(): array
    {
        return array_map(function (string $key) {
            // Convert GHS01 to ghs-01
            return strtolower(preg_replace('/(GHS)(\d+)/i', '$1-$2', $key) ?? $key);
        }, $this->ghsMarkList());
    }

    /**
     * Get the default GHS keys.
     *
     * @return array<int, string>
     */
    public static function defaultGhsKeys(): array
    {
        return [
            'GHS01', 'GHS02', 'GHS03', 'GHS04', 'GHS05',
            'GHS06', 'GHS07', 'GHS08', 'GHS09',
        ];
    }
}
