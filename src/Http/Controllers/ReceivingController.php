<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Lastdino\ProcurementFlow\Actions\Receiving\ReceivePurchaseOrderAction;
use Lastdino\ProcurementFlow\Enums\PurchaseOrderStatus;
use Lastdino\ProcurementFlow\Http\Requests\StoreReceivingRequest;
use Lastdino\ProcurementFlow\Models\Material;
use Lastdino\ProcurementFlow\Models\PurchaseOrder;
use Lastdino\ProcurementFlow\Models\PurchaseOrderItem;
use Lastdino\ProcurementFlow\Models\Receiving;
use Lastdino\ProcurementFlow\Models\ReceivingItem;
// use Lastdino\ProcurementFlow\Models\StockIn; // stock_ins abolished
use Lastdino\ProcurementFlow\Models\MaterialLot;
use Lastdino\ProcurementFlow\Models\StockMovement;
use Lastdino\ProcurementFlow\Services\UnitConversionService;

class ReceivingController extends Controller
{
    public function store(PurchaseOrder $po, StoreReceivingRequest $request, UnitConversionService $conversion, ReceivePurchaseOrderAction $action): JsonResponse
    {
        $receiving = $action->byItems($po, $request->validated());

        return response()->json($receiving, 201);
    }
}
