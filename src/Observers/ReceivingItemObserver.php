<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Observers;

use Lastdino\ProcurementFlow\Models\ReceivingItem;
use Lastdino\ProcurementFlow\Services\AutoReceiveShipping;

class ReceivingItemObserver
{
    public function created(ReceivingItem $receivingItem): void
    {
        $receiving = $receivingItem->receiving()->with(['purchaseOrder.receivings', 'purchaseOrder.items.receivingItems'])->first();
        if ($receiving) {
            AutoReceiveShipping::handleByReceiving($receiving);
        }
    }
}
