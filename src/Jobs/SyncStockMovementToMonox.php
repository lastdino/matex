<?php

declare(strict_types=1);

namespace Lastdino\Matex\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Lastdino\Matex\Models\StockMovement;
use Lastdino\Matex\Services\MonoxApiService;

class SyncStockMovementToMonox implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public StockMovement $movement
    ) {}

    /**
     * Execute the job.
     */
    public function handle(MonoxApiService $service): void
    {
        $service->syncStockMovement($this->movement);
    }
}
