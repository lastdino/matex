<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Actions\Receiving;

use Illuminate\Support\Facades\DB;
use Lastdino\ProcurementFlow\Domain\PO\PurchaseOrderCompletionService;
use Lastdino\ProcurementFlow\Domain\Receiving\ReceivingLineService;
use Lastdino\ProcurementFlow\Enums\PurchaseOrderStatus;
use Lastdino\ProcurementFlow\Models\PurchaseOrder;
use Lastdino\ProcurementFlow\Models\PurchaseOrderItem;
use Lastdino\ProcurementFlow\Models\Receiving;
use Lastdino\ProcurementFlow\Services\UnitConversionService;

class ReceivePurchaseOrderAction
{
    public function __construct(
        public ReceivingLineService $line,
        public PurchaseOrderCompletionService $poCompletion,
        public UnitConversionService $conversion,
    ) {}

    /**
     * @param  array{received_at:string, reference_number?:string|null, notes?:string|null, items: array<int, array{purchase_order_item_id:int, qty_received:float|int, unit_purchase?:string, lot_no?:string|null, mfg_date?:string|null, expiry_date?:string|null}>}  $payload
     */
    public function byItems(PurchaseOrder $po, array $payload): Receiving
    {
        // Only allow receiving after issuance
        abort_if(! in_array($po->status, [PurchaseOrderStatus::Issued, PurchaseOrderStatus::Receiving], true), 422, 'PO must be issued to receive.');

        return DB::transaction(function () use ($po, $payload) {
            $receiving = Receiving::create([
                'purchase_order_id' => $po->id,
                'received_at' => (string) $payload['received_at'],
                'reference_number' => $payload['reference_number'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ]);

            foreach ($payload['items'] as $line) {
                /** @var PurchaseOrderItem $poi */
                $poi = PurchaseOrderItem::query()->whereKey($line['purchase_order_item_id'])->firstOrFail();
                $this->line->handle($po, $receiving, $poi, [
                    'qty' => (float) $line['qty_received'],
                    'unit_purchase' => $line['unit_purchase'] ?? $poi->unit_purchase,
                    'lot_no' => $line['lot_no'] ?? null,
                    'mfg_date' => $line['mfg_date'] ?? null,
                    'expiry_date' => $line['expiry_date'] ?? null,
                ]);
            }

            $this->updatePoStatus($po);

            return $receiving->load('items');
        });
    }

    /**
     * @param  array{token:string, qty:float|int, received_at?:string|null, reference_number?:string|null, notes?:string|null, lot_no?:string|null, mfg_date?:string|null, expiry_date?:string|null}  $payload
     */
    public function byScan(array $payload): Receiving
    {
        /** @var PurchaseOrderItem $poi */
        $poi = PurchaseOrderItem::query()->whereScanToken((string) $payload['token'])->firstOrFail();
        /** @var PurchaseOrder $po */
        $po = PurchaseOrder::query()->whereKey($poi->purchase_order_id)->firstOrFail();

        abort_if(! in_array($po->status, [PurchaseOrderStatus::Issued, PurchaseOrderStatus::Receiving], true), 422, 'PO must be issued to receive.');

        return DB::transaction(function () use ($po, $poi, $payload) {
            $receivedAt = $payload['received_at'] ?? now()->toISOString();

            $receiving = Receiving::create([
                'purchase_order_id' => $po->id,
                'received_at' => $receivedAt,
                'reference_number' => $payload['reference_number'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ]);

            $this->line->handle($po, $receiving, $poi, [
                'qty' => (float) $payload['qty'],
                'unit_purchase' => $poi->unit_purchase,
                'lot_no' => $payload['lot_no'] ?? null,
                'mfg_date' => $payload['mfg_date'] ?? null,
                'expiry_date' => $payload['expiry_date'] ?? null,
            ]);

            $this->updatePoStatus($po);

            return $receiving->load('items');
        });
    }

    private function updatePoStatus(PurchaseOrder $po): void
    {
        $po->update([
            'status' => $this->poCompletion->isFullyReceived($po, $this->conversion)
                ? PurchaseOrderStatus::Closed
                : PurchaseOrderStatus::Receiving,
        ]);
    }
}
