<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Livewire\Procurement\PendingReceiving;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Lastdino\ProcurementFlow\Enums\PurchaseOrderStatus;
use Lastdino\ProcurementFlow\Models\PurchaseOrder;

class Index extends Component
{
    public string $q = '';

    public function getOrdersProperty()
    {
        $q = (string) $this->q;
        return PurchaseOrder::query()
            ->with(['supplier', 'items', 'requester'])
            ->whereIn('status', [
                PurchaseOrderStatus::Issued,
                PurchaseOrderStatus::Receiving,
            ])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('po_number', 'like', "%{$q}%")
                        ->orWhere('notes', 'like', "%{$q}%")
                        // 発注アイテムのスキャン用トークンでも検索可能にする
                        ->orWhereHas('items', function ($iq) use ($q) {
                            $iq->where(function ($iqq) use ($q) {
                                $iqq->where('scan_token', $q)
                                    ->orWhere('scan_token', 'like', $q.'%');
                            });
                        });
                });
            })
            ->latest('id')
            ->limit(50)
            ->get();
    }

    public function render(): View
    {
        return view('procflow::livewire.procurement.pending-receiving.index');
    }
}
