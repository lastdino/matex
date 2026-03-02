<?php

declare(strict_types=1);

namespace Lastdino\Matex\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Lastdino\Matex\Models\StockMovement;

class MonoxApiService
{
    /**
     * monox APIに対して在庫変動を通知する
     */
    public function syncStockMovement(StockMovement $movement): void
    {
        $material = $movement->material;

        if (! $material || ! $material->sync_to_monox || ! $material->sku) {
            return;
        }

        $baseUrl = config('matex.monox.base_url');
        $apiKey = $movement->department?->api_token ?: config('matex.monox.api_key');

        if (empty($baseUrl)) {
            Log::warning('Monox API base URL is not configured.');

            return;
        }

        // endpoint: POST /api/monox/v1/inventory/sync
        $endpoint = rtrim($baseUrl, '/').'/api/monox/v1/inventory/sync';

        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $apiKey,
                'Accept' => 'application/json',
            ])->post($endpoint, [
                'sku' => $material->sku,
                'lot_no' => $movement->lot?->lot_no,
                'qty' => (float) $movement->qty_base,
                'type' => $movement->type === 'in' ? 'in' : 'out',
                'reason' => $movement->reason ?: 'matex からの同期',
            ]);

            if ($response->failed()) {
                Log::error('Monox API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'movement_id' => $movement->id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Monox API communication error', [
                'message' => $e->getMessage(),
                'movement_id' => $movement->id,
            ]);
        }
    }
}
