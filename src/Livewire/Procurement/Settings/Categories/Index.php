<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Livewire\Procurement\Settings\Categories;

use Illuminate\Contracts\View\View;
use Lastdino\ProcurementFlow\Models\MaterialCategory;
use Livewire\Component;

class Index extends Component
{
    public bool $openModal = false;
    public ?int $editingId = null;

    public string $name = '';
    public string $code = '';

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        $id = $this->editingId;

        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_\-]+$/', 'unique:' . (new MaterialCategory())->getTable() . ',code' . ($id ? ',' . $id : '')],
        ];
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->openModal = true;
    }

    public function openEdit(int $id): void
    {
        $cat = MaterialCategory::query()->findOrFail($id);
        $this->editingId = $cat->id;
        $this->name = (string) $cat->name;
        $this->code = (string) $cat->code;
        $this->openModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $payload = [
            'name' => $this->name,
            'code' => $this->code,
        ];

        if ($this->editingId) {
            $cat = MaterialCategory::query()->findOrFail($this->editingId);
            $cat->fill($payload)->save();
            $this->dispatch('notify', text: __('procflow::settings.categories.flash.updated'));
        } else {
            MaterialCategory::query()->create($payload);
            $this->dispatch('notify', text: __('procflow::settings.categories.flash.created'));
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        $cat = MaterialCategory::query()->findOrFail($id);
        $cat->delete();
        $this->dispatch('notify', text: __('procflow::settings.categories.flash.deleted'));
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
        $this->code = '';
    }

    /**
     * @return array<int, array{id:int,name:string,code:string}>
     */
    public function getCategoriesProperty(): array
    {
        return MaterialCategory::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn ($m) => [
                'id' => (int) $m->id,
                'name' => (string) $m->name,
                'code' => (string) $m->code,
            ])->all();
    }

    public function render(): View
    {
        return view('procflow::livewire.procurement.settings.categories.index');
    }
}
