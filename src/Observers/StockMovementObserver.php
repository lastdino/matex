<?php

declare(strict_types=1);

namespace Lastdino\Matex\Observers;

use Lastdino\Matex\Jobs\SyncStockMovementToMonox;
use Lastdino\Matex\Models\StockMovement;

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
