<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lastdino\ProcurementFlow\Support\Tables;

class MaterialLot extends Model
{
    protected $fillable = [
        'material_id', 'lot_no', 'qty_on_hand', 'unit', 'received_at', 'mfg_date', 'expiry_date', 'status', 'storage_location_id', 'barcode', 'notes',
        'supplier_id', 'purchase_order_id',
    ];

    public function getTable()
    {
        return Tables::name('material_lots');
    }

    protected function casts(): array
    {
        return [
            'qty_on_hand' => 'decimal:6',
            'received_at' => 'datetime',
            'mfg_date' => 'date',
            'expiry_date' => 'date',
            'storage_location_id' => 'integer',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    public function storageLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'storage_location_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'lot_id');
    }
}
