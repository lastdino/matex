<?php

declare(strict_types=1);

namespace Lastdino\Matex\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Lastdino\Matex\Models\PurchaseOrder;
use Lastdino\Matex\Models\PurchaseOrderItem;
use Lastdino\Matex\Models\Receiving;
use Lastdino\Matex\Models\ReceivingItem;

class PurchaseOrderApiController extends Controller
{
    /**
     * Purchase order history (発注履歴)
     */
    public function history(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'date_type' => 'nullable|string|in:receiving,issue',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $from = $validated['start_date'];
        $to = $validated['end_date'];
        $dateType = $validated['date_type'] ?? 'receiving';
        $limit = (int) ($validated['limit'] ?? 50);

        if ($dateType === 'issue') {
            $items = PurchaseOrderItem::query()
                ->join((new PurchaseOrder)->getTable().' as po', 'po.id', '=', (new PurchaseOrderItem)->getTable().'.purchase_order_id')
                ->whereDate('po.issue_date', '>=', $from)
                ->whereDate('po.issue_date', '<=', $to)
                ->with([
                    'purchaseOrder:id,po_number,supplier_id,department_id,issue_date',
                    'purchaseOrder.supplier:id,name',
                    'purchaseOrder.department:id,name',
                    'material:id,sku,name,manufacturer_name',
                    'receivingItems.receiving:id,received_at',
                    'optionValues.group:id,name',
                    'optionValues.option:id,name',
                ])
                ->orderBy('po.issue_date', 'asc')
                ->select((new PurchaseOrderItem)->getTable().'.*')
                ->limit($limit)
                ->get();

            $data = $items->map(fn ($item) => $this->formatPurchaseOrderItem($item));
        } else {
            $items = ReceivingItem::query()
                ->join((new Receiving)->getTable().' as r', 'r.id', '=', (new ReceivingItem)->getTable().'.receiving_id')
                ->whereDate('r.received_at', '>=', $from)
                ->whereDate('r.received_at', '<=', $to)
                ->with([
                    'receiving:id,purchase_order_id,received_at,notes',
                    'purchaseOrderItem:id,purchase_order_id,material_id,qty_ordered,price_unit,note,description,manufacturer',
                    'purchaseOrderItem.purchaseOrder:id,po_number,supplier_id,department_id,issue_date',
                    'purchaseOrderItem.material:id,sku,name,manufacturer_name',
                    'purchaseOrderItem.purchaseOrder.supplier:id,name',
                    'purchaseOrderItem.purchaseOrder.department:id,name',
                    'purchaseOrderItem.optionValues.option:id,name',
                    'purchaseOrderItem.optionValues.group:id,name',
                ])
                ->orderBy('r.received_at', 'asc')
                ->select((new ReceivingItem)->getTable().'.*', 'r.received_at')
                ->limit($limit)
                ->get();

            $data = $items->map(fn ($item) => $this->formatReceivingItem($item));
        }

        return response()->json([
            'data' => $data,
        ]);
    }

    private function formatPurchaseOrderItem(PurchaseOrderItem $item): array
    {
        $po = $item->purchaseOrder;
        $receivedAt = $item->receivingItems->first()?->receiving?->received_at?->toDateString();
        $qtyReceived = $item->receivingItems->sum('qty_ordered'); // Adjusted based on context

        return [
            'po_number' => $po?->po_number,
            'supplier_name' => $po?->supplier?->name,
            'department_name' => $po?->department?->name,
            'issue_date' => $po?->issue_date?->toDateString(),
            'received_at' => $receivedAt,
            'sku' => $item->material?->sku,
            'material_name' => $item->material?->name,
            'manufacturer' => $item->manufacturer ?: $item->material?->manufacturer_name,
            'options' => $item->optionValues->map(fn ($ov) => [
                'group' => $ov->group?->name,
                'value' => $ov->option?->name,
            ]),
            'qty_ordered' => (float) $item->qty_ordered,
            'qty_received' => (float) $item->receivingItems->sum('qty_received'),
            'unit_price' => (float) $item->price_unit,
            'amount' => (float) $item->line_total,
            'note' => $item->note,
        ];
    }

    private function formatReceivingItem(ReceivingItem $item): array
    {
        $poi = $item->purchaseOrderItem;
        $po = $poi?->purchaseOrder;

        return [
            'po_number' => $po?->po_number,
            'supplier_name' => $po?->supplier?->name,
            'department_name' => $po?->department?->name,
            'issue_date' => $po?->issue_date?->toDateString(),
            'received_at' => $item->receiving?->received_at?->toDateString(),
            'sku' => $poi?->material?->sku,
            'material_name' => $poi?->material?->name,
            'manufacturer' => $poi?->manufacturer ?: $poi?->material?->manufacturer_name,
            'options' => $poi?->optionValues->map(fn ($ov) => [
                'group' => $ov->group?->name,
                'value' => $ov->option?->name,
            ]) ?? [],
            'qty_ordered' => (float) ($poi?->qty_ordered ?? 0),
            'qty_received' => (float) $item->qty_received,
            'unit_price' => (float) ($poi?->price_unit ?? 0),
            'amount' => (float) (($poi?->price_unit ?? 0) * $item->qty_received),
            'note' => $poi?->note,
        ];
    }
}
