<?php

declare(strict_types=1);

namespace Lastdino\Matex\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lastdino\Matex\Support\Tables;

class ReceivingItem extends Model
{
    protected $fillable = [
        'receiving_id', 'purchase_order_item_id', 'material_id', 'unit_purchase', 'qty_received', 'qty_base',
    ];

    public function getTable()
    {
        return Tables::name('receiving_items');
    }

    protected function casts(): array
    {
        return [
            'qty_received' => 'decimal:6',
            'qty_base' => 'decimal:6',
        ];
    }

    public function receiving(): BelongsTo
    {
        return $this->belongsTo(Receiving::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
