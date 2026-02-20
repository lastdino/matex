<?php

declare(strict_types=1);

namespace Lastdino\Matex\Domain\PO;

use Lastdino\Matex\Models\Material;
use Lastdino\Matex\Models\PurchaseOrder;
use Lastdino\Matex\Models\PurchaseOrderItem;
use Lastdino\Matex\Services\UnitConversionService;

class PurchaseOrderCompletionService
{
    public function isFullyReceived(PurchaseOrder $po, UnitConversionService $conv): bool
    {
        $items = $po->items()->where('unit_purchase', '!=', 'shipping')->get();

        return $items->every(function (PurchaseOrderItem $item) use ($conv) {
            if (! is_null($item->material_id)) {
                /** @var Material|null $mat */
                $mat = Material::query()->find($item->material_id);
                if (! $mat) {
                    return false;
                }
                $effectiveOrdered = max((float) ($item->qty_ordered ?? 0) - (float) ($item->qty_canceled ?? 0), 0.0);
                $orderedBase = $effectiveOrdered * (float) $conv->factor($mat, $item->unit_purchase, $mat->unit_stock);
                $receivedBase = (float) $item->receivingItems()->sum('qty_base');

                return $receivedBase >= $orderedBase - 1e-9;
            }

            $orderedBase = max((float) ($item->qty_ordered ?? 0) - (float) ($item->qty_canceled ?? 0), 0.0);
            $receivedBase = (float) $item->receivingItems()->sum('qty_base');

            return $receivedBase >= $orderedBase - 1e-9;
        });
    }
}
