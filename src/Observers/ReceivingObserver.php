<?php

declare(strict_types=1);

namespace Lastdino\Matex\Observers;

use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\DB;
use Lastdino\Matex\Models\PurchaseOrderItem;
use Lastdino\Matex\Models\Receiving;
use Lastdino\Matex\Models\ReceivingItem;

class ReceivingObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * When a receiving is created, check if the PO is fully received (excluding shipping items);
     * if so, auto-create a receiving for shipping items (qty=1) if not already present.
     */
    public function created(Receiving $receiving): void
    {
        $this->maybeAutoReceiveShipping($receiving);
    }

    /**
     * Also check on update (e.g., edits to a receiving) to ensure idempotent behavior.
     */
    public function updated(Receiving $receiving): void
    {
        $this->maybeAutoReceiveShipping($receiving);
    }

    protected function maybeAutoReceiveShipping(Receiving $receiving): void
    {
        // Reload relations we need
        $receiving->loadMissing(['purchaseOrder.receivings', 'purchaseOrder.items.receivingItems']);
        $po = $receiving->purchaseOrder;
        if ($po === null) {
            return;
        }

        $items = $po->items; // PurchaseOrderItem collection

        // Separate shipping and non-shipping items
        $nonShippingItems = $items->filter(function (PurchaseOrderItem $i) {
            return (string) ($i->unit_purchase ?? '') !== 'shipping';
        });
        $shippingItems = $items->filter(function (PurchaseOrderItem $i) {
            return (string) ($i->unit_purchase ?? '') === 'shipping';
        });

        if ($shippingItems->isEmpty()) {
            return; // Nothing to auto receive
        }

        // Check completion for non-shipping items: sum(received) >= ordered
        foreach ($nonShippingItems as $item) {
            $ordered = (float) ($item->qty_ordered ?? 0);
            if ($ordered <= 0) {
                // Treat zero-qty as already complete
                continue;
            }
            $receivedSum = (float) ($item->receivingItems->sum(function ($ri) {
                return (float) ($ri->qty_received ?? 0);
            }));
            // Allow tiny epsilon for float comparisons
            if ($receivedSum + 1e-9 < $ordered) {
                return; // Not yet complete
            }
        }

        // Determine last receiving date for this PO (used as the shipping receiving date)
        $lastReceivedAt = $po->receivings->max('received_at');

        // Idempotency: if all shipping items already have at least one receiving item, do nothing
        $allShippingReceived = $shippingItems->every(function (PurchaseOrderItem $item) {
            return $item->receivingItems()->exists();
        });
        if ($allShippingReceived) {
            return;
        }

        DB::transaction(function () use ($po, $receiving, $shippingItems, $lastReceivedAt): void {
            // Create a new Receiving record for shipping as requested
            $newReceiving = Receiving::query()->create([
                'purchase_order_id' => (int) $po->getKey(),
                'received_at' => $lastReceivedAt ?: now(),
                'reference_number' => null,
                'notes' => null,
                'created_by' => $receiving->created_by,
            ]);

            foreach ($shippingItems as $item) {
                // Skip if this shipping item already has a receiving recorded (idempotency)
                if ($item->receivingItems()->exists()) {
                    continue;
                }

                ReceivingItem::query()->create([
                    'receiving_id' => (int) $newReceiving->getKey(),
                    'purchase_order_item_id' => (int) $item->getKey(),
                    'material_id' => $item->material_id, // may be null for shipping line
                    'unit_purchase' => (string) ($item->unit_purchase ?? 'shipping'),
                    'qty_received' => 1,
                    'qty_base' => 1,
                ]);
            }
        });
    }
}
