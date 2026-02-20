<?php

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Lastdino\Matex\Enums\PurchaseOrderStatus;
use Lastdino\Matex\Models\PurchaseOrder;
use Lastdino\Matex\Models\PurchaseOrderItem;
use Lastdino\Matex\Services\UnitConversionService;
use Livewire\Component;

new class extends Component
{
    public PurchaseOrder $po;

    // Modal state for editing expected date (per line)
    public bool $showExpectedModal = false;

    public ?int $editingItemId = null;

    public ?string $editingExpectedDate = null; // Y-m-d

    public function mount(PurchaseOrder $po): void
    {
        // Load all relations required for detail view and receiving history
        $this->po = $po->load([
            'supplier',
            'requester',
            'items.material',
            'receivings.items',
            'receivings.items.material',
            'receivings.items.purchaseOrderItem',
        ]);
    }

    /**
     * Cancel a draft Purchase Order. Only allowed when current status is Draft.
     */
    public function cancelPo(): void
    {
        $po = $this->po->fresh();

        if (! $po) {
            $this->dispatch('toast', type: 'error', message: 'Purchase order not found');

            return;
        }

        // Normalize status value and check
        $statusValue = is_string($po->status) ? $po->status : ($po->status->value ?? '');
        if ($statusValue !== PurchaseOrderStatus::Draft->value) {
            $this->dispatch('toast', type: 'error', message: __('matex::po.detail.cancel_not_allowed'));

            return;
        }

        $po->status = PurchaseOrderStatus::Canceled;
        $po->save();

        $this->po = $po->load([
            'supplier',
            'requester',
            'items.material',
            'receivings.items',
            'receivings.items.material',
            'receivings.items.purchaseOrderItem',
        ]);

        $po->cancelApprovalFlowTask(
            userId: Auth::id(),      // キャンセルを実行するユーザーのID（通常は申請者自身）
            comment: '' // キャンセル理由（オプション）
        );

        $this->dispatch('toast', type: 'success', message: __('matex::po.detail.canceled_toast'));
    }

    /**
     * Cancel a single Purchase Order Item (entire remaining quantity).
     * Rules:
     * - Allowed only when PO status is Issued or Receiving.
     * - Shipping lines cannot be canceled by this action (kept as-is).
     * - If partially received, cancel the unreceived remainder only.
     */
    public function cancelItem(int $itemId, ?string $reason = null): void
    {
        /** @var PurchaseOrderItem|null $item */
        $item = PurchaseOrderItem::query()->with(['purchaseOrder', 'material'])->find($itemId);
        if (! $item) {
            $this->dispatch('toast', type: 'error', message: __('matex::po.detail.item_not_found'));

            return;
        }

        $po = $item->purchaseOrder;
        if (! in_array($po->status, [PurchaseOrderStatus::Issued, PurchaseOrderStatus::Receiving], true)) {
            $this->dispatch('toast', type: 'error', message: __('matex::po.detail.item_cancel_not_allowed'));

            return;
        }

        // Do not cancel shipping lines via this action
        if ($item->unit_purchase === 'shipping') {
            $this->dispatch('toast', type: 'error', message: __('matex::po.detail.item_cancel_shipping_not_allowed'));

            return;
        }

        // Already fully canceled?
        $ordered = (float) ($item->qty_ordered ?? 0);
        $canceled = (float) ($item->qty_canceled ?? 0);
        if ($canceled >= $ordered - 1e-9) {
            $this->dispatch('toast', type: 'info', message: __('matex::po.detail.item_already_canceled'));

            return;
        }

        // Compute received quantity in purchase unit
        $material = $item->material; // may be null for ad-hoc
        $receivedBase = (float) $item->receivingItems()->sum('qty_base');
        if ($material) {
            /** @var UnitConversionService $conv */
            $conv = app(UnitConversionService::class);
            $factor = (float) $conv->factor($material, $item->unit_purchase, $material->unit_stock);
            $receivedPurchase = $factor > 0 ? ($receivedBase / $factor) : 0.0;
        } else {
            // ad-hoc: base == purchase
            $receivedPurchase = $receivedBase;
        }

        $alreadyCanceled = $canceled;
        $remaining = max($ordered - $receivedPurchase - $alreadyCanceled, 0.0);
        if ($remaining <= 1e-9) {
            $this->dispatch('toast', type: 'info', message: __('matex::po.detail.item_no_remaining_to_cancel'));

            return;
        }

        // Apply cancel of the remaining qty
        $item->qty_canceled = $alreadyCanceled + $remaining;
        $item->canceled_at = now();
        if ($reason) {
            $item->canceled_reason = $reason;
        }
        $item->save();

        // Update PO status depending on whether any receipt exists and if any effective remaining
        $po->refresh();
        $po->loadMissing(['items', 'receivings.items']);

        // Determine if any receipt exists for this PO at all
        $hasAnyReceipt = $po->receivings->flatMap->items->isNotEmpty();

        // Compute effective remaining quantity across items
        $effectiveRemaining = 0.0;
        foreach ($po->items as $lit) {
            $ordered = (float) ($lit->qty_ordered ?? 0);
            $canceledQty = (float) ($lit->qty_canceled ?? 0);
            $effectiveRemaining += max($ordered - $canceledQty, 0.0);
        }

        if ($effectiveRemaining <= 1e-9) {
            // No quantities left to receive
            $po->status = $hasAnyReceipt ? PurchaseOrderStatus::Closed : PurchaseOrderStatus::Canceled;
            $po->save();
        }

        // Reload page data
        $this->po = $po->load([
            'supplier',
            'requester',
            'items.material',
            'receivings.items',
            'receivings.items.material',
            'receivings.items.purchaseOrderItem',
        ]);

        $this->dispatch('toast', type: 'success', message: __('matex::po.detail.item_canceled_toast'));
    }

    /**
     * Open modal to edit expected date for a specific item.
     */
    public function openExpectedDateModal(int $itemId): void
    {
        $po = $this->po->fresh(['items']);
        if (! $po) {
            return;
        }

        $statusValue = is_string($po->status) ? $po->status : ($po->status->value ?? '');
        if ($statusValue === PurchaseOrderStatus::Closed->value) {
            $this->dispatch('toast', type: 'error', message: __('matex::po.detail.cancel_not_allowed'));

            return;
        }

        /** @var PurchaseOrderItem|null $item */
        $item = $po->items->firstWhere('id', $itemId);
        if (! $item) {
            $this->dispatch('toast', type: 'error', message: __('matex::po.detail.item_not_found'));

            return;
        }

        // Do not allow for shipping lines
        if ($item->unit_purchase === 'shipping') {
            $this->dispatch('toast', type: 'error', message: __('matex::po.detail.item_cancel_shipping_not_allowed'));

            return;
        }

        $this->editingItemId = $item->id;
        $this->editingExpectedDate = $item->expected_date?->format('Y-m-d');
        $this->showExpectedModal = true;
    }

    /**
     * Persist expected date for the currently editing item.
     */
    public function saveExpectedDate(): void
    {
        if (! $this->editingItemId) {
            return;
        }

        $this->validate([
            'editingExpectedDate' => ['nullable', 'date'],
        ]);

        $po = $this->po->fresh(['items']);
        if (! $po) {
            return;
        }

        $statusValue = is_string($po->status) ? $po->status : ($po->status->value ?? '');
        if ($statusValue === PurchaseOrderStatus::Closed->value) {
            $this->dispatch('toast', type: 'error', message: __('matex::po.detail.cancel_not_allowed'));

            return;
        }

        /** @var PurchaseOrderItem|null $item */
        $item = $po->items->firstWhere('id', $this->editingItemId);
        if (! $item) {
            $this->dispatch('toast', type: 'error', message: __('matex::po.detail.item_not_found'));

            return;
        }

        if ($item->unit_purchase === 'shipping') {
            $this->dispatch('toast', type: 'error', message: __('matex::po.detail.item_cancel_shipping_not_allowed'));

            return;
        }

        $item->expected_date = $this->editingExpectedDate ? \Carbon\Carbon::parse($this->editingExpectedDate) : null;
        $item->save();

        // Reload for UI
        $this->po->refresh();
        $this->po->load([
            'supplier',
            'requester',
            'items.material',
            'receivings.items',
            'receivings.items.material',
            'receivings.items.purchaseOrderItem',
        ]);

        $this->showExpectedModal = false;
        $this->editingItemId = null;
        $this->editingExpectedDate = null;

        $this->dispatch('toast', type: 'success', message: __('matex::po.labels.saved'));
    }
};

?>

<div class="p-6 space-y-4">
    <x-matex::topmenu />
    <div class="flex items-center justify-between">
        <flux:heading size="lg">{{ __('matex::po.detail.title') }}</flux:heading>
        <flux:link href="{{ route('matex.purchase-orders.index') }}" icon="arrow-left">{{ __('matex::po.detail.back_to_list') }}</flux:link>
    </div>

    <div class="grid gap-4">
        <flux:card>
            <div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-6">
                <flux:field>
                    <flux:label>{{ __('matex::po.detail.fields.po_number') }}</flux:label>
                    <div class="font-medium">{{ $po->po_number ?? '—' }}</div>
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('matex::po.detail.fields.supplier') }}</flux:label>
                    <div class="font-medium">{{ $po->supplier->name ?? '—' }}</div>
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('matex::po.detail.fields.requester') }}</flux:label>
                    <div class="font-medium">{{ $po->requester->name ?? '—' }}</div>
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('matex::po.detail.fields.status') }}</flux:label>
                    <div>
                        @php
                            $statusVal = is_string($po->status) ? $po->status : ($po->status->value ?? 'draft');
                            $badgeColor = match ($statusVal) {
                                'closed' => 'green',
                                'issued' => 'yellow',
                                'receiving' => 'cyan',
                                'canceled' => 'red',
                                default => 'zinc',
                            };
                        @endphp
                        <flux:badge color="{{ $badgeColor }}" size="sm">{{ __('matex::po.status.' . $statusVal) }}</flux:badge>
                    </div>
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('matex::po.detail.fields.issue_date') }}</flux:label>
                    <div class="font-medium">{{ optional($po->issue_date)->format('Y-m-d H:i') ?? '—' }}</div>
                </flux:field>
            </div>
        </flux:card>

        <flux:card>
            <flux:heading size="md" class="mb-4">{{ __('matex::po.detail.items') }}</flux:heading>
            @if ($po->items->isEmpty())
                <div class="text-gray-500">{{ __('matex::po.detail.no_items') }}.</div>
            @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('matex::po.detail.table.sku') }}</flux:table.column>
                <flux:table.column>{{ __('matex::po.detail.table.name_desc') }}</flux:table.column>
                <flux:table.column>{{ __('matex::po.detail.table.qty') }}</flux:table.column>
                <flux:table.column>{{ __('matex::po.detail.table.unit') }}</flux:table.column>
                <flux:table.column>{{ __('matex::po.detail.table.unit_price') }}</flux:table.column>
                <flux:table.column>{{ __('matex::po.detail.table.line_total') }}</flux:table.column>
                <flux:table.column>{{ __('matex::po.detail.table.desired_date') }}</flux:table.column>
                <flux:table.column>{{ __('matex::po.detail.table.expected_date') }}</flux:table.column>
                <flux:table.column>{{ __('matex::po.detail.table.qr') }}</flux:table.column>
                <flux:table.column align="end" class="print:hidden">{{ __('matex::po.detail.table.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($po->items as $item)
                    @php
                        $qtyOrdered = (float) ($item->qty_ordered ?? 0);
                        $qtyCanceled = (float) ($item->qty_canceled ?? 0);
                        $effectiveQty = max($qtyOrdered - $qtyCanceled, 0);
                        $isCanceledLine = $qtyCanceled >= $qtyOrdered - 1e-9;
                        $isShipping = ($item->unit_purchase ?? '') === 'shipping';
                        $statusVal = is_string($po->status) ? $po->status : ($po->status->value ?? 'draft');
                        $canCancel = in_array($statusVal, ['issued','receiving'], true) && !$isShipping && !$isCanceledLine && $effectiveQty > 0;
                    @endphp
                    <flux:table.row :class="$isCanceledLine ? 'line-through text-neutral-500' : ''">
                        <flux:table.cell>{{ $item->material->sku ?? '-' }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <span>{{ $item->material->name ?? ($item->description ?? '-') }}</span>
                                @if($qtyCanceled > 0)
                                    <flux:badge color="red" size="xs">{{ __('matex::po.detail.badges.canceled') }}</flux:badge>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ \Lastdino\Matex\Support\Format::qty($effectiveQty) }}
                            @if($qtyCanceled > 0)
                                <div class="text-[10px] text-neutral-500">({{ __('matex::po.detail.labels.canceled_qty') }}: {{ \Lastdino\Matex\Support\Format::qty($qtyCanceled) }})</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $item->unit_purchase ?? '' }}</flux:table.cell>
                        <flux:table.cell class="tabular-nums">{{ \Lastdino\Matex\Support\Format::moneyUnitPrice($item->price_unit ?? 0) }}</flux:table.cell>
                        <flux:table.cell class="tabular-nums">{{ \Lastdino\Matex\Support\Format::moneyLineTotal(($item->price_unit ?? 0) * $effectiveQty) }}</flux:table.cell>
                        <flux:table.cell>{{ optional($item->desired_date)->format('Y-m-d') ?? '-' }}</flux:table.cell>
                        <flux:table.cell>{{ optional($item->expected_date)->format('Y-m-d') ?? '-' }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($item->unit_purchase === 'shipping')
                                <span class="text-gray-400">—</span>
                            @elseif (!empty($item->scan_token))
                                <div class="flex items-center gap-2">
                                    <div class="qr-svg w-[40px] h-[40px]">
                                        {!! \tbQuar\Facades\Quar::size(40)->generate(route('matex.receiving', ['token' => $item->scan_token])) !!}
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <div class="text-[10px] text-gray-500 select-all">{{ substr($item->scan_token, 0, 8) }}…</div>
                                    </div>
                                </div>
                            @else
                                <span class="text-gray-400 text-xs">{{ __('matex::po.detail.no_token') }}</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex justify-end print:hidden">
                                @php $statusVal = is_string($po->status) ? $po->status : ($po->status->value ?? 'draft'); @endphp
                                @if(($statusVal !== 'closed' && !$isShipping) || $canCancel)
                                    <flux:dropdown>
                                        <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" />
                                        <flux:menu>
                                            @if(in_array($statusVal, ['issued', 'receiving'], true) && !empty($item->scan_token))
                                                <flux:menu.item
                                                    icon="arrow-right-start-on-rectangle"
                                                    href="{{ route('matex.receiving', ['token' => $item->scan_token]) }}"
                                                >
                                                    {{ __('matex::receiving.buttons.go_to_receiving') }}
                                                </flux:menu.item>
                                            @endif
                                            @if($statusVal !== 'closed' && !$isShipping)
                                                <flux:menu.item icon="calendar"
                                                    wire:click="openExpectedDateModal({{ (int)$item->id }})"
                                                    wire:loading.attr="disabled"
                                                    wire:target="openExpectedDateModal({{ (int)$item->id }})"
                                                >{{ __('matex::po.common.expected_date') }}</flux:menu.item>
                                            @endif
                                            @if($canCancel)
                                                <flux:menu.item icon="x-circle" variant="danger"
                                                    x-on:click.prevent="if (confirm('{{ __('matex::po.detail.confirm_cancel_item') }}')) { $wire.cancelItem({{ (int)$item->id }}) }"
                                                    wire:loading.attr="disabled"
                                                    wire:target="cancelItem({{ (int)$item->id }})"
                                                >
                                                    <span wire:loading.remove wire:target="cancelItem({{ (int)$item->id }})">{{ __('matex::po.buttons.cancel_line') }}</span>
                                                    <span wire:loading wire:target="cancelItem({{ (int)$item->id }})">{{ __('matex::po.buttons.canceling_line') }}</span>
                                                </flux:menu.item>
                                            @endif
                                        </flux:menu>
                                    </flux:dropdown>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
            @endif
        </flux:card>

        <flux:card class="print:hidden">
            <flux:heading size="md" class="mb-4">{{ __('matex::po.detail.receivings.title') }}</flux:heading>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('matex::po.detail.receivings.received_at') }}</flux:table.column>
                    <flux:table.column>{{ __('matex::po.detail.receivings.reference') }}</flux:table.column>
                    <flux:table.column>{{ __('matex::po.detail.receivings.items') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse(($po->receivings ?? []) as $rcv)
                        <flux:table.row>
                            <flux:table.cell class="whitespace-nowrap">{{ optional($rcv->received_at)->format('Y-m-d H:i') ?? '' }}</flux:table.cell>
                            <flux:table.cell class="whitespace-nowrap">{{ $rcv->reference_number ?? '-' }}</flux:table.cell>
                            <flux:table.cell>
                                @php $items = $rcv->items ?? collect(); @endphp
                                @if($items->isEmpty())
                                    <span class="text-neutral-500">{{ __('matex::po.detail.receivings.empty') }}</span>
                                @else
                                    <ul class="space-y-1 text-xs">
                                        @foreach($items as $rit)
                                            @php
                                                $sku = $rit->material->sku ?? '';
                                                $name = $rit->material->name ?? ($rit->purchaseOrderItem->description ?? '');
                                                $qty = (float) ($rit->qty_received ?? 0);
                                                $unit = $rit->unit_purchase ?? '';
                                            @endphp
                                            <li class="flex items-center gap-2">
                                                <span class="text-neutral-500">{{ $sku }}</span>
                                                <span class="font-medium">{{ $name }}</span>
                                                <span class="ml-auto tabular-nums">{{ \Lastdino\Matex\Support\Format::qty($qty) }} {{ $unit }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="3" class="text-center text-neutral-500 py-6">{{ __('matex::po.detail.receivings.empty') }}</flux:cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>

        <div class="flex items-center gap-2 print:hidden">
            @php $statusVal = is_string($po->status) ? $po->status : ($po->status->value ?? 'draft'); @endphp
            <flux:button href="{{ route('matex.purchase-orders.index') }}" variant="outline">{{ __('matex::po.detail.buttons.back') }}</flux:button>
            <flux:button type="button" onclick="window.print()" variant="outline">{{ __('matex::po.detail.buttons.print_labels') }}</flux:button>
            @if ($statusVal !== 'draft' && $statusVal !== 'canceled')
                <flux:button href="{{ route('matex.purchase-orders.pdf', ['po' => $po->id]) }}" target="_blank" variant="outline">{{ __('matex::po.detail.buttons.download_pdf') }}</flux:button>
            @endif

            @if($statusVal === 'draft')
                <flux:button variant="danger" class="ml-auto"
                    x-on:click.prevent="if (confirm('{{ __('matex::po.detail.confirm_cancel') }}')) { $wire.cancelPo() }"
                    wire:loading.attr="disabled"
                    wire:target="cancelPo"
                >
                    <span wire:loading.remove wire:target="cancelPo">{{ __('matex::po.buttons.cancel_order') }}</span>
                    <span wire:loading wire:target="cancelPo">{{ __('matex::po.buttons.canceling_order') }}</span>
                </flux:button>
            @endif
        </div>
    </div>

    {{-- Expected Date Edit Modal --}}
    <flux:modal wire:model="showExpectedModal" name="expected-date" class="w-full md:w-[30rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('matex::po.common.expected_date') }}</flux:heading>
                <flux:subheading>{{ __('matex::po.detail.expected_date_modal_sub') ?: '入荷予定日を更新してください。' }}</flux:subheading>
            </div>

            <flux:input type="date" wire:model.live="editingExpectedDate" label="{{ __('matex::po.detail.table.expected_date') }}" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" x-on:click="$flux.modal('expected-date').close()">{{ __('matex::po.buttons.cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="saveExpectedDate" wire:loading.attr="disabled" wire:target="saveExpectedDate">
                    <span wire:loading.remove wire:target="saveExpectedDate">{{ __('matex::po.buttons.save') }}</span>
                    <span wire:loading wire:target="saveExpectedDate">{{ __('matex::po.buttons.saving') }}</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>

