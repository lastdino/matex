<?php

use Lastdino\Matex\Models\MaterialCategory;
use Livewire\Component;

new class extends Component
{
    public bool $openModal = false;

    public ?int $editingId = null;

    public string $name = '';

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
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
        $this->openModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $payload = [
            'name' => $this->name,
        ];

        if ($this->editingId) {
            $cat = MaterialCategory::query()->findOrFail($this->editingId);
            $cat->fill($payload)->save();
            $this->dispatch('notify', text: __('matex::settings.categories.flash.updated'));
        } else {
            MaterialCategory::query()->create($payload);
            $this->dispatch('notify', text: __('matex::settings.categories.flash.created'));
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        $cat = MaterialCategory::query()->findOrFail($id);
        $cat->delete();
        $this->dispatch('notify', text: __('matex::settings.categories.flash.deleted'));
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
    }

    /**
     * @return array<int, array{id:int,name:string}>
     */
    public function getCategoriesProperty(): array
    {
        return MaterialCategory::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($m) => [
                'id' => (int) $m->id,
                'name' => (string) $m->name,
            ])->all();
    }
};

?>

<div class="p-6 space-y-6">
    <x-matex::topmenu />
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">{{ __('matex::settings.categories.title', [], null) ?: '資材カテゴリの設定' }}</h1>
        <a href="{{ route('matex.dashboard') }}" class="text-blue-600 hover:underline">{{ __('matex::common.back', [], null) ?: '戻る' }}</a>
    </div>

    <div class="flex justify-end">
        <flux:button variant="primary" wire:click="openCreate">{{ __('matex::settings.categories.new', [], null) ?: 'カテゴリを追加' }}</flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('matex::settings.categories.fields.name', [], null) ?: '名称' }}</flux:table.column>
            <flux:table.column align="end">{{ __('matex::common.actions', [], null) ?: '操作' }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->categories as $cat)
                <flux:table.row>
                    <flux:table.cell class="font-medium">{{ $cat['name'] }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end gap-2">
                            <flux:button size="xs" variant="outline" wire:click="openEdit({{ $cat['id'] }})">{{ __('matex::common.edit', [], null) ?: '編集' }}</flux:button>
                            <flux:button size="xs" variant="danger" wire:click="delete({{ $cat['id'] }})" wire:confirm="{{ __('matex::common.confirm_delete', [], null) ?: '削除してよいですか？' }}">{{ __('matex::common.delete', [], null) ?: '削除' }}</flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="2" class="text-center text-neutral-500 py-6">{{ __('matex::settings.categories.empty', [], null) ?: 'カテゴリがありません' }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model="openModal" class="w-full md:w-[30rem]">
        <div class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? (__('matex::settings.categories.edit_title', [], null) ?: 'カテゴリを編集') : (__('matex::settings.categories.create_title', [], null) ?: 'カテゴリを作成') }}</flux:heading>

            <div class="space-y-4">
                <flux:input wire:model="name" label="{{ __('matex::settings.categories.fields.name', [], null) ?: '名称' }}" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="closeModal">{{ __('matex::common.cancel', [], null) ?: 'キャンセル' }}</flux:button>
                <flux:button variant="primary" wire:click="save" wire:loading.attr="disabled">{{ __('matex::common.save', [], null) ?: '保存' }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
