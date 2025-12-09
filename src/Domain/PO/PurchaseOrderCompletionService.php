<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Domain\PO;

use Lastdino\ProcurementFlow\Models\Material;
use Lastdino\ProcurementFlow\Models\PurchaseOrder;
use Lastdino\ProcurementFlow\Models\PurchaseOrderItem;
use Lastdino\ProcurementFlow\Services\UnitConversionService;

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
                $orderedBase = (float) $item->qty_ordered * (float) $conv->factor($mat, $item->unit_purchase, $mat->unit_stock);
                $receivedBase = (float) $item->receivingItems()->sum('qty_base');
                return $receivedBase >= $orderedBase - 1e-9;
            }

            $orderedBase = (float) $item->qty_ordered;
            $receivedBase = (float) $item->receivingItems()->sum('qty_base');
            return $receivedBase >= $orderedBase - 1e-9;
        });
    }
}
