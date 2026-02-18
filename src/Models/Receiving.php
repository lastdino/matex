<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lastdino\ProcurementFlow\Support\Tables;

class Receiving extends Model
{
    protected $fillable = [
        'purchase_order_id', 'received_at', 'reference_number', 'notes', 'created_by',
    ];

    public function getTable()
    {
        return Tables::name('receivings');
    }

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReceivingItem::class, 'receiving_id');
    }
}
