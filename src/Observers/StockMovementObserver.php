<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Observers;

use Lastdino\ProcurementFlow\Jobs\SyncStockMovementToMonox;
use Lastdino\ProcurementFlow\Models\StockMovement;

class StockMovementObserver
{
    public function created(StockMovement $movement): void
    {
        if ($movement->is_external_sync) {
            return;
        }

        SyncStockMovementToMonox::dispatch($movement);
    }
}
