<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Domain\Stock;

use DateTimeInterface;
use Illuminate\Support\Facades\Auth;
use Lastdino\ProcurementFlow\Models\Material;
use Lastdino\ProcurementFlow\Models\ReceivingItem;
use Lastdino\ProcurementFlow\Models\StockMovement;

class StockMovementService
{
    /**
     * Create an inbound stock movement record.
     */
    public function in(Material $material, ReceivingItem $ri, float $qtyBase, string|DateTimeInterface $occurredAt, ?int $lotId): void
    {
        $causerType = null;
        $causerId = null;
        if (Auth::check()) {
            /** @var class-string $userModel */
            $userModel = (string) config('auth.providers.users.model');
            $causerType = $userModel ?: null;
            $causerId = Auth::id();
        }

        StockMovement::create([
            'material_id' => $material->id,
            'lot_id' => $lotId,
            'type' => 'in',
            'source_type' => ReceivingItem::class,
            'source_id' => $ri->id,
            'qty_base' => $qtyBase,
            'unit' => $material->unit_stock,
            'occurred_at' => $occurredAt,
            'reason' => 'receiving',
            'causer_type' => $causerType,
            'causer_id' => $causerId,
        ]);
    }
}
