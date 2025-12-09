<div class="p-6 space-y-4" x-data @focus-token.window="$refs.token?.focus(); $refs.token?.select()">
    <x-procflow::topmenu />
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">{{ __('procflow::receiving.title') }}</h1>
        <a href="{{ route('procurement.purchase-orders.index') }}" class="text-blue-600 hover:underline">{{ __('procflow::receiving.back_to_list') }}</a>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <div class="rounded border p-4 space-y-4">
            <flux:heading size="sm">{{ __('procflow::receiving.token') }}</flux:heading>
            <flux:field>
                <flux:input
                    id="token"
                    x-ref="token"
                    wire:model.live.debounce.300ms="form.token"
                    placeholder="{{ __('procflow::receiving.token_placeholder') }}"
                />
            </flux:field>
            <div class="flex gap-2">
                <flux:button
                    variant="outline"
                    wire:click="lookup"
                    wire:loading.attr="disabled"
                    wire:target="lookup"
                >{{ __('procflow::receiving.lookup') }}</flux:button>
            </div>

            @if ($message)
                @if ($ok)
                    <flux:callout variant="success" class="mt-2">{{ $message }}</flux:callout>
                @else
                    <flux:callout variant="danger" class="mt-2">{{ $message }}</flux:callout>
                @endif
            @endif
        </div>

        <div class="rounded border p-4 space-y-4">
            <flux:heading size="sm">{{ __('procflow::receiving.info') }}</flux:heading>

            @if ($this->hasInfo)
                <div class="text-sm text-gray-700 space-y-1">
                    <div>{{ __('procflow::receiving.info_po') }}: <span class="font-medium">{{ $info['po_number'] }}</span> (<span>{{ $info['po_status'] }}</span>)</div>
                    <div>{{ __('procflow::receiving.info_material') }}: <span class="font-medium">{{ $info['material_name'] }}</span> [<span>{{ $info['material_sku'] }}</span>]</div>
                    <div>{{ __('procflow::receiving.info_remaining_base') }}: <span class="font-medium">{{ $info['remaining_base'] }}</span></div>
                </div>
            @endif

            <div class="grid gap-3 md:grid-cols-2">
                <flux:input type="number" step="0.000001" min="0" wire:model.number="form.qty" label="{{ __('procflow::receiving.qty_received') }}"/>
                <flux:input wire:model="form.reference_number" label="{{ __('procflow::receiving.reference_number') }}"/>
            </div>

            @if ($this->hasInfo && ($info['manage_by_lot'] ?? false))
                <div class="mt-4 space-y-3">
                    <flux:heading size="xs">{{ __('procflow::receiving.lot_section_title') }}</flux:heading>
                    <div class="grid gap-3 md:grid-cols-3">
                        <flux:input wire:model="form.lot_no" label="{{ __('procflow::receiving.lot_no') }}" placeholder="{{ __('procflow::receiving.lot_no_placeholder') }}"/>
                        <flux:input type="date" wire:model="form.mfg_date" label="{{ __('procflow::receiving.mfg_date') }}"/>
                        <flux:input type="date" wire:model="form.expiry_date" label="{{ __('procflow::receiving.expiry_date') }}"/>
                    </div>
                    <p class="text-xs text-gray-500">{{ __('procflow::receiving.lot_notice') }}</p>
                </div>
            @endif
            <flux:button
                variant="primary"
                wire:click="receive"
                wire:loading.attr="disabled"
                wire:target="receive"
            >{{ __('procflow::receiving.receive') }}</flux:button>
        </div>
    </div>
</div>
