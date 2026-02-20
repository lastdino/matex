<?php

declare(strict_types=1);

namespace Lastdino\Matex\Domain\Lot;

use Lastdino\Matex\Models\Material;
use Lastdino\Matex\Models\MaterialLot;
use Lastdino\Matex\Models\PurchaseOrder;

class LotService
{
    /**
     * @param  array{lot_no?:string|null, mfg_date?:string|null, expiry_date?:string|null, storage_location_id?:int|null}  $line
     */
    public function ensureAndIncrement(PurchaseOrder $po, Material $material, array $line, float $qtyBase, \DateTimeInterface|string $receivedAt): MaterialLot
    {
        abort_if(empty($line['lot_no'] ?? null), 422, 'lot_no is required for lot-managed materials.');

        /** @var MaterialLot $lot */
        $lot = MaterialLot::query()->firstOrCreate(
            [
                'material_id' => $material->id,
                'lot_no' => (string) $line['lot_no'],
                'storage_location_id' => $line['storage_location_id'] ?? null,
            ],
            [
                'unit' => $material->unit_stock,
                'received_at' => $receivedAt,
                'mfg_date' => $line['mfg_date'] ?? null,
                'expiry_date' => $line['expiry_date'] ?? null,
                'status' => 'Open',
                'purchase_order_id' => $po->id,
                'supplier_id' => $po->supplier_id,
            ]
        );

        $updates = [];
        foreach (['mfg_date', 'expiry_date'] as $k) {
            if (! empty($line[$k] ?? null)) {
                $updates[$k] = $line[$k];
            }
        }
        if (! empty($updates)) {
            $lot->fill($updates);
        }

        $lot->increment('qty_on_hand', $qtyBase);

        return $lot;
    }
}
