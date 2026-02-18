<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Observers;

use Lastdino\ProcurementFlow\Models\StockMovement;
use Lastdino\ProcurementFlow\Services\MonoxApiService;

class StockMovementObserver
{
    public function __construct(protected MonoxApiService $monoxApiService) {}

    public function created(StockMovement $movement): void
    {
        if ($movement->is_external_sync) {
            return;
        }

        $this->monoxApiService->syncStockMovement($movement);
    }
}
