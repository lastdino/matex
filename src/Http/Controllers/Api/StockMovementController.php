<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Lastdino\ProcurementFlow\Models\Material;
use Lastdino\ProcurementFlow\Models\MaterialLot;
use Lastdino\ProcurementFlow\Models\StockMovement;

class StockMovementController extends Controller
{
    /**
     * Stock In (入庫登録)
     */
    public function stockIn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku' => 'required|string',
            'lot_no' => 'required|string',
            'qty' => 'required|numeric|min:0.000001',
            'reason' => 'nullable|string',
            'occurred_at' => 'nullable|date',
        ]);

        $material = Material::where('sku', $validated['sku'])->first();

        if (! $material) {
            return response()->json(['message' => 'Material not found for SKU: '.$validated['sku']], 404);
        }

        return DB::transaction(function () use ($material, $validated, $request) {
            $lot = MaterialLot::firstOrCreate(
                ['material_id' => $material->id, 'lot_no' => $validated['lot_no']],
                ['qty_on_hand' => 0]
            );

            $qty = (float) $validated['qty'];

            // Create Stock Movement
            StockMovement::create([
                'material_id' => $material->id,
                'lot_id' => $lot->id,
                'type' => 'in',
                'source_type' => 'api',
                'qty_base' => $qty,
                'unit' => $material->unit_stock,
                'occurred_at' => $validated['occurred_at'] ?? now(),
                'reason' => $validated['reason'] ?? 'API Stock In',
                'causer_type' => $request->user() ? get_class($request->user()) : null,
                'causer_id' => $request->user() ? $request->user()->getKey() : null,
            ]);

            // Update Lot Qty
            $lot->increment('qty_on_hand', $qty);

            // Update Material Current Stock
            $material->increment('current_stock', $qty);

            return response()->json([
                'message' => 'Stock in recorded successfully',
                'sku' => $material->sku,
                'lot_no' => $lot->lot_no,
                'new_lot_qty' => $lot->qty_on_hand,
                'new_material_stock' => $material->current_stock,
            ]);
        });
    }

    /**
     * Stock Out (出庫登録)
     */
    public function stockOut(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku' => 'required|string',
            'lot_no' => 'required|string',
            'qty' => 'required|numeric|min:0.000001',
            'reason' => 'nullable|string',
            'occurred_at' => 'nullable|date',
        ]);

        $material = Material::where('sku', $validated['sku'])->first();

        if (! $material) {
            return response()->json(['message' => 'Material not found for SKU: '.$validated['sku']], 404);
        }

        $lot = MaterialLot::where('material_id', $material->id)
            ->where('lot_no', $validated['lot_no'])
            ->first();

        if (! $lot) {
            return response()->json(['message' => 'Lot not found: '.$validated['lot_no'].' for SKU: '.$validated['sku']], 404);
        }

        $qty = (float) $validated['qty'];

        if ($lot->qty_on_hand < $qty) {
            return response()->json([
                'message' => 'Insufficient stock in lot',
                'current_lot_qty' => $lot->qty_on_hand,
                'requested_qty' => $qty,
            ], 422);
        }

        return DB::transaction(function () use ($material, $lot, $qty, $validated, $request) {
            // Create Stock Movement
            StockMovement::create([
                'material_id' => $material->id,
                'lot_id' => $lot->id,
                'type' => 'out',
                'source_type' => 'api',
                'qty_base' => $qty,
                'unit' => $material->unit_stock,
                'occurred_at' => $validated['occurred_at'] ?? now(),
                'reason' => $validated['reason'] ?? 'API Stock Out',
                'causer_type' => $request->user() ? get_class($request->user()) : null,
                'causer_id' => $request->user() ? $request->user()->getKey() : null,
            ]);

            // Update Lot Qty
            $lot->decrement('qty_on_hand', $qty);

            // Update Material Current Stock
            $material->decrement('current_stock', $qty);

            return response()->json([
                'message' => 'Stock out recorded successfully',
                'sku' => $material->sku,
                'lot_no' => $lot->lot_no,
                'new_lot_qty' => $lot->qty_on_hand,
                'new_material_stock' => $material->current_stock,
            ]);
        });
    }
}
