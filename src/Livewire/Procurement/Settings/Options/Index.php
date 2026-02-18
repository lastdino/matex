<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Livewire\Procurement\Settings\Options;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Lastdino\ProcurementFlow\Models\Option;
use Lastdino\ProcurementFlow\Models\OptionGroup;
use Lastdino\ProcurementFlow\Models\PurchaseOrderItemOptionValue;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
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

    public function render()
    {
        return view('procflow::livewire.procurement.settings.options.index', [
            'groups' => $this->groups(),
            'options' => $this->options(),
            'selectedGroup' => $this->selectedGroupId ? OptionGroup::find($this->selectedGroupId) : null,
        ]);
    }

    protected function groups(): LengthAwarePaginator
    {
        return OptionGroup::query()
            ->when($this->groupSearch !== '', function (Builder $q): void {
                $q->where('name', 'like', "%{$this->groupSearch}%");
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(10, pageName: 'groups');
    }

    protected function options(): LengthAwarePaginator
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
}
