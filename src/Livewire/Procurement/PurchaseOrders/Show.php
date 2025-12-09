<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Livewire\Procurement\PurchaseOrders;

use Illuminate\Contracts\View\View;
use Lastdino\ProcurementFlow\Models\PurchaseOrder;
use Livewire\Component;

class Show extends Component
{
    public PurchaseOrder $po;

    public function mount(PurchaseOrder $po): void
    {
        $this->po = $po->load(['supplier', 'items', 'requester']);
    }

    public function render(): View
    {
        return view('procflow::livewire.procurement.purchase-orders.show', [
            'po' => $this->po,
        ]);
    }
}
