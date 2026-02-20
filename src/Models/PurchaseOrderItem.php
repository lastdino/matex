<?php

declare(strict_types=1);

namespace Lastdino\Matex\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lastdino\Matex\Support\Tables;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id', 'material_id', 'scan_token', 'description', 'unit_purchase', 'qty_ordered', 'price_unit', 'tax_rate', 'line_total', 'desired_date', 'expected_date', 'note', 'manufacturer', 'shipping_for_item_id',
    ];

    public function getTable()
    {
        return Tables::name('purchase_order_items');
    }

    protected function casts(): array
    {
        return [
            'qty_ordered' => 'decimal:6',
            'qty_canceled' => 'decimal:6',
            'price_unit' => 'decimal:6',
            'tax_rate' => 'decimal:4',
            'line_total' => 'decimal:2',
            'desired_date' => 'date:Y-m-d',
            'expected_date' => 'date:Y-m-d',
            'canceled_at' => 'datetime',
            'note' => 'string',
            'manufacturer' => 'string',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function receivingItems(): HasMany
    {
        return $this->hasMany(ReceivingItem::class, 'purchase_order_item_id');
    }

    public function optionValues(): HasMany
    {
        return $this->hasMany(PurchaseOrderItemOptionValue::class, 'purchase_order_item_id');
    }

    public function optionValueForGroup(int $groupId): ?PurchaseOrderItemOptionValue
    {
        return $this->optionValues->firstWhere('group_id', $groupId);
    }

    // Shipping relations
    public function shippingTarget(): BelongsTo
    {
        return $this->belongsTo(self::class, 'shipping_for_item_id');
    }

    public function shippingCharges(): HasMany
    {
        return $this->hasMany(self::class, 'shipping_for_item_id');
    }

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            if (empty($item->scan_token)) {
                $item->scan_token = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function scopeWhereScanToken($query, string $token)
    {
        return $query->where('scan_token', $token);
    }
}
