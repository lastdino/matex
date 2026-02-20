<?php

declare(strict_types=1);

namespace Lastdino\Matex\Observers;

use Lastdino\Matex\Models\ReceivingItem;
use Lastdino\Matex\Services\AutoReceiveShipping;

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
