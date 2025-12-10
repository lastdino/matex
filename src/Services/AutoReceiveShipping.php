<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Services;

use Illuminate\Support\Facades\DB;
use Lastdino\ProcurementFlow\Models\Receiving;
use Lastdino\ProcurementFlow\Models\ReceivingItem;
use Lastdino\ProcurementFlow\Models\PurchaseOrderItem;

class AutoReceiveShipping
{
    /**
     * Check completion for items of the PO related to the given receiving and
     * auto-create shipping receiving items "per item" based on shipping_for_item_id linkage.
     */
    public static function handleByReceiving(Receiving $receiving): void
    {
        $receiving->loadMissing(['purchaseOrder.receivings', 'purchaseOrder.items.receivingItems.receiving', 'purchaseOrder.items.shippingCharges']);
        $po = $receiving->purchaseOrder;
        if ($po === null) {
            return;
        }

        // For each non-shipping item that is now fully received, auto-receive its linked shipping charges.
        $items = $po->items;
        $nonShippingItems = $items->filter(fn (PurchaseOrderItem $i) => (string) ($i->unit_purchase ?? '') !== 'shipping');

        foreach ($nonShippingItems as $item) {
            $ordered = (float) ($item->qty_ordered ?? 0);
            if ($ordered <= 0) {
                continue; // nothing to receive
            }

            $receivedSum = (float) ($item->receivingItems->sum(fn ($ri) => (float) ($ri->qty_received ?? 0)));
            if ($receivedSum + 1e-9 < $ordered) {
                continue; // this item not yet complete
            }

            // Determine this item's last received_at
            $lastReceivedAt = null;
            foreach ($item->receivingItems as $ri) {
                $d = $ri->receiving?->received_at;
                if ($d !== null) {
                    $lastReceivedAt = $lastReceivedAt === null || $d > $lastReceivedAt ? $d : $lastReceivedAt;
                }
            }
            if ($lastReceivedAt === null) {
                $lastReceivedAt = now();
            }

            // For each shipping charge linked to this item, create receiving if not exists
            foreach ($item->shippingCharges as $shippingItem) {
                if ($shippingItem->receivingItems()->exists()) {
                    continue; // already created
                }

                DB::transaction(function () use ($po, $receiving, $shippingItem, $lastReceivedAt): void {
                    $newReceiving = Receiving::query()->create([
                        'purchase_order_id' => (int) $po->getKey(),
                        'received_at' => $lastReceivedAt,
                        'reference_number' => null,
                        'notes' => null,
                        'created_by' => $receiving->created_by,
                    ]);

                    ReceivingItem::query()->create([
                        'receiving_id' => (int) $newReceiving->getKey(),
                        'purchase_order_item_id' => (int) $shippingItem->getKey(),
                        'material_id' => $shippingItem->material_id,
                        'unit_purchase' => (string) ($shippingItem->unit_purchase ?? 'shipping'),
                        'qty_received' => 1,
                        'qty_base' => 1,
                    ]);
                });
            }
        }
    }
}
