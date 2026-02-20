<?php

declare(strict_types=1);

namespace Lastdino\Matex\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Lastdino\Matex\Models\AppSetting;
use Lastdino\Matex\Models\PurchaseOrder;

final class ApprovalFlowRegistrar
{
    public function registerForPo(PurchaseOrder $po): void
    {
        try {
            $poModel = $po->fresh();
            $authorId = (int) (auth()->id() ?? $poModel->created_by ?? 0);
            $link = null;
            if (Route::has('matex.purchase-orders.show')) {
                $link = route('matex.purchase-orders.show', ['po' => $poModel->id]);
            } elseif (Route::has('purchase-orders.show')) {
                $link = route('purchase-orders.show', ['purchase_order' => $poModel->id]);
            }
            $flowId = (int) ((AppSetting::get('approval_flow.purchase_order_flow_id')) ?? 0);
            if ($authorId > 0 && $flowId > 0) {
                $poModel->registerApprovalFlowTask($flowId, $authorId, null, null, $link);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to register approval flow for PO: '.$e->getMessage(), ['po_id' => $po->id]);
        }
    }
}
