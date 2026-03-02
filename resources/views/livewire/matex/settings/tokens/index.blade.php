<?php

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Validation\Rule;
use Lastdino\Matex\Models\Material;
use Lastdino\Matex\Models\OrderingToken;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';

    public ?int $materialId = null;

    public ?bool $enabled = null;

    public bool $showForm = false;

    public ?int $editingId = null;

    #[Livewire\Attributes\On('matex:open-token')]
    public function openTokenModal(int $materialId): void
    {
        $this->resetForm();
        $this->editingId = null;

        $m = Material::query()->findOrFail($materialId);
        $this->form = [
            'token' => strtoupper(\Illuminate\Support\Str::random(12)),
            'material_id' => $materialId,
            'department_id' => null,
            'unit_purchase' => $m->unit_purchase_default,
            'default_qty' => $m->pack_size ?: 1.0,
            'options' => [],
            'enabled' => true,
            'expires_at' => null,
        ];

        $this->showForm = true;
    }

    /**
     * @var array{token:string,material_id:int|null,department_id:int|null,unit_purchase:string|null,default_qty:float|int|string|null,options:array<int,int|string|null>,enabled:bool,expires_at:string|null}
     */
    public array $form = [
        'token' => '',
        'material_id' => null,
        'department_id' => null,
        'unit_purchase' => null,
        'default_qty' => null,
        'options' => [],
        'enabled' => true,
        'expires_at' => null,
    ];

    /**
     * @var array<int,array{id:int,name:string}>
     */
    public array $optionGroups = [];

    /**
     * @var array<int,array<int,array{id:int,name:string}>>
     */
    public array $optionsByGroup = [];

    public function mount(): void
    {
        $catalog = app(\Lastdino\Matex\Services\OptionCatalogService::class);
        $this->optionGroups = $catalog->getActiveGroups()->toArray();
        $this->optionsByGroup = $catalog->getActiveOptionsByGroup();
    }

    protected function rules(): array
    {
        $unique = Rule::unique((new OrderingToken)->getTable(), 'token');
        if ($this->editingId) {
            $unique = $unique->ignore($this->editingId);
        }

        return [
            'form.token' => ['required', 'string', 'max:191', $unique],
            'form.material_id' => ['required', 'integer', 'exists:'.(new Material)->getTable().',id'],
            'form.department_id' => ['nullable', 'integer', 'exists:'.\Lastdino\Matex\Support\Tables::name('departments').',id'],
            'form.unit_purchase' => ['nullable', 'string', 'max:64'],
            'form.default_qty' => ['nullable', 'numeric', 'gt:0'],
            'form.options' => ['nullable', 'array'],
            'form.enabled' => ['required', 'boolean'],
            'form.expires_at' => ['nullable', 'date'],
        ];
    }

    public function creating(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $ot = OrderingToken::findOrFail($id);
        $this->editingId = $ot->id;
        $this->form = [
            'token' => (string) $ot->token,
            'material_id' => (int) $ot->material_id,
            'department_id' => $ot->department_id,
            'unit_purchase' => $ot->unit_purchase,
            'default_qty' => $ot->default_qty,
            'options' => $ot->options ?? [],
            'enabled' => (bool) $ot->enabled,
            'expires_at' => optional($ot->expires_at)->format('Y-m-d\TH:i'),
        ];
        $this->showForm = true;
    }

    public function save(): void
    {
        $validated = $this->validate();
        $payload = $validated['form'];

        // Normalize expires_at
        if (! empty($payload['expires_at'])) {
            $payload['expires_at'] = \Carbon\Carbon::parse((string) $payload['expires_at']);
        }

        // Filter out empty options
        if (isset($payload['options']) && is_array($payload['options'])) {
            $payload['options'] = array_filter($payload['options'], fn ($v) => $v !== '' && $v !== null);
        }

        if ($this->editingId) {
            $ot = OrderingToken::findOrFail($this->editingId);
            $ot->update($payload);
        } else {
            OrderingToken::create($payload);
        }
        $this->showForm = false;
        $this->dispatch('saved');
    }

    public function toggle(int $id): void
    {
        $ot = OrderingToken::findOrFail($id);
        $ot->enabled = ! (bool) $ot->enabled;
        $ot->save();
    }

    public function delete(int $id): void
    {
        OrderingToken::whereKey($id)->delete();
    }

    public function resetForm(): void
    {
        $this->form = [
            'token' => '',
            'material_id' => null,
            'unit_purchase' => null,
            'default_qty' => null,
            'options' => [],
            'enabled' => true,
            'expires_at' => null,
        ];
    }

    public function getRowsProperty(): LengthAwarePaginator
    {
        $q = OrderingToken::query()->with(['material']);
        if ($this->search !== '') {
            $s = $this->search;
            $q->where(function ($qq) use ($s) {
                $qq->where('token', 'like', "%{$s}%")
                    ->orWhereHas('material', function ($mq) use ($s) {
                        $mq->where('name', 'like', "%{$s}%")
                            ->orWhere('sku', 'like', "%{$s}%");
                    });
            });
        }
        if (! is_null($this->materialId)) {
            $q->where('material_id', $this->materialId);
        }
        if (! is_null($this->enabled)) {
            $q->where('enabled', $this->enabled);
        }

        return $q->orderByDesc('id')->paginate(15);
    }

    public function getMaterialsProperty()
    {
        return Material::query()->orderBy('name')->limit(200)->get(['id', 'name', 'sku']);
    }
};

?>

<div class="space-y-6 @if(!Route::is('matex.settings.tokens')) @else p-6 @endif">
    @if(Route::is('matex.settings.tokens'))
        <x-matex::topmenu />

        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ __('matex::settings.tokens.title') }}</h1>
            <div class="flex gap-2">
                <flux:button variant="primary" wire:click="creating">{{ __('matex::settings.tokens.buttons.new') }}</flux:button>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-4 items-end">
            <flux:field class="md:col-span-2">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('matex::settings.tokens.filters.search_placeholder') }}" />
            </flux:field>
            <flux:field>
                <flux:select wire:model.live="materialId">
                    <option value="">{{ __('matex::settings.tokens.filters.all_materials') }}</option>
                    @foreach($this->materials as $m)
                        <option value="{{ $m->id }}">{{ $m->name }} ({{ $m->sku }})</option>
                    @endforeach
                </flux:select>
            </flux:field>
            <flux:field>
                <flux:select wire:model.live="enabled">
                    <option value="">{{ __('matex::settings.tokens.filters.enabled_all') }}</option>
                    <option value="1">{{ __('matex::settings.tokens.filters.enabled') }}</option>
                    <option value="0">{{ __('matex::settings.tokens.filters.disabled') }}</option>
                </flux:select>
            </flux:field>
        </div>

        <div class="rounded border divide-y">
            <div class="grid grid-cols-12 gap-2 p-3 text-sm font-medium text-gray-600">
                <div class="col-span-3">{{ __('matex::settings.tokens.table.token') }}</div>
                <div class="col-span-3">{{ __('matex::settings.tokens.table.material') }}</div>
                <div class="col-span-2">{{ __('matex::settings.tokens.table.unit_qty') }}</div>
                <div class="col-span-2">{{ __('matex::settings.tokens.table.expires') }}</div>
                <div class="col-span-2 text-right">{{ __('matex::settings.tokens.table.actions') }}</div>
            </div>
            @forelse($this->rows as $row)
                <div class="grid grid-cols-12 gap-2 p-3 items-center">
                    <div class="col-span-3">
                        <div class="font-mono text-sm">{{ $row->token }}</div>
                        <div class="text-xs text-gray-500">{{ __('matex::settings.tokens.labels.id') }}: {{ $row->id }}</div>
                    </div>
                    <div class="col-span-3">
                        <div class="font-medium">{{ $row->material?->name }}</div>
                        <div class="text-xs text-gray-500">{{ $row->material?->sku }}</div>
                    </div>
                    <div class="col-span-2 text-sm">
                        <div>{{ __('matex::settings.tokens.labels.unit') }}: {{ $row->unit_purchase ?? '-' }}</div>
                        <div>{{ __('matex::settings.tokens.labels.default_qty') }}: {{ $row->default_qty ?? '-' }}</div>
                    </div>
                    <div class="col-span-2 text-sm">
                        {{ $row->expires_at?->format('Y-m-d H:i') ?? '-' }}
                    </div>
                    <div class="col-span-2 flex justify-end gap-2">
                        <flux:button size="xs" variant="outline" wire:click="edit({{ $row->id }})">{{ __('matex::settings.tokens.buttons.edit') }}</flux:button>
                        <flux:button size="xs" variant="outline" wire:click="toggle({{ $row->id }})">
                            {{ $row->enabled ? __('matex::settings.tokens.buttons.disable') : __('matex::settings.tokens.buttons.enable') }}
                        </flux:button>
                        <flux:button size="xs" variant="danger" wire:click="delete({{ $row->id }})">{{ __('matex::settings.tokens.buttons.delete') }}</flux:button>
                    </div>
                </div>
            @empty
                <div class="p-6 text-sm text-gray-600">{{ __('matex::settings.tokens.table.empty') }}</div>
            @endforelse
        </div>

        <div>
            {{ $this->rows->links() }}
        </div>
    @endif

    <flux:modal wire:model="showForm">
        <flux:heading size="sm">{{ $editingId ? __('matex::settings.tokens.modal.title_edit') : __('matex::settings.tokens.modal.title_create') }}</flux:heading>
        <div class="space-y-3">
            <flux:input wire:model.defer="form.token" label="{{ __('matex::settings.tokens.modal.token') }}"/>
            <flux:select wire:model.defer="form.material_id" label="{{ __('matex::settings.tokens.modal.material') }}">
                <option value="">{{ __('matex::settings.tokens.modal.select_placeholder') }}</option>
                @foreach($this->materials as $m)
                    <option value="{{ $m->id }}">{{ $m->name }} ({{ $m->sku }})</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.defer="form.department_id" label="部門">
                <option value="">(部門指定なし)</option>
                @foreach(\Lastdino\Matex\Models\Department::active()->ordered()->get() as $dept)
                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                @endforeach
            </flux:select>
            <div class="grid md:grid-cols-3 gap-3">
                <flux:input wire:model.defer="form.unit_purchase" placeholder="e.g. case" label="{{ __('matex::settings.tokens.modal.unit_purchase') }}"/>
                <flux:input type="number" step="0.000001" min="0" wire:model.defer="form.default_qty" label="{{ __('matex::settings.tokens.modal.default_qty') }}"/>
                <flux:switch wire:model.defer="form.enabled" label="{{ __('matex::settings.tokens.modal.enabled') }}"/>
            </div>
            <flux:input type="datetime-local" wire:model.defer="form.expires_at" label="{{ __('matex::settings.tokens.modal.expires_at') }}"/>

            @if(!empty($optionGroups))
                <div class="pt-2 border-t dark:border-white/10 space-y-3">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">既定のオプション選択（任意）</p>
                    <div class="grid gap-3 md:grid-cols-2">
                        @foreach($optionGroups as $group)
                            <flux:select wire:model.defer="form.options.{{ $group['id'] }}" label="{{ $group['name'] }}" placeholder="選択なし">
                                <option value="">(選択なし)</option>
                                @foreach($optionsByGroup[$group['id']] ?? [] as $opt)
                                    <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
                                @endforeach
                            </flux:select>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
        <div class="mt-4 flex justify-end gap-2">
            <flux:button variant="outline" wire:click="$set('showForm', false)">{{ __('matex::settings.tokens.buttons.cancel') }}</flux:button>
            <flux:button variant="primary" wire:click="save">{{ __('matex::settings.tokens.buttons.save') }}</flux:button>
        </div>
    </flux:modal>
</div>
