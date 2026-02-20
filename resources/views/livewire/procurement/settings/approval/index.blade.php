<?php

use Illuminate\Contracts\View\View;
use Lastdino\ApprovalFlow\Models\ApprovalFlow;
use Lastdino\ProcurementFlow\Models\AppSetting;
use Livewire\Component;

new class extends Component
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
};

?>

<div class="p-6 space-y-6">
    <x-procflow::topmenu />
    <div class="mx-auto w-full max-w-2xl">
        <flux:heading size="xl" class="mb-6">{{ __('procflow::settings.approval.title') }}</flux:heading>

        <div class="space-y-5">
            <flux:select wire:model="purchaseOrderFlowId" placeholder="{{ __('procflow::settings.approval.select.placeholder') }}" label="{{ __('procflow::settings.approval.select.label') }}">
                @foreach($this->flows as $flow)
                    <flux:select.option :value="$flow->id">{{ $flow->name }} (ID: {{ $flow->id }})</flux:select.option>
                @endforeach
            </flux:select>
            <div class="flex gap-3">
                <flux:button wire:click="save" variant="primary">{{ __('procflow::settings.approval.buttons.save') }}</flux:button>
            </div>
        </div>
    </div>
</div>
