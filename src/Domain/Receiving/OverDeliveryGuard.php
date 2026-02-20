<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Domain\Receiving;

use Lastdino\ProcurementFlow\Models\Material;
use Lastdino\ProcurementFlow\Models\PurchaseOrderItem;
use Lastdino\ProcurementFlow\Services\UnitConversionService;

class OverDeliveryGuard
{
    public function assertNotExceededAdhoc(PurchaseOrderItem $poi, float $qtyBase): void
    {
        $ordered = max((float) $poi->qty_ordered - (float) ($poi->qty_canceled ?? 0), 0.0);
        $received = (float) $poi->receivingItems()->sum('qty_base');
        $remaining = $ordered - $received;
        abort_if($qtyBase > max($remaining, 0.0), 422, 'Quantity exceeds remaining amount. Over-delivery must be returned.');
    }

    public function assertNotExceededMaterial(PurchaseOrderItem $poi, Material $material, float $qtyBase, UnitConversionService $conv): void
    {
        $effectiveOrdered = max((float) $poi->qty_ordered - (float) ($poi->qty_canceled ?? 0), 0.0);
        $orderedBase = $effectiveOrdered * (float) $conv->factor($material, $poi->unit_purchase, $material->unit_stock);
        $receivedBase = (float) $poi->receivingItems()->sum('qty_base');
        $remaining = $orderedBase - $receivedBase;
        abort_if($qtyBase > max($remaining, 0.0), 422, 'Quantity exceeds remaining amount. Over-delivery must be returned.');
    }

    public function assertStorageLocationNotExceeded(StorageLocation $location, Material $material, float $qtyBase): void
    {
        if (! $material->is_chemical || (float) $material->specified_quantity <= 0) {
            return;
        }

        if ($location->max_specified_quantity_ratio === null || (float) $location->max_specified_quantity_ratio <= 0) {
            return;
        }

        $currentRatio = $location->currentSpecifiedQuantityRatio();
        $newRatio = $qtyBase / (float) $material->specified_quantity;
        $totalRatio = $currentRatio + $newRatio;

        if ($totalRatio > (float) $location->max_specified_quantity_ratio) {
            abort(422, "保管場所「{$location->name}」の指定数量倍率（{$location->max_specified_quantity_ratio}）を超過します。現在の合計: " . number_format($totalRatio, 2));
        }
    }
}
