<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Lastdino\ProcurementFlow\Http\Requests\StorePurchaseOrderRequest;
use Lastdino\ProcurementFlow\Models\Material;
use Lastdino\ProcurementFlow\Models\PurchaseOrder;
use Lastdino\ProcurementFlow\Models\PurchaseOrderItem;
use Lastdino\ProcurementFlow\Support\Settings;

class PurchaseOrderController extends Controller
{
    public function index(): JsonResponse
    {
        $pos = PurchaseOrder::query()->with('supplier')->latest('id')->paginate(15);
        return response()->json($pos);
    }

    public function show(PurchaseOrder $purchase_order): JsonResponse
    {
        $purchase_order->load(['supplier', 'items.material']);
        return response()->json($purchase_order);
    }

    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        $data = $request->validated();

        $po = DB::transaction(function () use ($data) {
            $po = PurchaseOrder::create([
                'supplier_id' => $data['supplier_id'],
                'status' => 'draft',
                'expected_date' => $data['expected_date'] ?? null,
                'subtotal' => 0,
                'tax' => 0,
                'total' => 0,
                // 納品先：明示指定がなければPDF設定の値を初期値として保存
                'delivery_location' => (string) ($data['delivery_location'] ?? (Settings::pdf()['delivery_location'] ?? '')),
            ]);

            $subtotal = 0.0;
            $tax = 0.0;

            $expectedDate = isset($data['expected_date']) && $data['expected_date']
                ? Carbon::parse($data['expected_date'])
                : null;
            $taxSet = Settings::itemTax($expectedDate);

            // 1) 通常のアイテム行
            $materialIdsInOrder = [];
            foreach ($data['items'] as $line) {
                $materialId = $line['material_id'] ?? null;
                $lineTotal = (float) $line['qty_ordered'] * (float) $line['price_unit'];

                // Derive tax rate from material tax_code if not provided
                $lineTaxRate = null;
                if (array_key_exists('tax_rate', $line) && $line['tax_rate'] !== null && $line['tax_rate'] !== '') {
                    $lineTaxRate = (float) $line['tax_rate'];
                } else {
                    $material = null;
                    if (! is_null($materialId)) {
                        $material = Material::find($materialId);
                    }
                    $lineTaxRate = $this->resolveMaterialTaxRate($material, $taxSet);
                }
                $lineTax = $lineTotal * $lineTaxRate;

                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'material_id' => $materialId,
                    'description' => $line['description'] ?? null,
                    'unit_purchase' => $line['unit_purchase'],
                    'qty_ordered' => $line['qty_ordered'],
                    'price_unit' => $line['price_unit'],
                    'tax_rate' => $lineTaxRate,
                    'line_total' => $lineTotal,
                    'desired_date' => $line['desired_date'] ?? null,
                    'expected_date' => $line['expected_date'] ?? null,
                ]);

                $subtotal += $lineTotal;
                $tax += $lineTax;

                if (! is_null($materialId)) {
                    $materialIdsInOrder[(int) $materialId] = true; // unique set
                }
            }

            // 2) 送料アイテム行（対象資材ごとに1行）
            if (! empty($materialIdsInOrder)) {
                /** @var \Illuminate\Support\Collection<int, \Lastdino\ProcurementFlow\Models\Material> $materials */
                $materials = \Lastdino\ProcurementFlow\Models\Material::query()
                    ->whereIn('id', array_keys($materialIdsInOrder))
                    ->get();

                $shipping = Settings::shipping();
                $shippingTaxable = (bool) $shipping['taxable'];
                $shippingTaxRate = $shippingTaxable ? (float) $shipping['tax_rate'] : 0.0;

                foreach ($materials as $mat) {
                    $separate = (bool) ($mat->getAttribute('separate_shipping') ?? false);
                    $fee = (float) ($mat->getAttribute('shipping_fee_per_order') ?? 0);
                    if (! $separate || $fee <= 0) {
                        continue;
                    }

                    $desc = '送料（' . (string) ($mat->getAttribute('name') ?? '対象資材') . '）';

                    PurchaseOrderItem::create([
                        'purchase_order_id' => $po->id,
                        'material_id' => null, // 送料は独立行として扱う
                        'description' => $desc,
                        'unit_purchase' => 'shipping',
                        'qty_ordered' => 1,
                        'price_unit' => $fee,
                        'tax_rate' => $shippingTaxRate,
                        'line_total' => $fee,
                        'desired_date' => null,
                        'expected_date' => null,
                    ]);

                    $subtotal += $fee;
                    $tax += ($fee * $shippingTaxRate);
                }
            }

            $total = $subtotal + $tax;
            $po->update([
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
            ]);

            return $po->fresh(['items']);
        });

        return response()->json($po, 201);
    }

    /**
     * 現在（または予定日）に有効な商品税セットを返す。
     * 返り値: ['default_rate' => float, 'rates' => array<string,float>]
     */
    protected function resolveCurrentItemTaxSet(?Carbon $at): array
    {
        $cfg = (array) config('procurement-flow.item_tax', []);
        $default = (float) ($cfg['default_rate'] ?? 0.10);
        $rates = (array) ($cfg['rates'] ?? []);
        $schedule = (array) ($cfg['schedule'] ?? []);

        if ($at && ! empty($schedule)) {
            foreach ($schedule as $entry) {
                $from = $entry['effective_from'] ?? null;
                if ($from && $at->greaterThanOrEqualTo(Carbon::parse($from))) {
                    $default = (float) ($entry['default_rate'] ?? $default);
                    $rates = array_merge($rates, (array) ($entry['rates'] ?? []));
                }
            }
        }

        return ['default_rate' => $default, 'rates' => $rates];
    }

    /**
     * 資材の tax_code に応じて税率を返す。該当コードが無い場合はデフォルト。
     */
    protected function resolveMaterialTaxRate(?Material $material, array $taxSet): float
    {
        $code = $material ? (string) ($material->getAttribute('tax_code') ?? 'standard') : 'standard';
        $default = (float) ($taxSet['default_rate'] ?? 0.10);
        $rates = (array) ($taxSet['rates'] ?? []);
        return match ($code) {
            'reduced' => (float) ($rates['reduced'] ?? $default),
            default => $default,
        };
    }
}
