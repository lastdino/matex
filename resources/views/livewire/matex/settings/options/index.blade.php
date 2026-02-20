<?php

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Lastdino\Matex\Models\Option;
use Lastdino\Matex\Models\OptionGroup;
use Lastdino\Matex\Models\PurchaseOrderItemOptionValue;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $groupSearch = '';

    public string $optionSearch = '';

    public ?int $selectedGroupId = null;

    // Group form state
    public array $groupForm = [
        'id' => null,
        'name' => '',
        'description' => '',
        'is_active' => true,
        'sort_order' => 0,
    ];

    // Option form state
    public array $optionForm = [
        'id' => null,
        'group_id' => null,
        'code' => '',
        'name' => '',
        'description' => '',
        'is_active' => true,
        'sort_order' => 0,
    ];

    public bool $showGroupModal = false;

    public bool $showOptionModal = false;

    public function mount(): void
    {
        if ($this->selectedGroupId === null) {
            $first = OptionGroup::query()->orderBy('sort_order')->orderBy('name')->first();
            $this->selectedGroupId = $first?->getKey();
        }
    }

    #[Computed]
    public function groups(): LengthAwarePaginator
    {
        return OptionGroup::query()
            ->when($this->groupSearch !== '', function (Builder $q): void {
                $q->where('name', 'like', "%{$this->groupSearch}%");
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(10, pageName: 'groups');
    }

    #[Computed]
    public function options(): LengthAwarePaginator
    {
        if (! $this->selectedGroupId) {
            return Option::query()->whereRaw('1=0')->paginate(10, pageName: 'options');
        }

        return Option::query()
            ->where('group_id', $this->selectedGroupId)
            ->when($this->optionSearch !== '', function (Builder $q): void {
                $q->where(function (Builder $qq): void {
                    $qq->where('name', 'like', "%{$this->optionSearch}%")
                        ->orWhere('code', 'like', "%{$this->optionSearch}%");
                });
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(10, pageName: 'options');
    }

    #[Computed]
    public function selectedGroup(): ?OptionGroup
    {
        if (! $this->selectedGroupId) {
            return null;
        }

        return OptionGroup::find($this->selectedGroupId);
    }

    public function selectGroup(int $groupId): void
    {
        $this->resetPage('options');
        $this->selectedGroupId = $groupId;
        $this->optionSearch = '';
    }

    // Group CRUD
    public function openCreateGroup(): void
    {
        $this->groupForm = [
            'id' => null,
            'name' => '',
            'description' => '',
            'is_active' => true,
            'sort_order' => 0,
        ];
        $this->showGroupModal = true;
    }

    public function openEditGroup(int $id): void
    {
        $g = OptionGroup::query()->findOrFail($id);
        $this->groupForm = [
            'id' => (int) $g->getKey(),
            'name' => (string) $g->getAttribute('name'),
            'description' => (string) ($g->getAttribute('description') ?? ''),
            'is_active' => (bool) $g->getAttribute('is_active'),
            'sort_order' => (int) $g->getAttribute('sort_order'),
        ];
        $this->showGroupModal = true;
    }

    public function saveGroup(): void
    {
        $validated = $this->validate([
            'groupForm.name' => ['required', 'string', 'max:255'],
            'groupForm.description' => ['nullable', 'string'],
            'groupForm.is_active' => ['boolean'],
            'groupForm.sort_order' => ['integer'],
        ]);

        $payload = $validated['groupForm'];

        if (! empty($payload['id'])) {
            $g = OptionGroup::findOrFail((int) $payload['id']);
            $g->fill($payload)->save();
        } else {
            $g = OptionGroup::create($payload);
            if ($this->selectedGroupId === null) {
                $this->selectedGroupId = (int) $g->getKey();
            }
        }

        $this->showGroupModal = false;
    }

    public function toggleGroup(int $id): void
    {
        $g = OptionGroup::findOrFail($id);
        $g->is_active = ! (bool) $g->is_active;
        $g->save();
    }

    public function moveGroupUp(int $id): void
    {
        $this->moveGroup($id, -1);
    }

    public function moveGroupDown(int $id): void
    {
        $this->moveGroup($id, +1);
    }

    protected function moveGroup(int $id, int $delta): void
    {
        $g = OptionGroup::findOrFail($id);
        $g->sort_order = ((int) $g->sort_order) + $delta;
        if ($g->sort_order < 0) {
            $g->sort_order = 0;
        }
        $g->save();
    }

    // Option CRUD
    public function openCreateOption(): void
    {
        if (! $this->selectedGroupId) {
            return;
        }
        $this->optionForm = [
            'id' => null,
            'group_id' => $this->selectedGroupId,
            'code' => '',
            'name' => '',
            'description' => '',
            'is_active' => true,
            'sort_order' => 0,
        ];
        $this->showOptionModal = true;
    }

    public function openEditOption(int $id): void
    {
        $o = Option::query()->findOrFail($id);
        $this->optionForm = [
            'id' => (int) $o->getKey(),
            'group_id' => (int) $o->getAttribute('group_id'),
            'code' => (string) $o->getAttribute('code'),
            'name' => (string) $o->getAttribute('name'),
            'description' => (string) ($o->getAttribute('description') ?? ''),
            'is_active' => (bool) $o->getAttribute('is_active'),
            'sort_order' => (int) $o->getAttribute('sort_order'),
        ];
        $this->showOptionModal = true;
    }

    public function saveOption(): void
    {
        $gid = (int) ($this->optionForm['group_id'] ?? 0);
        // Preserve ID before validation because it is not part of validated payload
        $currentId = (int) ($this->optionForm['id'] ?? 0);

        $validated = $this->validate([
            'optionForm.group_id' => ['required', Rule::exists((new OptionGroup)->getTable(), 'id')],
            'optionForm.code' => [
                'required', 'string', 'max:100',
                Rule::unique((new Option)->getTable(), 'code')
                    ->ignore($currentId)
                    ->where(fn ($q) => $q->where('group_id', $gid)),
            ],
            'optionForm.name' => ['required', 'string', 'max:255'],
            'optionForm.description' => ['nullable', 'string'],
            'optionForm.is_active' => ['boolean'],
            'optionForm.sort_order' => ['integer'],
        ]);

        $payload = $validated['optionForm'];

        if ($currentId > 0) {
            $o = Option::findOrFail($currentId);
            $o->fill($payload)->save();
        } else {
            Option::create($payload);
        }

        $this->showOptionModal = false;
    }

    public function toggleOption(int $id): void
    {
        $o = Option::withTrashed()->findOrFail($id);
        // If soft-deleted, restore when toggling to active
        if ($o->trashed()) {
            $o->restore();
        }
        $o->is_active = ! (bool) $o->is_active;
        $o->save();
    }

    public function deleteOption(int $id): void
    {
        // Reference protection: if referenced by PO items, only soft delete allowed (which we do).
        $o = Option::findOrFail($id);

        // Options are stored in purchase_order_item_option_values, not on purchase_order_items columns.
        $isReferenced = PurchaseOrderItemOptionValue::query()
            ->where('option_id', $id)
            ->exists();

        // In both cases we only soft delete; for non-referenced it is also fine.
        if (! $o->trashed()) {
            $o->delete();
        }

        if ($isReferenced) {
            // additionally ensure inactive
            if ($o->is_active) {
                $o->is_active = false;
                $o->save();
            }
        }
    }

    public function restoreOption(int $id): void
    {
        $o = Option::withTrashed()->findOrFail($id);
        if ($o->trashed()) {
            $o->restore();
        }
        $o->is_active = true;
        $o->save();
    }

    public function moveOptionUp(int $id): void
    {
        $this->moveOption($id, -1);
    }

    public function moveOptionDown(int $id): void
    {
        $this->moveOption($id, +1);
    }

    protected function moveOption(int $id, int $delta): void
    {
        $o = Option::findOrFail($id);
        $o->sort_order = ((int) $o->sort_order) + $delta;
        if ($o->sort_order < 0) {
            $o->sort_order = 0;
        }
        $o->save();
    }
};

?>

<div class="p-6 space-y-6">
    <x-matex::topmenu />
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">{{ __('matex::settings.options.title') }}</h1>
        <a href="{{ route('matex.dashboard') }}" class="text-blue-600 hover:underline">{{ __('matex::settings.options.back') }}</a>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <!-- Groups Pane -->
        <div class="rounded border p-4 space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading size="sm">{{ __('matex::settings.options.groups.heading') }}</flux:heading>
                <flux:button size="sm" variant="primary" wire:click="openCreateGroup">{{ __('matex::settings.options.groups.add') }}</flux:button>
            </div>

            <flux:field>
                <flux:input wire:model.live.debounce.300ms="groupSearch" placeholder="{{ __('matex::settings.options.groups.search_placeholder') }}" />
            </flux:field>

            <div class="divide-y">
                @foreach ($this->groups as $g)
                    <div class="py-2 flex items-center justify-between {{ $selectedGroupId === $g->id ? 'bg-gray-50 px-2 rounded' : '' }}">
                        <button class="text-left flex-1" wire:click="selectGroup({{ $g->id }})">
                            <div class="font-medium">{{ $g->name }}</div>
                            <div class="text-xs text-gray-500">{{ __('matex::settings.options.groups.sort') }}: {{ $g->sort_order }} @if (! $g->is_active) <span class="ml-2 text-red-500">{{ __('matex::settings.options.groups.inactive') }}</span> @endif</div>
                        </button>
                        <div class="flex items-center gap-1">
                            <flux:button size="xs" variant="ghost" wire:click="moveGroupUp({{ $g->id }})" title="{{ __('matex::settings.options.groups.buttons.up') }}">
                                <flux:icon name="chevron-up" />
                            </flux:button>
                            <flux:button size="xs" variant="ghost" wire:click="moveGroupDown({{ $g->id }})" title="{{ __('matex::settings.options.groups.buttons.down') }}">
                                <flux:icon name="chevron-down" />
                            </flux:button>
                            <flux:button size="xs" variant="outline" wire:click="openEditGroup({{ $g->id }})">{{ __('matex::settings.options.groups.buttons.edit') }}</flux:button>
                            <flux:button size="xs" variant="outline" wire:click="toggleGroup({{ $g->id }})">{{ $g->is_active ? __('matex::settings.options.groups.buttons.disable') : __('matex::settings.options.groups.buttons.enable') }}</flux:button>
                        </div>
                    </div>
                @endforeach
            </div>

            <div>
                {{ $this->groups->links() }}
            </div>
        </div>

        <!-- Options Pane -->
        <div class="rounded border p-4 space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading size="sm">{{ __('matex::settings.options.items.heading') }} @if($this->selectedGroup) <span class="text-gray-500 text-xs">({{ __('matex::settings.options.items.group_hint', ['name' => $this->selectedGroup->name]) }})</span> @endif</flux:heading>
                <flux:button size="sm" variant="primary" wire:click="openCreateOption" :disabled="! $selectedGroupId">{{ __('matex::settings.options.items.add') }}</flux:button>
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <flux:input wire:model.live.debounce.300ms="optionSearch" placeholder="{{ __('matex::settings.options.items.search_placeholder') }}" />
            </div>

            @if (! $selectedGroupId)
                <flux:callout variant="warning">{{ __('matex::settings.options.items.select_group_warning') }}</flux:callout>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-gray-500">
                            <tr>
                                <th class="py-2">{{ __('matex::settings.options.items.table.code') }}</th>
                                <th class="py-2">{{ __('matex::settings.options.items.table.name') }}</th>
                                <th class="py-2">{{ __('matex::settings.options.items.table.sort') }}</th>
                                <th class="py-2">{{ __('matex::settings.options.items.table.status') }}</th>
                                <th class="py-2 text-right">{{ __('matex::settings.options.items.table.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                        @foreach ($this->options as $o)
                            <tr>
                                <td class="py-2">{{ $o->code }}</td>
                                <td class="py-2">{{ $o->name }}</td>
                                <td class="py-2">{{ $o->sort_order }}</td>
                                <td class="py-2">
                                    @if($o->deleted_at)
                                        <span class="text-red-600">{{ __('matex::settings.options.items.table.status_deleted') }}</span>
                                    @else
                                        <span class="{{ $o->is_active ? 'text-green-600' : 'text-red-600' }}">{{ $o->is_active ? __('matex::settings.options.items.table.status_active') : __('matex::settings.options.items.table.status_inactive') }}</span>
                                    @endif
                                </td>
                                <td class="py-2">
                                    <div class="flex justify-end gap-1">
                                        <flux:button size="xs" variant="ghost" wire:click="moveOptionUp({{ $o->id }})" title="{{ __('matex::settings.options.items.buttons.up') }}">
                                            <flux:icon name="chevron-up" />
                                        </flux:button>
                                        <flux:button size="xs" variant="ghost" wire:click="moveOptionDown({{ $o->id }})" title="{{ __('matex::settings.options.items.buttons.down') }}">
                                            <flux:icon name="chevron-down" />
                                        </flux:button>
                                        <flux:button size="xs" variant="outline" wire:click="openEditOption({{ $o->id }})">{{ __('matex::settings.options.items.buttons.edit') }}</flux:button>
                                        <flux:button size="xs" variant="outline" wire:click="toggleOption({{ $o->id }})">{{ $o->is_active ? __('matex::settings.options.items.buttons.disable') : __('matex::settings.options.items.buttons.enable') }}</flux:button>
                                        @if($o->deleted_at)
                                            <flux:button size="xs" variant="outline" wire:click="restoreOption({{ $o->id }})">{{ __('matex::settings.options.items.buttons.restore') }}</flux:button>
                                        @else
                                            <flux:button size="xs" variant="danger" wire:click="deleteOption({{ $o->id }})">{{ __('matex::settings.options.items.buttons.delete') }}</flux:button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div>
                    {{ $this->options->links() }}
                </div>
@endif
        </div>
    </div>

    <!-- Group Modal -->
    <flux:modal wire:model="showGroupModal">
        <flux:heading size="sm">{{ $groupForm['id'] ? __('matex::settings.options.groups.modal.title_edit') : __('matex::settings.options.groups.modal.title_create') }}</flux:heading>
        <div class="space-y-3 mt-3">
            <flux:input wire:model="groupForm.name" label="{{ __('matex::settings.options.groups.modal.name') }}"/>
            <flux:textarea wire:model="groupForm.description" label="{{ __('matex::settings.options.groups.modal.description') }}"></flux:textarea>
            <flux:switch wire:model="groupForm.is_active" label="{{ __('matex::settings.options.groups.modal.active') }}"/>
            <flux:input type="number" wire:model="groupForm.sort_order" label="{{ __('matex::settings.options.groups.modal.sort_order') }}"/>
        </div>
        <div class="mt-4 flex justify-end gap-2">
            <flux:button variant="outline" wire:click="$set('showGroupModal', false)">{{ __('matex::settings.options.groups.modal.cancel') }}</flux:button>
            <flux:button variant="primary" wire:click="saveGroup" wire:loading.attr="disabled">{{ __('matex::settings.options.groups.modal.save') }}</flux:button>
        </div>
    </flux:modal>

    <!-- Option Modal -->
    <flux:modal wire:model="showOptionModal">
        <flux:heading size="sm">{{ $optionForm['id'] ? __('matex::settings.options.items.modal.title_edit') : __('matex::settings.options.items.modal.title_create') }}</flux:heading>
        <div class="space-y-3 mt-3">
            <flux:input wire:model="optionForm.code" label="{{ __('matex::settings.options.items.modal.code') }}"/>
            <flux:input wire:model="optionForm.name" label="{{ __('matex::settings.options.items.modal.name') }}"/>
            <flux:textarea wire:model="optionForm.description" label="{{ __('matex::settings.options.items.modal.description') }}"></flux:textarea>
            <flux:switch wire:model="optionForm.is_active" label="{{ __('matex::settings.options.items.modal.active') }}"/>
            <flux:input type="number" wire:model="optionForm.sort_order" label="{{ __('matex::settings.options.items.modal.sort_order') }}"/>
        </div>
        <div class="mt-4 flex justify-end gap-2">
            <flux:button variant="outline" wire:click="$set('showOptionModal', false)">{{ __('matex::settings.options.items.modal.cancel') }}</flux:button>
            <flux:button variant="primary" wire:click="saveOption" wire:loading.attr="disabled">{{ __('matex::settings.options.items.modal.save') }}</flux:button>
        </div>
    </flux:modal>
</div>
