<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lastdino\ProcurementFlow\Support\Tables;

class PurchaseOrderItemOptionValue extends Model
{
    protected $fillable = [
        'purchase_order_item_id', 'group_id', 'option_id',
    ];

    public function getTable()
    {
        return Tables::name('po_item_option_values');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(OptionGroup::class, 'group_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'option_id');
    }
}
