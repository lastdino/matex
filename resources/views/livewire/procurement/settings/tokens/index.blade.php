<div class="p-6 space-y-6">
    <x-procflow::topmenu />

    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">{{ __('procflow::settings.tokens.title') }}</h1>
        <div class="flex gap-2">
            <a href="{{ route('procurement.settings.labels') }}" class="text-blue-600 hover:underline">{{ __('procflow::settings.tokens.to_labels') }}</a>
            <flux:button variant="primary" wire:click="creating">{{ __('procflow::settings.tokens.buttons.new') }}</flux:button>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-4 items-end">
        <flux:field class="md:col-span-2">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('procflow::settings.tokens.filters.search_placeholder') }}" />
        </flux:field>
        <flux:field>
            <flux:select wire:model.live="materialId">
                <option value="">{{ __('procflow::settings.tokens.filters.all_materials') }}</option>
                @foreach($materials as $m)
                    <option value="{{ $m->id }}">{{ $m->name }} ({{ $m->sku }})</option>
                @endforeach
            </flux:select>
        </flux:field>
        <flux:field>
            <flux:select wire:model.live="enabled">
                <option value="">{{ __('procflow::settings.tokens.filters.enabled_all') }}</option>
                <option value="1">{{ __('procflow::settings.tokens.filters.enabled') }}</option>
                <option value="0">{{ __('procflow::settings.tokens.filters.disabled') }}</option>
            </flux:select>
        </flux:field>
    </div>

    <div class="rounded border divide-y">
        <div class="grid grid-cols-12 gap-2 p-3 text-sm font-medium text-gray-600">
            <div class="col-span-3">{{ __('procflow::settings.tokens.table.token') }}</div>
            <div class="col-span-3">{{ __('procflow::settings.tokens.table.material') }}</div>
            <div class="col-span-2">{{ __('procflow::settings.tokens.table.unit_qty') }}</div>
            <div class="col-span-2">{{ __('procflow::settings.tokens.table.expires') }}</div>
            <div class="col-span-2 text-right">{{ __('procflow::settings.tokens.table.actions') }}</div>
        </div>
        @forelse($rows as $row)
            <div class="grid grid-cols-12 gap-2 p-3 items-center">
                <div class="col-span-3">
                    <div class="font-mono text-sm">{{ $row->token }}</div>
                    <div class="text-xs text-gray-500">{{ __('procflow::settings.tokens.labels.id') }}: {{ $row->id }}</div>
                </div>
                <div class="col-span-3">
                    <div class="font-medium">{{ $row->material?->name }}</div>
                    <div class="text-xs text-gray-500">{{ $row->material?->sku }}</div>
                </div>
                <div class="col-span-2 text-sm">
                    <div>{{ __('procflow::settings.tokens.labels.unit') }}: {{ $row->unit_purchase ?? '-' }}</div>
                    <div>{{ __('procflow::settings.tokens.labels.default_qty') }}: {{ $row->default_qty ?? '-' }}</div>
                </div>
                <div class="col-span-2 text-sm">
                    {{ $row->expires_at?->format('Y-m-d H:i') ?? '-' }}
                </div>
                <div class="col-span-2 flex justify-end gap-2">
                    <flux:button size="xs" variant="outline" wire:click="edit({{ $row->id }})">{{ __('procflow::settings.tokens.buttons.edit') }}</flux:button>
                    <flux:button size="xs" variant="outline" wire:click="toggle({{ $row->id }})">
                        {{ $row->enabled ? __('procflow::settings.tokens.buttons.disable') : __('procflow::settings.tokens.buttons.enable') }}
                    </flux:button>
                    <flux:button size="xs" variant="danger" wire:click="delete({{ $row->id }})">{{ __('procflow::settings.tokens.buttons.delete') }}</flux:button>
                </div>
            </div>
        @empty
            <div class="p-6 text-sm text-gray-600">{{ __('procflow::settings.tokens.table.empty') }}</div>
        @endforelse
    </div>

    <div>
        {{ $rows->links() }}
    </div>

    <flux:modal wire:model="showForm">
        <flux:heading size="sm">{{ $editingId ? __('procflow::settings.tokens.modal.title_edit') : __('procflow::settings.tokens.modal.title_create') }}</flux:heading>
        <div class="space-y-3">
            <flux:input wire:model.defer="form.token" label="{{ __('procflow::settings.tokens.modal.token') }}"/>
            <flux:select wire:model.defer="form.material_id" label="{{ __('procflow::settings.tokens.modal.material') }}">
                <option value="">{{ __('procflow::settings.tokens.modal.select_placeholder') }}</option>
                @foreach($materials as $m)
                    <option value="{{ $m->id }}">{{ $m->name }} ({{ $m->sku }})</option>
                @endforeach
            </flux:select>
            <div class="grid md:grid-cols-3 gap-3">
                <flux:input wire:model.defer="form.unit_purchase" placeholder="e.g. case" label="{{ __('procflow::settings.tokens.modal.unit_purchase') }}"/>
                <flux:input type="number" step="0.000001" min="0" wire:model.defer="form.default_qty" label="{{ __('procflow::settings.tokens.modal.default_qty') }}"/>
                <flux:switch wire:model.defer="form.enabled" label="{{ __('procflow::settings.tokens.modal.enabled') }}"/>
            </div>
            <flux:input type="datetime-local" wire:model.defer="form.expires_at" label="{{ __('procflow::settings.tokens.modal.expires_at') }}"/>
        </div>
        <div class="mt-4 flex justify-end gap-2">
            <flux:button variant="outline" wire:click="$set('showForm', false)">{{ __('procflow::settings.tokens.buttons.cancel') }}</flux:button>
            <flux:button variant="primary" wire:click="save">{{ __('procflow::settings.tokens.buttons.save') }}</flux:button>
        </div>
    </flux:modal>
</div>
