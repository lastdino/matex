<?php

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lastdino\Matex\Models\Material;
use Lastdino\Matex\Models\MaterialCategory;
use Lastdino\Matex\Models\OrderingToken;
use Lastdino\Matex\Models\Supplier;
use Lastdino\Matex\Models\UnitConversion;
use Lastdino\Matex\Support\Settings;
use Lastdino\Matex\Support\Tables;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new class extends Component
{
    use WithFileUploads, WithPagination;

    #[Url]
    public ?int $id = null;

    public string $q = '';

    public ?int $category_id = null;

    public bool $is_chemical_only = false;

    public bool $is_alert_only = false;

    public string $regulation_q = '';

    public function updatingQ(): void
    {
        $this->resetPage();
    }

    public function updatingCategoryId(): void
    {
        $this->resetPage();
    }

    public function updatingIsChemicalOnly(): void
    {
        $this->resetPage();
    }

    public function updatingIsAlertOnly(): void
    {
        $this->resetPage();
    }

    public function updatingRegulationQ(): void
    {
        $this->resetPage();
    }

    public function getCategoriesProperty()
    {
        return MaterialCategory::query()->orderBy('name')->get();
    }

    public function getMaterialsProperty()
    {
        $q = (string) $this->q;
        $rq = (string) $this->regulation_q;

        return Material::query()
            ->with(['category', 'preferredSupplier'])
            ->withSum('lots', 'qty_on_hand')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('sku', 'like', "%{$q}%");
                });
            })
            ->when($rq !== '', function ($query) use ($rq) {
                $query->where('applicable_regulation', 'like', "%{$rq}%");
            })
            ->when(! is_null($this->category_id), function ($query) {
                $query->where('category_id', $this->category_id);
            })
            ->when($this->is_chemical_only, function ($query) {
                $query->where('is_chemical', true);
            })
            ->when($this->is_alert_only, function ($query) {
                $query->where(function ($sub) {
                    $sub->whereRaw('CAST(current_stock AS DECIMAL) < CAST(min_stock AS DECIMAL)')
                        ->orWhere(function ($s) {
                            $s->whereNotNull('max_stock')
                                ->where('max_stock', '>', 0)
                                ->whereRaw('CAST(current_stock AS DECIMAL) > CAST(max_stock AS DECIMAL)');
                        });
                });
            })
            ->orderBy('name')
            ->paginate(25);
    }

    public function toggleActive(int $materialId): void
    {
        $m = Material::query()->findOrFail($materialId);
        $m->is_active = ! ($m->is_active ?? true);
        $m->save();

        $msg = $m->is_active ? 'Material activated' : 'Material deactivated';
        $this->dispatch('toast', type: 'success', message: $msg);
    }

    #[On('matex:material-saved')]
    public function refresh(): void
    {
        // Simply triggering a re-render
    }

    #[On('matex:sds-updated')]
    public function sdsUpdated(): void
    {
        // Simply triggering a re-render
    }
};

?>

<div class="p-6 space-y-6">
    <x-matex::topmenu />
    @if($id)
        <livewire:matex::matex.materials.show :id="$id" />
    @else
        <div>
            <h1 class="text-xl font-semibold">{{ __('matex::materials.title') }}</h1>

            <div class="flex flex-wrap items-end gap-4">
                <div class="grow max-w-96">
                    <flux:input wire:model.live.debounce.300ms="q" placeholder="{{ __('matex::materials.filters.search_placeholder') }}" />
                </div>
                <div>
                    <label class="block text-sm text-neutral-600 mb-1">{{ __('matex::materials.form.applicable_regulation') }}</label>
                    <flux:input wire:model.live.debounce.300ms="regulation_q" placeholder="{{ __('matex::materials.filters.regulation_placeholder') }}" />
                </div>
                <div>
                    <label class="block text-sm text-neutral-600 mb-1">{{ __('matex::materials.filters.category') }}</label>
                    <select class="w-60 border rounded p-2 bg-white dark:bg-neutral-900" wire:model.live="category_id">
                        <option value="">{{ __('matex::materials.filters.all') }}</option>
                        @foreach($this->categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-4 h-10">
                    <flux:checkbox wire:model.live="is_chemical_only" label="{{ __('matex::materials.filters.chemical_only') }}" />
                    <flux:checkbox wire:model.live="is_alert_only" label="{{ __('matex::materials.filters.alert_only') }}" />
                </div>
                <div>
                    <flux:button variant="primary" x-on:click="$dispatch('matex:open-material-form')">{{ __('matex::materials.buttons.new') }}</flux:button>
                </div>
            </div>

            <div class="mt-4">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('matex::materials.table.ghs') }}</flux:table.column>
                        <flux:table.column>{{ __('matex::materials.table.sku') }}</flux:table.column>
                        <flux:table.column>{{ __('matex::materials.table.name') }}</flux:table.column>
                        <flux:table.column>{{ __('matex::materials.form.applicable_regulation') }}</flux:table.column>
                        <flux:table.column>{{ __('matex::materials.filters.category') }}</flux:table.column>
                        <flux:table.column>{{ __('matex::materials.table.manufacturer') }}</flux:table.column>
                        <flux:table.column>{{ __('matex::materials.table.stock') }}</flux:table.column>
                        <flux:table.column>{{ __('matex::materials.table.min_stock') }}</flux:table.column>
                        <flux:table.column>{{ __('matex::materials.table.max_stock') }}</flux:table.column>
                        <flux:table.column>{{ __('matex::materials.table.unit') }}</flux:table.column>
                        <flux:table.column>{{ __('matex::materials.table.moq') }}</flux:table.column>
                        <flux:table.column>{{ __('matex::materials.table.pack_size') }}</flux:table.column>
                        <flux:table.column>{{ __('matex::materials.table.unit_price') }}</flux:table.column>
                        <flux:table.column>{{ __('matex::materials.table.is_chemical') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('matex::materials.table.actions') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse($this->materials as $m)
                            @php
                                $stockValue = $m->manage_by_lot ? (float) ($m->lots_sum_qty_on_hand ?? 0) : (float) ($m->current_stock ?? 0);
                                $low = !is_null($m->min_stock) && $stockValue < (float) $m->min_stock;
                                $over = !is_null($m->max_stock) && (float) $m->max_stock > 0 && $stockValue > (float) $m->max_stock;
                            @endphp
                            <flux:table.row :class="$low || $over ? 'bg-red-50/40 dark:bg-red-950/20' : ''">
                                <flux:table.cell>
                                    @php($icons = method_exists($m, 'ghsIconNames') ? $m->ghsIconNames() : [])
                                    @if(!empty($icons))
                                        <div class="flex flex-wrap items-center gap-1">
                                            @foreach($icons as $icon)
                                                <flux:icon :name="$icon" class="size-6" />
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="w-10 h-10 bg-neutral-100 dark:bg-neutral-800 text-[10px] grid place-items-center text-neutral-400">{{ __('matex::materials.table.na') }}</div>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="whitespace-nowrap">{{ $m->sku }}</flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium">{{ $m->name }}</span>
                                        @if(!($m->is_active ?? true))
                                            <flux:badge size="sm" color="zinc">{{ __('matex::materials.badges.inactive') }}</flux:badge>
                                        @endif
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <div class="max-w-48 truncate" title="{{ $m->applicable_regulation }}">
                                        {{ $m->applicable_regulation }}
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <div>
                                        @if(!empty($m->category->name))
                                            {{ $m->category->name }}
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-1 mt-1">
                                        @if($m->manage_by_lot)
                                            <flux:badge size="sm" color="purple">{{ __('matex::materials.badges.lot') }}</flux:badge>
                                        @endif
                                        @php($hasSds = \Illuminate\Support\Facades\Schema::hasTable('media') ? (bool) $m->getFirstMedia('sds') : false)
                                        <flux:badge size="sm" color="{{ $hasSds ? 'emerald' : 'zinc' }}">{{ $hasSds ? __('matex::materials.sds.badge_has') : __('matex::materials.sds.badge_none') }}</flux:badge>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell class="text-neutral-500">{{ $m->manufacturer_name }}</flux:table.cell>
                                <flux:table.cell :class="$low || $over ? 'text-red-600 font-medium' : ''">
                                    {{ $stockValue }}
                                    @if($low || $over)
                                        <flux:tooltip content="{{ $low ? '最小量を下回っています' : '最大量を超過しています' }}">
                                            <flux:icon name="exclamation-triangle" class="size-4 inline text-red-600 ml-1" />
                                        </flux:tooltip>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>{{ is_null($m->min_stock) ? '-' : (float) $m->min_stock }}</flux:table.cell>
                                <flux:table.cell>{{ is_null($m->max_stock) ? '-' : (float) $m->max_stock }}</flux:table.cell>
                                <flux:table.cell class="text-neutral-500">{{ $m->unit_stock }}</flux:table.cell>
                                <flux:table.cell>
                                    {{ is_null($m->moq) ? __('matex::materials.table.not_set') : (string) (float) $m->moq }}
                                </flux:table.cell>
                                <flux:table.cell>
                                    {{ is_null($m->pack_size) ? __('matex::materials.table.not_set') : (string) (float) $m->pack_size }}
                                </flux:table.cell>
                                <flux:table.cell>@if(!is_null($m->unit_price)) {{ \Lastdino\Matex\Support\Format::moneyUnitPriceMaterials($m->unit_price) }} @endif</flux:table.cell>
                                <flux:table.cell>
                                    @if($m->is_chemical)
                                        <flux:icon name="beaker" class="size-5 text-blue-600" variant="solid" />
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex justify-end">
                                        <flux:dropdown>
                                            <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" />
                                            <flux:menu>
                                                <flux:menu.item href="{{ route('matex.materials.index', ['id' => $m->id]) }}" icon="clipboard-document-check" wire:navigate>{{ __('matex::materials.buttons.view') }}</flux:menu.item>
                                                <flux:menu.item href="{{ route('matex.issue.scan', ['material' => $m->id]) }}" icon="arrow-left-start-on-rectangle" wire:navigate>{{ __('matex::materials.buttons.issue') }}</flux:menu.item>
                                                <flux:menu.item icon="qr-code" x-on:click="$dispatch('matex:open-token', { materialId: {{ $m->id }} })">{{ __('matex::materials.buttons.issue_token') }}</flux:menu.item>
                                                @php($sds = \Illuminate\Support\Facades\Schema::hasTable('media') ? $m->getFirstMedia('sds') : null)
                                                @if($sds)
                                                    @php($dl = \Illuminate\Support\Facades\URL::temporarySignedRoute('matex.materials.sds.download', now()->addMinutes(10), ['material' => $m->id]))
                                                    <flux:menu.item icon="document-text" href="{{ $dl }}" target="_blank">{{ __('matex::materials.sds.download') }}</flux:menu.item>
                                                @endif
                                                <flux:menu.item icon="arrow-up-tray" x-on:click="$dispatch('matex:open-sds', { materialId: {{ $m->id }} })">{{ __('matex::materials.sds.open_modal') }}</flux:menu.item>
                                                <flux:menu.item icon="pencil-square" variant="danger" x-on:click="$dispatch('matex:open-material-form', { id: {{ $m->id }} })">{{ __('matex::materials.buttons.edit') }}</flux:menu.item>
                                                @if($m->is_active)
                                                    <flux:menu.item icon="no-symbol" wire:click="toggleActive({{ $m->id }})">{{ __('matex::materials.buttons.disable') }}</flux:menu.item>
                                                @else
                                                    <flux:menu.item icon="power" wire:click="toggleActive({{ $m->id }})">{{ __('matex::materials.buttons.enable') }}</flux:menu.item>
                                                @endif
                                            </flux:menu>
                                        </flux:dropdown>
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="14" class="py-6 text-center text-neutral-500">{{ __('matex::materials.table.no_materials') }}</flux:cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>

            <div class="mt-4">
                {{ $this->materials->links() }}
            </div>

            <livewire:matex::matex.materials.material-form-modal />
            <livewire:matex::matex.materials.issue-token-modal />
            <livewire:matex::matex.materials.sds-manager-modal />
        </div>
    @endif
</div>
