<div class="p-6 space-y-6">
    <x-procflow::topmenu />

    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">{{ __('procflow::materials.issue.title_prefix') }}: {{ $this->material->sku }} — {{ $this->material->name }}</h1>
        <a class="px-3 py-1.5 rounded bg-neutral-200 dark:bg-neutral-800" href="{{ route('procurement.materials.show', ['material' => $this->material->id]) }}">{{ __('procflow::materials.issue.back') }}</a>
    </div>

    @if ($message)
        @if ($ok)
            <flux:callout variant="success">{{ $message }}</flux:callout>
        @else
            <flux:callout variant="danger">{{ $message }}</flux:callout>
        @endif
    @endif

    @if ($this->material->manage_by_lot)
        <div class="rounded border bg-white dark:bg-neutral-900">
            <div class="p-4 border-b flex items-center justify-between">
                <h2 class="text-lg font-medium">{{ __('procflow::materials.issue.from_lot_title') }}</h2>
                <div class="text-sm text-neutral-600">{{ __('procflow::materials.issue.unit_label') }}: {{ $this->material->unit_stock }}</div>
            </div>
            <div class="p-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-neutral-500">
                            <th class="py-2 px-3">{{ __('procflow::materials.issue.table.lot_no') }}</th>
                            <th class="py-2 px-3">{{ __('procflow::materials.issue.table.stock') }}</th>
                            <th class="py-2 px-3">{{ __('procflow::materials.issue.table.expiry') }}</th>
                            <th class="py-2 px-3">{{ __('procflow::materials.issue.table.qty') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($this->lots as $lot)
                        <tr class="border-t {{ $this->prefLotId === (int) $lot->id ? 'bg-blue-50/40 dark:bg-blue-950/20' : '' }}"
                            data-lot-id="{{ $lot->id }}" @if($this->prefLotId === (int) $lot->id) data-selected="true" @endif>
                            <td class="py-2 px-3">{{ $lot->lot_no }}</td>
                            <td class="py-2 px-3">{{ (float) $lot->qty_on_hand }} {{ $lot->unit }}</td>
                            <td class="py-2 px-3">{{ $lot->expiry_date ?? '-' }}</td>
                            <td class="py-2 px-3">
                                <input type="number" min="0" step="0.000001"
                                       class="w-36 border rounded p-1 bg-white dark:bg-neutral-900"
                                       wire:model.lazy="lotQty.{{ $lot->id }}"
                                       @if($this->prefLotId === (int) $lot->id) x-data x-init="$el.focus()" @endif />
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-6 text-center text-neutral-500">{{ __('procflow::materials.issue.table.empty') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t space-y-3">
                <div class="max-w-xl">
                    <label class="block text-sm text-neutral-600 mb-1">{{ __('procflow::materials.issue.reason_label') }}</label>
                    <flux:textarea wire:model.defer="reason" placeholder="{{ __('procflow::materials.issue.reason_placeholder') }}" rows="2" />
                    @error('reason')
                        <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                    @enderror
                </div>
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" wire:click="issue" wire:loading.attr="disabled">
                        <span wire:loading.remove>{{ __('procflow::materials.issue.submit') }}</span>
                        <span wire:loading>{{ __('procflow::materials.issue.processing') }}</span>
                    </flux:button>
                </div>
            </div>
        </div>
    @else
        <div class="rounded border bg-white dark:bg-neutral-900 p-4 space-y-4">
            <h2 class="text-lg font-medium">{{ __('procflow::materials.issue.non_lot.qty_title') }}</h2>
            <div class="grid md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-sm text-neutral-600 mb-1">{{ __('procflow::materials.issue.non_lot.qty_label') }} ({{ $this->material->unit_stock }})</label>
                    <input type="number" min="0" step="0.000001" class="w-48 border rounded p-2 bg-white dark:bg-neutral-900" wire:model.lazy="nonLotQty" />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm text-neutral-600 mb-1">{{ __('procflow::materials.issue.reason_label') }}</label>
                    <flux:textarea wire:model.defer="reason" placeholder="{{ __('procflow::materials.issue.reason_placeholder') }}" rows="2" />
                    @error('reason')
                        <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                    @enderror
                </div>
                <div class="self-end">
                    <flux:button variant="primary" wire:click="issue" wire:loading.attr="disabled">
                        <span wire:loading.remove>{{ __('procflow::materials.issue.submit') }}</span>
                        <span wire:loading>{{ __('procflow::materials.issue.processing') }}</span>
                    </flux:button>
                </div>
            </div>
            <div class="text-sm text-neutral-600">{{ __('procflow::materials.issue.non_lot.current_stock') }}: {{ (float) ($this->material->current_stock ?? 0) }} {{ $this->material->unit_stock }}</div>
        </div>
    @endif
</div>
