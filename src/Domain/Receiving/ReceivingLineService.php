<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Domain\Receiving;

use Lastdino\ProcurementFlow\Domain\Lot\LotService;
use Lastdino\ProcurementFlow\Domain\Stock\StockMovementService;
use Lastdino\ProcurementFlow\Models\Material;
use Lastdino\ProcurementFlow\Models\PurchaseOrder;
use Lastdino\ProcurementFlow\Models\PurchaseOrderItem;
use Lastdino\ProcurementFlow\Models\Receiving;
use Lastdino\ProcurementFlow\Models\ReceivingItem;
use Lastdino\ProcurementFlow\Models\StorageLocation;
use Lastdino\ProcurementFlow\Services\UnitConversionService;

class ReceivingLineService
{
    public function __construct(
        public UnitConversionService $conversion,
        public OverDeliveryGuard $guard,
        public LotService $lot,
        public StockMovementService $movement,
    ) {}

    /**
     * @param  array{qty:float|int, unit_purchase?:string|null, lot_no?:string|null, mfg_date?:string|null, expiry_date?:string|null, storage_location_id?:int|null}  $line
     */
    public function handle(PurchaseOrder $po, Receiving $receiving, PurchaseOrderItem $poi, array $line): void
    {
        // Shipping line guard
        abort_if($poi->unit_purchase === 'shipping', 422, 'Shipping line is not receivable.');

        $qty = (float) $line['qty'];

        // Ad-hoc item: no material
        if (is_null($poi->material_id)) {
            $qtyBase = $qty; // no conversion
            $this->guard->assertNotExceededAdhoc($poi, $qtyBase);

            ReceivingItem::create([
                'receiving_id' => $receiving->id,
                'purchase_order_item_id' => $poi->id,
                'material_id' => null,
                'unit_purchase' => $line['unit_purchase'] ?? $poi->unit_purchase,
                'qty_received' => $qty,
                'qty_base' => $qtyBase,
            ]);

            return;
        }

        /** @var Material $material */
        $material = Material::query()->whereKey($poi->material_id)->firstOrFail();
        $fromUnit = $line['unit_purchase'] ?? $poi->unit_purchase;
        $factor = (float) $this->conversion->factor($material, $fromUnit, $material->unit_stock);
        $qtyBase = $qty * $factor;

        // Over delivery guard in base unit
        $this->guard->assertNotExceededMaterial($poi, $material, $qtyBase, $this->conversion);

        if ($line['storage_location_id']) {
            $location = StorageLocation::findOrFail($line['storage_location_id']);
            $this->guard->assertStorageLocationNotExceeded($location, $material, $qtyBase);
        }

        $ri = ReceivingItem::create([
            'receiving_id' => $receiving->id,
            'purchase_order_item_id' => $poi->id,
            'material_id' => $material->id,
            'unit_purchase' => $fromUnit,
            'qty_received' => $qty,
            'qty_base' => $qtyBase,
        ]);

        // Lot / stock movement
        // require lot no and upsert + increment
        $lot = $this->lot->ensureAndIncrement(
            $po,
            $material,
            [
                'lot_no' => $line['lot_no'] ?? null,
                'mfg_date' => $line['mfg_date'] ?? null,
                'expiry_date' => $line['expiry_date'] ?? null,
                'storage_location_id' => $line['storage_location_id'] ?? null,
            ],
            $qtyBase,
            $receiving->received_at
        );

        $this->movement->in($material, $ri, $qtyBase, $receiving->received_at, $lot->id);

        // Optional current_stock increment
        if (! is_null($material->current_stock)) {
            $material->increment('current_stock', $qtyBase);
        }
    }
}
