<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lastdino\ProcurementFlow\Support\Tables;

class StockMovement extends Model
{
    protected $fillable = [
        'material_id', 'lot_id', 'type', 'source_type', 'source_id', 'qty_base', 'unit', 'occurred_at', 'reason',
        'causer_type', 'causer_id',
    ];

    public function getTable()
    {
        return Tables::name('stock_movements');
    }

    protected function casts(): array
    {
        return [
            'qty_base' => 'decimal:6',
            'occurred_at' => 'datetime',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(MaterialLot::class, 'lot_id');
    }

    public function causer(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }
}
