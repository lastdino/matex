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
        $ordered = (float) $poi->qty_ordered;
        $received = (float) $poi->receivingItems()->sum('qty_base');
        $remaining = $ordered - $received;
        abort_if($qtyBase > max($remaining, 0.0), 422, 'Quantity exceeds remaining amount. Over-delivery must be returned.');
    }

    public function assertNotExceededMaterial(PurchaseOrderItem $poi, Material $material, float $qtyBase, UnitConversionService $conv): void
    {
        $orderedBase = (float) $poi->qty_ordered * (float) $conv->factor($material, $poi->unit_purchase, $material->unit_stock);
        $receivedBase = (float) $poi->receivingItems()->sum('qty_base');
        $remaining = $orderedBase - $receivedBase;
        abort_if($qtyBase > max($remaining, 0.0), 422, 'Quantity exceeds remaining amount. Over-delivery must be returned.');
    }
}
