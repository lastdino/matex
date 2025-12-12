<div class="p-6 space-y-6">
    <x-procflow::topmenu />
    <div class="flex flex-wrap items-end gap-3">
        <div class="w-full sm:w-40">
            <flux:input wire:model.live.debounce.300ms="poNumber" placeholder="{{ __('procflow::po.filters.po_number_placeholder') }}" />
        </div>
        <div class="w-full sm:w-56">
            <flux:select wire:model.live="supplierId">
                <option value="">{{ __('procflow::po.filters.supplier') }}</option>
                @foreach($this->suppliers as $sup)
                    <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                @endforeach
            </flux:select>
        </div>
        <div class="w-full sm:w-48">
            <flux:select wire:model.live="requesterId">
                <option value="">{{ __('procflow::po.filters.requester') }}</option>
                @foreach($this->users as $u)
                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
            </flux:select>
        </div>
        <flux:field>
            <flux:label>{{ __('procflow::po.filters.issue_from_to') }}</flux:label>
            <div class="flex items-center gap-2">
                <div class="w-full sm:w-44">
                    <input type="date" class="w-full border rounded p-2 bg-white dark:bg-neutral-900" wire:model.live="issueDate.start" placeholder="{{ __('procflow::po.filters.issue_from') }}">
                </div>
                <div class="w-full sm:w-44">
                    <input type="date" class="w-full border rounded p-2 bg-white dark:bg-neutral-900" wire:model.live="issueDate.end" placeholder="{{ __('procflow::po.filters.issue_to') }}">
                </div>
            </div>

        </flux:field>
        <flux:field>
            <flux:label>{{ __('procflow::po.filters.expected_from_to') }}</flux:label>
            <div class="flex items-center gap-2">
                <div class="w-full sm:w-44">
                    <input type="date" class="w-full border rounded p-2 bg-white dark:bg-neutral-900" wire:model.live="expectedDate.start" placeholder="{{ __('procflow::po.filters.expected_from') }}">
                </div>
                <div class="w-full sm:w-44">
                    <input type="date" class="w-full border rounded p-2 bg-white dark:bg-neutral-900" wire:model.live="expectedDate.end" placeholder="{{ __('procflow::po.filters.expected_to') }}">
                </div>
            </div>
        </flux:field>

        <div class="w-full sm:flex-1 min-w-64">
            <flux:input wire:model.live.debounce.300ms="q" placeholder="{{ __('procflow::po.filters.search_placeholder') }}" />
        </div>
        <div class="w-full sm:w-40">
            <flux:select wire:model.live="status">
                <option value="">{{ __('procflow::po.filters.status_all') }}</option>
                <option value="draft">{{ __('procflow::po.status.draft') }}</option>
                <option value="issued">{{ __('procflow::po.status.issued') }}</option>
                <option value="receiving">{{ __('procflow::po.status.receiving') }}</option>
                <option value="closed">{{ __('procflow::po.status.closed') }}</option>
                <option value="canceled">{{ __('procflow::po.status.canceled') }}</option>
            </flux:select>
        </div>
        <div class="ms-auto flex gap-2 w-full sm:w-auto">
            <flux:button variant="outline" wire:click="clearFilters">{{ __('procflow::po.buttons.clear_filters') }}</flux:button>
            <flux:button variant="primary" wire:click="openCreatePo">{{ __('procflow::po.buttons.new_po') }}</flux:button>
            <flux:button variant="outline" wire:click="openAdhocPo">{{ __('procflow::po.buttons.adhoc_order') }}</flux:button>
            <flux:button variant="outline" wire:click="openExportModal">{{ __('procflow::po.export.history_button') }}</flux:button>
        </div>
    </div>

    <div class="rounded-lg border overflow-x-auto bg-white dark:bg-neutral-900">
        <table class="min-w-full text-sm">
            <thead>
            <tr class="text-left text-neutral-500">
                <th class="py-2 px-3">{{ __('procflow::po.table.po_number') }}</th>
                <th class="py-2 px-3">{{ __('procflow::po.table.supplier') }}</th>
                <th class="py-2 px-3">{{ __('procflow::po.table.requester') }}</th>
                <th class="py-2 px-3">{{ __('procflow::po.table.status') }}</th>
                <th class="py-2 px-3">{{ __('procflow::po.table.total') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse($this->orders as $po)
                <tr class="border-t hover:bg-neutral-50 dark:hover:bg-neutral-800">
                    <td class="py-2 px-3">
                        <a class="text-blue-600 hover:underline"
                           href="{{ route('procurement.purchase-orders.show', ['po' => $po->id]) }}">
                            {{ $po->po_number ?? __('procflow::po.labels.draft_with_id', ['id' => $po->id]) }}
                        </a>
                    </td>
                    <td class="py-2 px-3">{{ $po->supplier->name ?? '-' }}</td>
                    <td class="py-2 px-3">{{ $po->requester->name ?? '-' }}</td>
                    <td class="py-2 px-3">
                        @php $status = is_string($po->status) ? $po->status : ($po->status->value ?? 'draft'); @endphp
                        @php
                            $color = match ($status) {
                                'closed' => 'green',
                                'issued' => 'yellow',
                                'receiving' => 'cyan',
                                'canceled' => 'red',
                                default  => 'zinc',
                            };
                        @endphp
                        <flux:badge color="{{ $color }}" size="sm">{{ __('procflow::po.status.' . $status) }}</flux:badge>
                    </td>
                    <td class="py-2 px-3">{{ \Lastdino\ProcurementFlow\Support\Format::moneyTotal($po->total) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-6 text-center text-neutral-500">{{ __('procflow::po.table.empty') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <!-- 履歴ダウンロードモーダル -->
    <flux:modal wire:model.self="showExportModal" name="export-history" class="w-full md:w-[40rem] max-w-full">
        <x-slot name="title">{{ __('procflow::po.export.modal.title') }}</x-slot>

        <div class="space-y-4">
            <p class="text-sm text-neutral-600 dark:text-neutral-300">{{ __('procflow::po.export.modal.description') }}</p>

            <flux:field>
                <flux:label>{{ __('procflow::po.export.fields.receiving_from_to') }}</flux:label>
                <div class="flex items-center gap-2">
                    <div class="w-full">
                        <input type="date" class="w-full border rounded p-2 bg-white dark:bg-neutral-900" wire:model.live="receivingDate.start" placeholder="{{ __('procflow::po.export.fields.receiving_from') }}">
                    </div>
                    <div class="w-full">
                        <input type="date" class="w-full border rounded p-2 bg-white dark:bg-neutral-900" wire:model.live="receivingDate.end" placeholder="{{ __('procflow::po.export.fields.receiving_to') }}">
                    </div>
                </div>
                @error('receivingDate')
                    <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                @enderror
            </flux:field>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <flux:field>
                    <flux:label>{{ __('procflow::po.export.fields.row_group') }}</flux:label>
                    <flux:select wire:model.live="rowGroupId">
                        <option value="">{{ __('procflow::po.export.fields.row_auto') }}</option>
                        @foreach($this->optionGroups as $grp)
                            <option value="{{ $grp->id }}">{{ $grp->name }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('procflow::po.export.fields.col_group') }}</flux:label>
                    <flux:select wire:model.live="colGroupId">
                        <option value="">{{ __('procflow::po.export.fields.col_none') }}</option>
                        @foreach($this->optionGroups as $grp)
                            <option value="{{ $grp->id }}">{{ $grp->name }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('procflow::po.export.fields.aggregate_type') }}</flux:label>
                <div class="flex items-center gap-3">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="radio" value="amount" class="accent-blue-600" wire:model.live="aggregateType">
                        {{ __('procflow::po.export.aggregate.amount') }}
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="radio" value="quantity" class="accent-blue-600" wire:model.live="aggregateType">
                        {{ __('procflow::po.export.aggregate.quantity') }}
                    </label>
                </div>
                @error('aggregateType')
                    <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                @enderror
            </flux:field>
        </div>
        <div class="flex items-center justify-end gap-2 mt-4">
            <flux:button variant="ghost" wire:click="$set('showExportModal', false)">{{ __('procflow::po.buttons.cancel') }}</flux:button>
            <flux:button variant="primary" wire:click="exportExcel" wire:target="exportExcel" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="exportExcel">{{ __('procflow::po.export.buttons.download') }}</span>
                <span wire:loading wire:target="exportExcel">{{ __('procflow::po.export.buttons.creating') }}</span>
            </flux:button>
        </div>
    </flux:modal>

    <div class="flex justify-end">
        {{ $this->orders->links() }}
    </div>

    {{-- Create PO Modal (Flux UI) --}}
    <flux:modal wire:model.self="showPoModal" name="create-po" class="w-full md:w-[64rem] max-w-full" :dismissible="false">
        <div class="w-full md:w-[64rem] max-w-full">
            <h3 class="text-lg font-semibold mb-3">{{ __('procflow::po.create.title') }}</h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div class="md:col-span-1">
                    <label class="block text-sm text-neutral-600 mb-1">{{ __('procflow::po.create.mode.title') }}</label>
                    <div class="text-sm text-neutral-700 dark:text-neutral-300">{{ __('procflow::po.create.mode.material_based') }}</div>
                </div>
                <div class="md:col-span-1">
                    <label class="block text-sm text-neutral-600 mb-1">{{ __('procflow::po.common.expected_date') }}</label>
                    <input type="date" class="w-full border rounded p-2 bg-white dark:bg-neutral-900" wire:model.live="poForm.expected_date">
                </div>
                <div class="md:col-span-1">
                    <label class="block text-sm text-neutral-600 mb-1">{{ __('procflow::po.common.requester') }}</label>
                    <input type="text" class="w-full border rounded p-2 bg-neutral-100 dark:bg-neutral-800" value="{{ auth()->user()->name ?? '—' }}" disabled>
                </div>
            </div>
            <flux:textarea
                label="{{ __('procflow::po.create.delivery.label') }}"
                placeholder="{{ __('procflow::po.create.delivery.placeholder') }}"
                wire:model.live="poForm.delivery_location"
            />

            {{-- 自動送信可否はサプライヤー設定に基づいて決定するため、UIでの選択肢は廃止 --}}

            <div class="space-y-3 mt-3">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-neutral-600">{{ __('procflow::po.create.items.title') }}</div>
                    <flux:button size="sm" variant="outline" wire:click="addPoItem">{{ __('procflow::po.create.items.add_row') }}</flux:button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-neutral-500">
                                <th class="py-2 px-3">{{ __('procflow::po.create.table.material') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.create.table.description') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.create.table.note') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.create.table.unit') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.create.table.qty') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.create.table.unit_price') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.create.table.tax_rate') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.create.table.desired_date') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.create.table.expected_date') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.create.table.options') }}</th>
                                <th class="py-2 px-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($poForm['items'] as $i => $row)
                                <tr class="border-t">
                                    <td class="py-2 px-3">
                                        <flux:select class="min-w-48" placeholder="{{ __('procflow::po.common.choose_material_placeholder') }}" wire:model.live="poForm.items.{{ $i }}.material_id" wire:change="onMaterialChanged({{ $i }}, $event.target.value)">
                                            @foreach($this->materials as $m)
                                                <flux:select.option value="{{ $m->id }}">{{ $m->sku }} - {{ $m->name }}</flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    </td>
                                    <td class="py-2 px-3">
                                        <flux:textarea
                                            rows="2"
                                            placeholder="{{ __('procflow::po.adhoc.placeholders.description_hint') }}"
                                            wire:model="poForm.items.{{ $i }}.description"
                                            class="min-w-64"
                                        />
                                    </td>
                                    <td class="py-2 px-3">
                                        <flux:textarea
                                            rows="2"
                                            placeholder="{{ __('procflow::po.common.note') }}"
                                            wire:model.live="poForm.items.{{ $i }}.note"
                                            class="min-w-56"
                                        />
                                    </td>
                                    <td class="py-2 px-3 w-28">
                                        <div class="w-28">
                                            <flux:input wire:model="poForm.items.{{ $i }}.unit_purchase" placeholder="{{ __('procflow::po.common.unit_example') }}"/>
                                        </div>
                                    </td>
                                    <td class="py-2 px-3">
                                        <div class="w-28">
                                            <flux:input class="w-28" type="number" wire:model="poForm.items.{{ $i }}.qty_ordered"/>
                                        </div>
                                    </td>
                                    <td class="py-2 px-3">
                                        <div class="w-28">
                                            <flux:input class="w-28" type="number" wire:model.live="poForm.items.{{ $i }}.price_unit"/>
                                        </div>
                                    </td>
                                    <td class="py-2 px-3">
                                        <div class="w-28">
                                            <flux:input class="w-28" type="number" step="0.0001" min="0" max="1" wire:model.live="poForm.items.{{ $i }}.tax_rate"/>
                                        </div>
                                    </td>
                                    <td class="py-2 px-3">
                                        <div class="w-28">
                                            <flux:input class="w-40" type="date" wire:model.live="poForm.items.{{ $i }}.desired_date"/>
                                        </div>
                                    </td>
                                    <td class="py-2 px-3">
                                        <div class="w-28">
                                            <flux:input class="w-40" type="date" wire:model.live="poForm.items.{{ $i }}.expected_date"/>
                                        </div>
                                    </td>
                                    <td class="py-2 px-3 align-top">
                                        @if ($this->activeGroups && $this->activeGroups->isNotEmpty())
                                            <div class="grid gap-2">
                                                @foreach ($this->activeGroups as $g)
                                                    <div class="flex items-center gap-2">
                                                        <flux:select wire:model.live="poForm.items.{{ $i }}.options.{{ $g->id }}" placeholder="{{ __('procflow::po.common.choose_placeholder') }}" label="{{ $g->name }}" class="min-w-56" >
                                                            <flux:select.option value="">—</flux:select.option>
                                                            @foreach (($this->activeOptionsByGroup[$g->id] ?? []) as $opt)
                                                                <flux:select.option value="{{ $opt['id'] }}">{{ $opt['name'] }}</flux:select.option>
                                                            @endforeach
                                                        </flux:select>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-neutral-400 text-sm">{{ __('procflow::po.create.options.no_active_groups') }}</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-3 text-right">
                                        <flux:button size="xs" variant="outline" wire:click="removePoItem({{ $i }})">{{ __('procflow::po.common.remove') }}</flux:button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Supplier grouping preview --}}
            <div class="mt-4">
                <div class="text-sm text-neutral-600 mb-2">{{ __('procflow::po.create_preview.title') }}</div>
                @php $preview = $this->poSupplierPreview; @endphp
                @if (!empty($preview))
                    <div class="rounded border border-amber-300 bg-amber-50 dark:bg-amber-900/20 p-3 text-amber-800 dark:text-amber-200">
                        <div class="font-medium mb-2">{{ __('procflow::po.create_preview.split_notice', ['count' => count($preview)]) }}</div>
                        <ul class="text-sm grid md:grid-cols-2 gap-2">
                            @foreach ($preview as $p)
                                <li class="flex items-center justify-between rounded border border-amber-200 dark:border-amber-800 bg-white dark:bg-neutral-900 px-3 py-2">
                                    <div>
                                        <div class="font-semibold">{{ $p['name'] }}</div>
                                        <div class="text-neutral-500">{{ __('procflow::po.create_preview.lines') }}: {{ $p['lines'] }}</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-neutral-500">{{ __('procflow::po.create_preview.subtotal_excl_tax') }}</div>
                                        <div class="font-semibold">{{ \Lastdino\ProcurementFlow\Support\Format::moneySubtotal($p['subtotal']) }}</div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <div class="text-sm text-neutral-500">{{ __('procflow::po.create_preview.select_materials_hint') }}</div>
                @endif

                <div class="text-xs text-neutral-500 mt-2">{{ __('procflow::po.create_preview.no_adhoc_hint') }}</div>
            </div>

            <div class="mt-4 flex items-center justify-end gap-3">
                <flux:button variant="outline" x-on:click="$flux.modal('create-po').close()">{{ __('procflow::po.buttons.cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="savePoFromModal" wire:loading.attr="disabled">
                    <span wire:loading.remove>{{ __('procflow::po.buttons.create') }}</span>
                    <span wire:loading>{{ __('procflow::po.buttons.creating') }}</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>


    {{-- Ad-hoc PO Modal (no material master) --}}
    <flux:modal wire:model.self="showAdhocPoModal" name="create-adhoc-po" class="w-full md:w-[64rem] max-w-full" :dismissible="false">
        <div class="w-full md:w-[64rem] max-w-full">
            <h3 class="text-lg font-semibold mb-3">{{ __('procflow::po.adhoc.title') }}</h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-sm text-neutral-600 mb-1">{{ __('procflow::po.adhoc.form.supplier') }}</label>
                    <select class="w-full border rounded p-2 bg-white dark:bg-neutral-900" wire:model.live="adhocForm.supplier_id">
                        <option value="">{{ __('procflow::po.common.choose_placeholder') }}</option>
                        @foreach($this->suppliers as $sup)
                            <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                        @endforeach
                    </select>
                    @error('adhocForm.supplier_id') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="block text-sm text-neutral-600 mb-1">{{ __('procflow::po.common.expected_date') }}</label>
                    <input type="date" class="w-full border rounded p-2 bg-white dark:bg-neutral-900" wire:model.live="adhocForm.expected_date">
                </div>
                <div>
                    <label class="block text-sm text-neutral-600 mb-1">{{ __('procflow::po.common.requester') }}</label>
                    <input type="text" class="w-full border rounded p-2 bg-neutral-100 dark:bg-neutral-800" value="{{ auth()->user()->name ?? '—' }}" disabled>
                </div>
            </div>
            <flux:textarea
                label="{{ __('procflow::po.adhoc.delivery.label') }}"
                placeholder="{{ __('procflow::po.adhoc.delivery.placeholder') }}"
                wire:model.live="adhocForm.delivery_location"
            />

            <div class="space-y-3 mt-3">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-neutral-600">{{ __('procflow::po.adhoc.items.title') }}</div>
                    <flux:button size="sm" variant="outline" wire:click="addAdhocItem">{{ __('procflow::po.adhoc.items.add_row') }}</flux:button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-neutral-500">
                                <th class="py-2 px-3">{{ __('procflow::po.adhoc.table.description') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.adhoc.table.manufacturer') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.adhoc.table.note') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.adhoc.table.unit') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.adhoc.table.qty') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.adhoc.table.unit_price') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.adhoc.table.tax_rate') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.adhoc.table.desired_date') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.adhoc.table.expected_date') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.adhoc.table.options') }}</th>
                                <th class="py-2 px-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($adhocForm['items'] as $i => $row)
                                <tr class="border-t">
                                    <td class="py-2 px-3">
                                        <flux:textarea
                                            rows="2"
                                            placeholder="{{ __('procflow::po.adhoc.placeholders.description_hint') }}"
                                            wire:model.live="adhocForm.items.{{ $i }}.description"
                                            class="min-w-64"
                                        />
                                    </td>
                                    <td class="py-2 px-3">
                                        <div class="w-40">
                                            <flux:input
                                                placeholder="{{ __('procflow::po.adhoc.placeholders.manufacturer_hint') }}"
                                                wire:model.live="adhocForm.items.{{ $i }}.manufacturer"
                                            />
                                        </div>
                                    </td>
                                    <td class="py-2 px-3">
                                        <flux:textarea
                                            rows="2"
                                            placeholder="{{ __('procflow::po.common.note') }}"
                                            wire:model.live="adhocForm.items.{{ $i }}.note"
                                            class="min-w-56"
                                        />
                                    </td>
                                    <td class="py-2 px-3 w-28">
                                        <div class="w-28">
                                            <flux:input wire:model.live="adhocForm.items.{{ $i }}.unit_purchase" placeholder="{{ __('procflow::po.common.unit_example') }}"/>
                                        </div>
                                    </td>
                                    <td class="py-2 px-3">
                                        <div class="w-28">
                                            <flux:input class="w-28" type="number" wire:model.live="adhocForm.items.{{ $i }}.qty_ordered"/>
                                        </div>
                                    </td>
                                    <td class="py-2 px-3">
                                        <div class="w-28">
                                            <flux:input class="w-28" type="number" wire:model.live="adhocForm.items.{{ $i }}.price_unit"/>
                                        </div>
                                    </td>
                                    <td class="py-2 px-3">
                                        <div class="w-28">
                                            <flux:input class="w-28" type="number" step="0.0001" min="0" max="1" wire:model.live="adhocForm.items.{{ $i }}.tax_rate"/>
                                        </div>
                                    </td>
                                    <td class="py-2 px-3">
                                        <div class="w-28">
                                            <flux:input class="w-40" type="date" wire:model.live="adhocForm.items.{{ $i }}.desired_date"/>
                                        </div>
                                    </td>
                                    <td class="py-2 px-3">
                                        <div class="w-28">
                                            <flux:input class="w-40" type="date" wire:model.live="adhocForm.items.{{ $i }}.expected_date"/>
                                        </div>
                                    </td>
                                    <td class="py-2 px-3 align-top">
                                        @if ($this->activeGroups && $this->activeGroups->isNotEmpty())
                                            <div class="grid gap-2">
                                                @foreach ($this->activeGroups as $g)
                                                    <div class="flex items-center gap-2">
                                                        <flux:select wire:model.live="adhocForm.items.{{ $i }}.options.{{ $g->id }}" placeholder="{{ __('procflow::po.common.choose_placeholder') }}" label="{{ $g->name }}" class="min-w-56" >
                                                            <flux:select.option value="">—</flux:select.option>
                                                            @foreach (($this->activeOptionsByGroup[$g->id] ?? []) as $opt)
                                                                <flux:select.option value="{{ $opt['id'] }}">{{ $opt['name'] }}</flux:select.option>
                                                            @endforeach
                                                        </flux:select>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-neutral-400 text-sm">{{ __('procflow::po.adhoc.options.no_active_groups') }}</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-3">
                                        <flux:button size="xs" variant="ghost" wire:click="removeAdhocItem({{ $i }})">{{ __('procflow::po.common.remove') }}</flux:button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4 flex items-center justify-end gap-3">
                <flux:button variant="outline" x-on:click="$flux.modal('create-adhoc-po').close()">{{ __('procflow::po.buttons.cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="saveAdhocPoFromModal" wire:loading.attr="disabled">
                    <span wire:loading.remove>{{ __('procflow::po.buttons.save') }}</span>
                    <span wire:loading>{{ __('procflow::po.buttons.saving') }}</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>

