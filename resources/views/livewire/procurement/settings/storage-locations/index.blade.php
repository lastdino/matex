<?php

use Lastdino\ProcurementFlow\Models\StorageLocation;
use Livewire\Component;

new class extends Component
{
    public bool $openModal = false;

    public ?int $editingId = null;

    public string $name = '';
    public string $fire_service_law_category = '';
    public string $max_specified_quantity_ratio = '';
    public string $description = '';
    public bool $is_active = true;

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'fire_service_law_category' => ['nullable', 'string', 'max:100'],
            'max_specified_quantity_ratio' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->openModal = true;
    }

    public function openEdit(int $id): void
    {
        $loc = StorageLocation::query()->findOrFail($id);
        $this->editingId = $loc->id;
        $this->name = (string) $loc->name;
        $this->fire_service_law_category = (string) ($loc->fire_service_law_category ?? '');
        $this->max_specified_quantity_ratio = (string) ($loc->max_specified_quantity_ratio ?? '');
        $this->description = (string) ($loc->description ?? '');
        $this->is_active = (bool) $loc->is_active;
        $this->openModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $payload = [
            'name' => $this->name,
            'fire_service_law_category' => $this->fire_service_law_category ?: null,
            'max_specified_quantity_ratio' => $this->max_specified_quantity_ratio !== '' ? (float) $this->max_specified_quantity_ratio : null,
            'description' => $this->description ?: null,
            'is_active' => $this->is_active,
        ];

        if ($this->editingId) {
            $loc = StorageLocation::query()->findOrFail($this->editingId);
            $loc->fill($payload)->save();
            $this->dispatch('notify', text: __('procflow::settings.storage_locations.flash.updated'));
        } else {
            StorageLocation::query()->create($payload);
            $this->dispatch('notify', text: __('procflow::settings.storage_locations.flash.created'));
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        $loc = StorageLocation::query()->findOrFail($id);
        $loc->delete();
        $this->dispatch('notify', text: __('procflow::settings.storage_locations.flash.deleted'));
    }

    public function closeModal(): void
    {
        $this->openModal = false;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->fire_service_law_category = '';
        $this->max_specified_quantity_ratio = '';
        $this->description = '';
        $this->is_active = true;
    }

    /**
     * @return array<int, array>
     */
    public function getLocationsProperty(): array
    {
        return StorageLocation::query()
            ->orderBy('name')
            ->get()
            ->map(fn (StorageLocation $loc) => [
                'id' => (int) $loc->id,
                'name' => (string) $loc->name,
                'fire_service_law_category' => (string) $loc->fire_service_law_category,
                'max_ratio' => (float) $loc->max_specified_quantity_ratio,
                'current_ratio' => $loc->currentSpecifiedQuantityRatio(),
                'is_over' => $loc->isOverLimit(),
                'usage_percent' => $loc->limitUsagePercentage(),
            ])->all();
    }
};

?>

<div class="p-6 space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">{{ __('procflow::settings.storage_locations.title') }}</h1>
        <a href="{{ route('procurement.dashboard') }}" class="text-blue-600 hover:underline">{{ __('procflow::common.back') }}</a>
    </div>

    <div class="flex justify-end">
        <flux:button variant="primary" wire:click="openCreate">{{ __('procflow::settings.storage_locations.new') }}</flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('procflow::settings.storage_locations.fields.name') }}</flux:table.column>
            <flux:table.column>{{ __('procflow::settings.storage_locations.fields.fire_service_law_category') }}</flux:table.column>
            <flux:table.column align="center">{{ __('procflow::settings.storage_locations.fields.current_ratio') }}</flux:table.column>
            <flux:table.column align="end">{{ __('procflow::common.actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->locations as $loc)
                <flux:table.row>
                    <flux:table.cell class="font-medium">
                        {{ $loc['name'] }}
                    </flux:table.cell>
                    <flux:table.cell>{{ $loc['fire_service_law_category'] }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-col items-center gap-1">
                            <span class="{{ $loc['is_over'] ? 'text-red-600 font-bold' : ($loc['usage_percent'] > 80 ? 'text-orange-500 font-bold' : 'text-neutral-700') }}">
                                {{ number_format($loc['current_ratio'], 3) }} / {{ $loc['max_ratio'] > 0 ? number_format($loc['max_ratio'], 2) : '∞' }}
                            </span>
                            @if($loc['max_ratio'] > 0)
                                <div class="w-24 h-2 bg-gray-200 rounded-full overflow-hidden">
                                    <div class="h-full {{ $loc['is_over'] ? 'bg-red-500' : ($loc['usage_percent'] > 80 ? 'bg-orange-400' : 'bg-green-500') }}" style="width: {{ min(100, $loc['usage_percent']) }}%"></div>
                                </div>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end gap-2">
                            <flux:button size="xs" variant="outline" wire:click="openEdit({{ $loc['id'] }})">{{ __('procflow::common.edit') }}</flux:button>
                            <flux:button size="xs" variant="danger" wire:click="delete({{ $loc['id'] }})" wire:confirm="{{ __('procflow::common.confirm_delete') }}">{{ __('procflow::common.delete') }}</flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="text-center text-neutral-500 py-6">{{ __('procflow::settings.storage_locations.empty') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model="openModal" class="w-full md:w-[35rem]">
        <div class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? __('procflow::settings.storage_locations.edit_title') : __('procflow::settings.storage_locations.create_title') }}</flux:heading>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:input wire:model="name" label="{{ __('procflow::settings.storage_locations.fields.name') }}" required />
                <flux:input wire:model="fire_service_law_category" label="{{ __('procflow::settings.storage_locations.fields.fire_service_law_category') }}" placeholder="例: 屋内貯蔵所, 一般取扱所" />
                <flux:input type="number" step="0.01" wire:model="max_specified_quantity_ratio" label="{{ __('procflow::settings.storage_locations.fields.max_specified_quantity_ratio') }}" placeholder="例: 1.0" />
            </div>

            <flux:textarea wire:model="description" label="{{ __('procflow::settings.storage_locations.fields.description') }}" />

            <flux:checkbox wire:model="is_active" label="{{ __('procflow::settings.storage_locations.fields.is_active') }}" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="closeModal">{{ __('procflow::common.cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="save" wire:loading.attr="disabled">{{ __('procflow::common.save') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
