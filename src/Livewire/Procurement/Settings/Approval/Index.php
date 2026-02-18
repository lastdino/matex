<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Livewire\Procurement\Settings\Approval;

use Illuminate\Contracts\View\View;
use Lastdino\ApprovalFlow\Models\ApprovalFlow;
use Lastdino\ProcurementFlow\Models\AppSetting;
use Livewire\Component;

class Index extends Component
{
    public $purchaseOrderFlowId = '';

    public function mount(): void
    {
        $current = AppSetting::get('approval_flow.purchase_order_flow_id');
        $this->purchaseOrderFlowId = $current !== null && $current !== '' ? (int) $current : '';
    }

    public function save(): void
    {
        $this->validate([
            'purchaseOrderFlowId' => ['nullable', 'integer', 'exists:approval_flows,id'],
        ]);

        AppSetting::set('approval_flow.purchase_order_flow_id', $this->purchaseOrderFlowId !== null ? (string) $this->purchaseOrderFlowId : null);

        $this->dispatch('notify', text: __('procflow::settings.approval.flash.saved'));
    }

    public function getFlowsProperty()
    {
        return ApprovalFlow::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();
    }

    public function render(): View
    {
        return view('procflow::livewire.procurement.settings.approval.index');
    }
}
