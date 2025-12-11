<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Lastdino\ProcurementFlow\Actions\Receiving\ReceivePurchaseOrderAction;
use Lastdino\ProcurementFlow\Enums\PurchaseOrderStatus;
use Lastdino\ProcurementFlow\Http\Requests\StoreReceivingByScanRequest;
use Lastdino\ProcurementFlow\Models\Material;
use Lastdino\ProcurementFlow\Models\PurchaseOrder;
use Lastdino\ProcurementFlow\Models\PurchaseOrderItem;
use Lastdino\ProcurementFlow\Models\Receiving;
use Lastdino\ProcurementFlow\Models\ReceivingItem;
// use Lastdino\ProcurementFlow\Models\StockIn; // stock_ins abolished
use Lastdino\ProcurementFlow\Models\MaterialLot;
use Lastdino\ProcurementFlow\Models\StockMovement;
use Lastdino\ProcurementFlow\Services\UnitConversionService;

class ScanReceivingController extends Controller
{
    public function info(string $token, UnitConversionService $conversion): JsonResponse
    {
        /** @var PurchaseOrderItem|null $poi */
        $poi = PurchaseOrderItem::query()->whereScanToken($token)->with(['purchaseOrder', 'material'])->first();
        abort_if(! $poi, 404);

        /** @var PurchaseOrder $po */
        $po = $poi->purchaseOrder;
        abort_if(! in_array($po->status, [PurchaseOrderStatus::Issued, PurchaseOrderStatus::Receiving], true), 422, 'PO is not receivable.');

        // Shipping lines are not receivable
        abort_if($poi->unit_purchase === 'shipping', 422, 'Shipping line is not receivable.');

        /** @var Material|null $material */
        $material = $poi->material;

        if (! $material) {
            // Ad-hoc line: no conversion. Provide minimal info without material.
            $orderedBase = (float) $poi->qty_ordered - (float) ($poi->qty_canceled ?? 0);
            $receivedBase = (float) $poi->receivingItems()->sum('qty_base');
            $remainingBase = max($orderedBase - $receivedBase, 0.0);

            abort_if($remainingBase <= 0.0, 422, 'This line has no receivable quantity (possibly canceled).');

            return response()->json([
                'po_id' => $po->getKey(),
                'po_number' => $po->po_number,
                'po_status' => $po->status->value,
                'item_id' => $poi->getKey(),
                'material' => null,
                'unit_purchase' => $poi->unit_purchase,
                'qty_ordered' => (float) $poi->qty_ordered,
                'remaining_base' => $remainingBase,
            ]);
        }

        // Normal material line
        $effectiveOrdered = max((float) $poi->qty_ordered - (float) ($poi->qty_canceled ?? 0), 0.0);
        $orderedBase = $effectiveOrdered * (float) $conversion->factor($material, $poi->unit_purchase, $material->unit_stock);
        $receivedBase = (float) $poi->receivingItems()->sum('qty_base');
        $remainingBase = max($orderedBase - $receivedBase, 0.0);

        abort_if($remainingBase <= 0.0, 422, 'This line has no receivable quantity (possibly canceled).');

        return response()->json([
            'po_id' => $po->getKey(),
            'po_number' => $po->po_number,
            'po_status' => $po->status->value,
            'item_id' => $poi->getKey(),
            'material' => [
                'id' => $material->getKey(),
                'sku' => $material->sku,
                'name' => $material->name,
                'unit_stock' => $material->unit_stock,
            ],
            'unit_purchase' => $poi->unit_purchase,
            'qty_ordered' => (float) $poi->qty_ordered,
            'remaining_base' => $remainingBase,
        ]);
    }

    public function store(StoreReceivingByScanRequest $request, UnitConversionService $conversion, ReceivePurchaseOrderAction $action): JsonResponse
    {
        $receiving = $action->byScan($request->validated());

        return response()->json($receiving, 201);
    }
}
