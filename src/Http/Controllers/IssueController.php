<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Lastdino\ProcurementFlow\Http\Requests\StoreIssueRequest;
use Lastdino\ProcurementFlow\Models\Material;
use Lastdino\ProcurementFlow\Models\MaterialLot;
use Lastdino\ProcurementFlow\Models\StockMovement;
use Lastdino\ProcurementFlow\Services\UnitConversionService;

class IssueController extends Controller
{
    public function store(Material $material, StoreIssueRequest $request, UnitConversionService $conversion): JsonResponse
    {
        $data = $request->validated();
        $occurredAt = $data['occurred_at'] ?? CarbonImmutable::now()->toISOString();
        $reason = $data['reason'] ?? 'issue';

        $result = DB::transaction(function () use ($material, $data, $occurredAt, $reason, $conversion) {
            $causerType = null;
            $causerId = null;
            if (Auth::check()) {
                $user = Auth::user();
                $causerType = $user ? get_class($user) : null;
                $causerId = $user?->getAuthIdentifier();
            }
            $responses = [];
            foreach ($data['items'] as $line) {
                $fromUnit = $line['unit'] ?? $material->unit_stock;
                $factor = $conversion->factor($material, $fromUnit, $material->unit_stock);
                $qtyBase = (float) $line['qty'] * (float) $factor;

                if ((bool) ($material->manage_by_lot ?? false)) {
                    // Require lot
                    $lot = null;
                    if (! empty($line['lot_id'] ?? null)) {
                        $lot = MaterialLot::query()->where('material_id', $material->id)->whereKey((int) $line['lot_id'])->first();
                    } elseif (! empty($line['lot_no'] ?? null)) {
                        $lot = MaterialLot::query()->where('material_id', $material->id)->where('lot_no', (string) $line['lot_no'])->first();
                    }
                    abort_if(! $lot, 422, 'lot_no (or lot_id) is required and must exist for lot-managed materials.');
                    // Stock check
                    abort_if($qtyBase > (float) $lot->qty_on_hand, 422, 'Insufficient lot stock.');

                    // Decrement lot and record movement
                    $lot->decrement('qty_on_hand', $qtyBase);
                    StockMovement::create([
                        'material_id' => $material->id,
                        'lot_id' => $lot->id,
                        'type' => 'out',
                        'source_type' => $line['source_type'] ?? null,
                        'source_id' => $line['source_id'] ?? null,
                        'qty_base' => $qtyBase,
                        'unit' => $material->unit_stock,
                        'occurred_at' => $occurredAt,
                        'reason' => $reason,
                        'causer_type' => $causerType,
                        'causer_id' => $causerId,
                    ]);

                    $responses[] = [
                        'lot_id' => $lot->id,
                        'lot_no' => $lot->lot_no,
                        'qty_base' => $qtyBase,
                    ];
                } else {
                    // Non-lot material: only total check if current_stock is tracked
                    if (! is_null($material->current_stock)) {
                        abort_if($qtyBase > (float) $material->current_stock, 422, 'Insufficient material stock.');
                    }

                    StockMovement::create([
                        'material_id' => $material->id,
                        'lot_id' => null,
                        'type' => 'out',
                        'source_type' => $line['source_type'] ?? null,
                        'source_id' => $line['source_id'] ?? null,
                        'qty_base' => $qtyBase,
                        'unit' => $material->unit_stock,
                        'occurred_at' => $occurredAt,
                        'reason' => $reason,
                        'causer_type' => $causerType,
                        'causer_id' => $causerId,
                    ]);

                    $responses[] = [
                        'qty_base' => $qtyBase,
                    ];
                }

                // Always sync current_stock if present
                if (! is_null($material->current_stock)) {
                    $material->decrement('current_stock', $qtyBase);
                }
            }

            return [
                'material_id' => $material->id,
                'occurred_at' => $occurredAt,
                'items' => $responses,
            ];
        });

        return response()->json($result, 201);
    }
}
