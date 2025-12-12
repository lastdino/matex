<div class="p-6 space-y-4">
    <x-procflow::topmenu />
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">{{ __('procflow::po.detail.title') }}</h1>
        <a href="{{ route('procurement.purchase-orders.index') }}" class="text-blue-600 hover:underline">{{ __('procflow::po.detail.back_to_list') }}</a>
    </div>

    <div class="grid gap-4">
        <div class="rounded border p-4 bg-white dark:bg-neutral-900">
            <div class="grid sm:grid-cols-2 gap-2">
                <div>
                    <div class="text-sm text-gray-500">{{ __('procflow::po.detail.fields.po_number') }}</div>
                    <div class="font-medium">{{ $po->po_number ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">{{ __('procflow::po.detail.fields.supplier') }}</div>
                    <div class="font-medium">{{ $po->supplier->name ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">{{ __('procflow::po.detail.fields.requester') }}</div>
                    <div class="font-medium">{{ $po->requester->name ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">{{ __('procflow::po.detail.fields.status') }}</div>
                    <div class="font-medium">
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
                        <flux:badge color="{{ $badgeColor }}" size="sm">{{ __('procflow::po.status.' . $statusVal) }}</flux:badge>
                    </div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">{{ __('procflow::po.detail.fields.issue_date') }}</div>
                    <div class="font-medium">{{ optional($po->issue_date)->format('Y-m-d H:i') ?? '—' }}</div>
                </div>
            </div>
        </div>

        <div class="rounded border p-4 bg-white dark:bg-neutral-900">
            <h2 class="font-semibold mb-2">{{ __('procflow::po.detail.items') }}</h2>
            @if ($po->items->isEmpty())
                <div class="text-gray-500">{{ __('procflow::po.detail.no_items') }}.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-gray-600">
                            <tr>
                                <th class="py-2 px-3">{{ __('procflow::po.detail.table.sku') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.detail.table.name_desc') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.detail.table.qty') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.detail.table.unit') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.detail.table.unit_price') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.detail.table.line_total') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.detail.table.desired_date') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.detail.table.expected_date') }}</th>
                                <th class="py-2 pr-4">{{ __('procflow::po.detail.table.qr') }}</th>
                                <th class="py-2 px-3 text-right print:hidden">{{ __('procflow::po.detail.table.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
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
                                <tr class="border-t align-top {{ $isCanceledLine ? 'line-through text-neutral-500' : '' }}">
                                    <td class="py-2 px-3">{{ $item->material->sku ?? '-' }}</td>
                                    <td class="py-2 px-3">
                                        <div class="flex items-center gap-2">
                                            <span>{{ $item->material->name ?? ($item->description ?? '-') }}</span>
                                            @if($qtyCanceled > 0)
                                                <flux:badge color="red" size="xs">{{ __('procflow::po.detail.badges.canceled') }}</flux:badge>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="py-2 px-3">
                                        {{ \Lastdino\ProcurementFlow\Support\Format::qty($effectiveQty) }}
                                        @if($qtyCanceled > 0)
                                            <span class="text-xs text-neutral-500">({{ __('procflow::po.detail.labels.canceled_qty') }}: {{ \Lastdino\ProcurementFlow\Support\Format::qty($qtyCanceled) }})</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-3">{{ $item->unit_purchase ?? '' }}</td>
                                    <td class="py-2 px-3">{{ \Lastdino\ProcurementFlow\Support\Format::moneyUnitPrice($item->price_unit ?? 0) }}</td>
                                    <td class="py-2 px-3">{{ \Lastdino\ProcurementFlow\Support\Format::moneyLineTotal(($item->price_unit ?? 0) * $effectiveQty) }}</td>
                                    <td class="py-2 px-3">{{ optional($item->desired_date)->format('Y-m-d') ?? '-' }}</td>
                                    <td class="py-2 px-3">{{ optional($item->expected_date)->format('Y-m-d') ?? '-' }}</td>
                                    <td class="py-2 pr-4">
                                        @if ($item->unit_purchase === 'shipping')
                                            <span class="text-gray-400">—</span>
                                        @elseif (!empty($item->scan_token))
                                            <div class="flex items-center gap-2">
                                                <div class="qr-svg w-[50px] h-[50px]">
                                                    {!! \tbQuar\Facades\Quar::size(50)->generate($item->scan_token) !!}
                                                </div>
                                                <div class="text-xs text-gray-500 select-all">{{ substr($item->scan_token, 0, 8) }}…</div>
                                            </div>
                                        @else
                                            <span class="text-gray-400">{{ __('procflow::po.detail.no_token') }}</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-3 text-right print:hidden">
                                        @php $statusVal = is_string($po->status) ? $po->status : ($po->status->value ?? 'draft'); @endphp
                                        @if(($statusVal !== 'closed' && !$isShipping) || $canCancel)
                                            <flux:dropdown>
                                                <flux:button size="xs" icon:trailing="chevron-down">{{ __('procflow::po.detail.table.actions') ?: 'Actions' }}</flux:button>
                                                <flux:menu>
                                                    @if($statusVal !== 'closed' && !$isShipping)
                                                        <flux:menu.item icon="calendar"
                                                            wire:click="openExpectedDateModal({{ (int)$item->id }})"
                                                            wire:loading.attr="disabled"
                                                            wire:target="openExpectedDateModal({{ (int)$item->id }})"
                                                        >{{ __('procflow::po.common.expected_date') }}</flux:menu.item>
                                                    @endif
                                                    @if($canCancel)
                                                        <flux:menu.item icon="x-circle" variant="danger"
                                                            x-on:click.prevent="if (confirm('{{ __('procflow::po.detail.confirm_cancel_item') }}')) { $wire.cancelItem({{ (int)$item->id }}) }"
                                                            wire:loading.attr="disabled"
                                                            wire:target="cancelItem({{ (int)$item->id }})"
                                                        >
                                                            <span wire:loading.remove wire:target="cancelItem({{ (int)$item->id }})">{{ __('procflow::po.buttons.cancel_line') }}</span>
                                                            <span wire:loading wire:target="cancelItem({{ (int)$item->id }})">{{ __('procflow::po.buttons.canceling_line') }}</span>
                                                        </flux:menu.item>
                                                    @endif
                                                </flux:menu>
                                            </flux:dropdown>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="rounded border p-4 bg-white dark:bg-neutral-900 print:hidden">
            <h4 class="text-md font-semibold mb-2">{{ __('procflow::po.detail.receivings.title') }}</h4>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-neutral-500">
                            <th class="py-2 px-3">{{ __('procflow::po.detail.receivings.received_at') }}</th>
                            <th class="py-2 px-3">{{ __('procflow::po.detail.receivings.reference') }}</th>
                            <th class="py-2 px-3">{{ __('procflow::po.detail.receivings.items') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(($po->receivings ?? []) as $rcv)
                            <tr class="border-t align-top">
                                <td class="py-2 px-3 whitespace-nowrap">{{ optional($rcv->received_at)->format('Y-m-d H:i') ?? '' }}</td>
                                <td class="py-2 px-3 whitespace-nowrap">{{ $rcv->reference_number ?? '-' }}</td>
                                <td class="py-2 px-3">
                                    @php $items = $rcv->items ?? collect(); @endphp
                                    @if($items->isEmpty())
                                        <span class="text-neutral-500">{{ __('procflow::po.detail.receivings.empty') }}</span>
                                    @else
                                        <ul class="space-y-1">
                                            @foreach($items as $rit)
                                                @php
                                                    $sku = $rit->material->sku ?? '';
                                                    $name = $rit->material->name ?? ($rit->purchaseOrderItem->description ?? '');
                                                    $qty = (float) ($rit->qty_received ?? 0);
                                                    $unit = $rit->unit_purchase ?? '';
                                                @endphp
                                                <li class="flex items-center gap-2">
                                                    <span class="text-neutral-500">{{ $sku }}</span>
                                                    <span>{{ $name }}</span>
                                                    <span class="ml-auto tabular-nums">{{ \Lastdino\ProcurementFlow\Support\Format::qty($qty) }} {{ $unit }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-4 text-center text-neutral-500">{{ __('procflow::po.detail.receivings.empty') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex items-center gap-2 print:hidden">
            <a href="{{ route('procurement.purchase-orders.index') }}" class="px-3 py-1.5 border rounded">{{ __('procflow::po.detail.buttons.back') }}</a>
            <button type="button" onclick="window.print()" class="px-3 py-1.5 border rounded">{{ __('procflow::po.detail.buttons.print_labels') }}</button>
            <a
                href="{{ route('procurement.purchase-orders.pdf', ['po' => $po->id]) }}"
                class="px-3 py-1.5 border rounded"
                target="_blank"
                rel="noopener"
            >{{ __('procflow::po.detail.buttons.download_pdf') }}</a>
            @php $statusVal = is_string($po->status) ? $po->status : ($po->status->value ?? 'draft'); @endphp
            @if($statusVal === 'draft')
                <flux:button variant="danger" class="ml-auto"
                    x-on:click.prevent="if (confirm('{{ __('procflow::po.detail.confirm_cancel') }}')) { $wire.cancelPo() }"
                    wire:loading.attr="disabled"
                    wire:target="cancelPo"
                >
                    <span wire:loading.remove wire:target="cancelPo">{{ __('procflow::po.buttons.cancel_order') }}</span>
                    <span wire:loading wire:target="cancelPo">{{ __('procflow::po.buttons.canceling_order') }}</span>
                </flux:button>
            @endif
        </div>
    </div>

    {{-- Expected Date Edit Modal --}}
    <flux:modal wire:model="showExpectedModal" name="expected-date">
        <div class="w-full max-w-md">
            <h3 class="text-lg font-semibold mb-3">{{ __('procflow::po.common.expected_date') }}</h3>
            <div class="space-y-3">
                <label class="block text-sm text-neutral-600 mb-1">{{ __('procflow::po.common.expected_date') }}</label>
                <input type="date"
                       class="w-full border rounded p-2 bg-white dark:bg-neutral-900"
                       wire:model.live="editingExpectedDate">
                @error('editingExpectedDate')
                    <div class="text-red-600 text-xs">{{ $message }}</div>
                @enderror
            </div>

            <div class="mt-5 flex items-center justify-end gap-2">
                <flux:button variant="outline" x-on:click="$flux.modal('expected-date').close()">{{ __('procflow::po.buttons.cancel') }}</flux:button>
                <flux:button variant="primary"
                             wire:click="saveExpectedDate"
                             wire:loading.attr="disabled"
                             wire:target="saveExpectedDate">
                    <span wire:loading.remove>{{ __('procflow::po.buttons.save') }}</span>
                    <span wire:loading>{{ __('procflow::po.buttons.saving') }}</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>

