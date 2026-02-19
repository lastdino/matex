<?php

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lastdino\ProcurementFlow\Models\Material;
use Lastdino\ProcurementFlow\Models\MaterialCategory;
use Lastdino\ProcurementFlow\Models\OrderingToken;
use Lastdino\ProcurementFlow\Models\Supplier;
use Lastdino\ProcurementFlow\Models\UnitConversion;
use Lastdino\ProcurementFlow\Support\Settings;
use Lastdino\ProcurementFlow\Support\Tables;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new class extends Component
{
    use WithFileUploads, WithPagination;

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

    // Modal state for create/edit Material
    public bool $showMaterialModal = false;

    public ?int $editingMaterialId = null;

    /**
     * @var array{
     *   sku:?string,name:?string,unit_stock:?string,unit_purchase_default:?string|null,safety_stock:?string|float|int|null,
     *   category_id:?int,current_stock:?string|float|int|null,
     *   manufacturer_name:?string,storage_location:?string,applicable_regulation:?string,
     *   ghs_mark:?string,ghs_mark_options?:array<int,string>,protective_equipment:?string,unit_price:?string|float|int|null,
     *   conversion_factor_purchase_to_stock:?string|float|int|null,
     *   conversions:array<int, array{from_unit:string, factor:float|int|string}>,
     *   preferred_supplier_id:?int|null,
     *   tax_code:?string|null,
     *   moq:?string|float|int|null,
     *   pack_size:?string|float|int|null,
     *   min_stock:?string|float|int|null,
     *   max_stock:?string|float|int|null,
     *   sync_to_monox:bool,
     *   is_chemical:bool,
     *   cas_no:?string,
     *   physical_state:?string,
     *   ghs_hazard_details:?string,
     *   specified_quantity:?string|float|int|null,
     *   emergency_contact:?string,
     *   disposal_method:?string
     * }
     */
    public array $materialForm = [
        'sku' => null,
        'name' => null,
        'tax_code' => 'standard',
        'unit_stock' => null,
        'unit_purchase_default' => null,
        'min_stock' => 0,
        'max_stock' => null,
        'category_id' => null,
        'current_stock' => null,
        'manufacturer_name' => null,
        'applicable_regulation' => null,
        'ghs_mark' => null,
        'ghs_mark_options' => [],
        'protective_equipment' => null,
        'unit_price' => null,
        'conversion_factor_purchase_to_stock' => null,
        'conversions' => [],
        'preferred_supplier_id' => null,
        'separate_shipping' => false,
        'shipping_fee_per_order' => null,
        'is_chemical' => false,
        'cas_no' => null,
        'physical_state' => null,
        'ghs_hazard_details' => null,
        'specified_quantity' => null,
        'emergency_contact' => null,
        'disposal_method' => null,
        'moq' => null,
        'pack_size' => null,
    ];

    public function getCategoriesProperty()
    {
        return MaterialCategory::query()->orderBy('name')->get();
    }

    public function getSuppliersProperty()
    {
        return \Lastdino\ProcurementFlow\Models\Supplier::query()->orderBy('name')->get();
    }

    // Ordering Token issuance modal state
    public bool $showTokenModal = false;

    public ?int $tokenMaterialId = null;

    /**
     * @var array{token:?string, material_id:?int, unit_purchase:?string|null, default_qty:?float|int|null, enabled:bool, expires_at:?string|null}
     */
    public array $tokenForm = [
        'token' => null,
        'material_id' => null,
        'unit_purchase' => null,
        'default_qty' => null,
        'enabled' => true,
        'expires_at' => null,
    ];

    // SDS upload modal state
    public bool $showSdsModal = false;

    public ?int $sdsMaterialId = null;

    #[Validate('file|mimes:pdf|max:10240')]
    public $sdsUpload;

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
                            $s->where('is_chemical', true)
                                ->whereNotNull('specified_quantity')
                                ->where('specified_quantity', '>', 0)
                                ->whereRaw('CAST(current_stock AS DECIMAL) >= CAST(specified_quantity AS DECIMAL)');
                        });
                });
            })
            ->orderBy('name')
            ->paginate(25);
    }

    public function openCreateMaterial(): void
    {
        $this->resetMaterialForm();
        $this->editingMaterialId = null;
        $this->showMaterialModal = true;
    }

    public function openEditMaterial(int $id): void
    {
        /** @var Material $m */
        $m = Material::query()->findOrFail($id);
        $this->editingMaterialId = $m->id;

        $ghsOptions = [];
        if ($m->ghs_mark) {
            $ghsOptions = explode(',', $m->ghs_mark);
        }

        $this->materialForm = [
            'sku' => $m->sku,
            'name' => $m->name,
            'tax_code' => $m->tax_code ?? 'standard',
            'unit_stock' => $m->unit_stock,
            'unit_purchase_default' => $m->unit_purchase_default,
            'min_stock' => (float) $m->min_stock,
            'max_stock' => is_null($m->max_stock) ? null : (float) $m->max_stock,
            'category_id' => $m->category_id,
            'current_stock' => (float) $m->current_stock,
            'manufacturer_name' => $m->manufacturer_name,
            'storage_location' => $m->storage_location,
            'applicable_regulation' => $m->applicable_regulation,
            'ghs_mark' => $m->ghs_mark,
            'ghs_mark_options' => $ghsOptions,
            'protective_equipment' => $m->protective_equipment,
            'unit_price' => (float) $m->unit_price,
            'conversion_factor_purchase_to_stock' => (float) ($m->conversions()->where('from_unit', $m->unit_purchase_default)->where('to_unit', $m->unit_stock)->first()?->factor ?? 1.0),
            'conversions' => $m->conversions()
                ->where('to_unit', $m->unit_stock)
                ->where('from_unit', '!=', $m->unit_purchase_default)
                ->get()
                ->map(fn ($c) => ['from_unit' => $c->from_unit, 'factor' => (float) $c->factor])
                ->toArray(),
            'preferred_supplier_id' => $m->preferred_supplier_id,
            'separate_shipping' => (bool) $m->separate_shipping,
            'shipping_fee_per_order' => (float) $m->shipping_fee_per_order,
            'manage_by_lot' => (bool) $m->manage_by_lot,
            'is_chemical' => (bool) ($m->is_chemical ?? false),
            'cas_no' => $m->cas_no,
            'physical_state' => $m->physical_state,
            'ghs_hazard_details' => $m->ghs_hazard_details,
            'specified_quantity' => (float) $m->specified_quantity,
            'emergency_contact' => $m->emergency_contact,
            'disposal_method' => $m->disposal_method,
            'moq' => (float) $m->moq,
            'pack_size' => (float) $m->pack_size,
        ];
        $this->showMaterialModal = true;
    }

    public function openTokenModal(int $materialId): void
    {
        $this->tokenMaterialId = $materialId;
        $m = Material::query()->findOrFail($materialId);

        $this->tokenForm = [
            'token' => strtoupper(Str::random(12)),
            'material_id' => $materialId,
            'unit_purchase' => $m->unit_purchase_default,
            'default_qty' => (float) ($m->pack_size ?: 1.0),
            'enabled' => true,
            'expires_at' => null,
        ];

        $this->showTokenModal = true;
    }

    public function toggleActive(int $materialId): void
    {
        $m = Material::query()->findOrFail($materialId);
        $m->is_active = ! ($m->is_active ?? true);
        $m->save();

        $msg = $m->is_active ? 'Material activated' : 'Material deactivated';
        $this->dispatch('toast', type: 'success', message: $msg);
    }

    public function tokenRules(): array
    {
        return [
            'tokenForm.token' => ['required', 'string', 'unique:'.Tables::name('ordering_tokens').',token'],
            'tokenForm.material_id' => ['required', 'exists:'.Tables::name('materials').',id'],
            'tokenForm.unit_purchase' => ['required', 'string'],
            'tokenForm.default_qty' => ['required', 'numeric', 'min:0'],
            'tokenForm.enabled' => ['boolean'],
            'tokenForm.expires_at' => ['nullable', 'date'],
        ];
    }

    public function saveToken(): void
    {
        $this->validate($this->tokenRules());

        OrderingToken::query()->create($this->tokenForm);

        $this->showTokenModal = false;
        $this->dispatch('toast', type: 'success', message: 'Token issued successfully');
    }

    public function closeMaterialModal(): void
    {
        $this->showMaterialModal = false;
    }

    public function materialRules(): array
    {
        return [
            'materialForm.sku' => ['required', 'string', Rule::unique(Tables::name('materials'), 'sku')->ignore($this->editingMaterialId)],
            'materialForm.name' => ['required', 'string', 'max:255'],
            'materialForm.tax_code' => ['required', 'string'],
            'materialForm.unit_stock' => ['required', 'string', 'max:50'],
            'materialForm.unit_purchase_default' => ['required', 'string', 'max:50'],
            'materialForm.min_stock' => ['nullable', 'numeric', 'min:0'],
            'materialForm.max_stock' => ['nullable', 'numeric', 'min:0'],
            'materialForm.category_id' => ['nullable', 'exists:'.Tables::name('material_categories').',id'],
            'materialForm.current_stock' => ['nullable', 'numeric'],
            'materialForm.manufacturer_name' => ['nullable', 'string', 'max:255'],
            'materialForm.applicable_regulation' => ['nullable', 'string', 'max:1000'],
            'materialForm.ghs_mark_options' => ['nullable', 'array'],
            'materialForm.protective_equipment' => ['nullable', 'string', 'max:1000'],
            'materialForm.unit_price' => ['nullable', 'numeric', 'min:0'],
            'materialForm.conversion_factor_purchase_to_stock' => ['nullable', 'numeric', 'min:0'],
            'materialForm.conversions' => ['nullable', 'array'],
            'materialForm.conversions.*.from_unit' => ['required', 'string', 'max:50'],
            'materialForm.conversions.*.factor' => ['required', 'numeric', 'min:0'],
            'materialForm.preferred_supplier_id' => ['nullable', 'exists:'.Tables::name('suppliers').',id'],
            'materialForm.separate_shipping' => ['boolean'],
            'materialForm.shipping_fee_per_order' => ['nullable', 'numeric', 'min:0'],
            'materialForm.is_chemical' => ['boolean'],
            'materialForm.cas_no' => ['nullable', 'string', 'max:100'],
            'materialForm.physical_state' => ['nullable', 'string', 'max:255'],
            'materialForm.ghs_hazard_details' => ['nullable', 'string', 'max:2000'],
            'materialForm.specified_quantity' => ['nullable', 'numeric', 'min:0'],
            'materialForm.emergency_contact' => ['nullable', 'string', 'max:255'],
            'materialForm.disposal_method' => ['nullable', 'string', 'max:2000'],
            'materialForm.moq' => ['nullable', 'numeric', 'min:0'],
            'materialForm.pack_size' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function taxCodeOptions(): array
    {
        return [
            'standard' => __('procflow::materials.form.tax_code_options.standard'),
            'reduced' => __('procflow::materials.form.tax_code_options.reduced'),
            'zero' => __('procflow::materials.form.tax_code_options.zero'),
            'exempt' => __('procflow::materials.form.tax_code_options.exempt'),
        ];
    }

    public function getTaxCodesProperty(): array
    {
        $taxOptions = $this->taxCodeOptions();
        $codes = Settings::taxCodes();

        $out = [];
        foreach ($codes as $code) {
            $out[$code] = $taxOptions[$code] ?? ucfirst($code);
        }

        return $out;
    }

    public function saveMaterial(): void
    {
        $data = $this->validate($this->materialRules());
        $payload = $data['materialForm'];

        // Join GHS mark options back to string
        $payload['ghs_mark'] = ! empty($payload['ghs_mark_options']) ? implode(',', $payload['ghs_mark_options']) : null;
        unset($payload['ghs_mark_options']);

        // Handle numeric fields that are NOT NULL in DB
        $payload['shipping_fee_per_order'] = (float) ($payload['shipping_fee_per_order'] ?? 0);
        $payload['min_stock'] = (float) ($payload['min_stock'] ?? 0);

        // Separate conversion factor from main payload
        $factor = (float) ($payload['conversion_factor_purchase_to_stock'] ?? 1.0);
        unset($payload['conversion_factor_purchase_to_stock']);

        $conversions = $payload['conversions'] ?? [];
        unset($payload['conversions']);

        if ($this->editingMaterialId) {
            /** @var Material $m */
            $m = Material::query()->findOrFail($this->editingMaterialId);
            $m->update($payload);
        } else {
            /** @var Material $m */
            $m = Material::query()->create($payload);
        }

        // Upsert unit conversion for Default Purchase -> Stock
        $this->upsertConversion($m, [
            'from_unit' => $m->unit_purchase_default,
            'to_unit' => $m->unit_stock,
            'factor' => $factor,
        ]);

        // Upsert additional conversions
        // First delete existing ones that are not the default purchase unit
        UnitConversion::query()
            ->where('material_id', $m->id)
            ->where('to_unit', $m->unit_stock)
            ->where('from_unit', '!=', $m->unit_purchase_default)
            ->delete();

        foreach ($conversions as $conv) {
            if (! empty($conv['from_unit']) && ! empty($conv['factor'])) {
                $this->upsertConversion($m, [
                    'from_unit' => $conv['from_unit'],
                    'to_unit' => $m->unit_stock,
                    'factor' => (float) $conv['factor'],
                ]);
            }
        }

        $this->showMaterialModal = false;
        $this->dispatch('toast', type: 'success', message: 'Material saved');
    }

    protected function resetMaterialForm(): void
    {
        $this->materialForm = [
            'sku' => null,
            'name' => null,
            'tax_code' => 'standard',
            'unit_stock' => null,
            'unit_purchase_default' => null,
            'min_stock' => 0,
            'max_stock' => null,
            'category_id' => null,
            'current_stock' => null,
            'manufacturer_name' => null,
            'storage_location' => null,
            'applicable_regulation' => null,
            'ghs_mark' => null,
            'ghs_mark_options' => [],
            'protective_equipment' => null,
            'unit_price' => null,
            'conversion_factor_purchase_to_stock' => 1.0,
            'conversions' => [],
            'preferred_supplier_id' => null,
            'separate_shipping' => false,
            'shipping_fee_per_order' => null,
            'manage_by_lot' => false,
            'is_chemical' => false,
            'cas_no' => null,
            'physical_state' => null,
            'ghs_hazard_details' => null,
            'specified_quantity' => null,
            'specified_quantity_ratio' => null,
            'emergency_contact' => null,
            'disposal_method' => null,
            'moq' => null,
            'pack_size' => null,
        ];
    }

    public function addConversion(): void
    {
        $this->materialForm['conversions'][] = ['from_unit' => '', 'factor' => 1.0];
    }

    public function removeConversion(int $index): void
    {
        unset($this->materialForm['conversions'][$index]);
        $this->materialForm['conversions'] = array_values($this->materialForm['conversions']);
    }

    protected function upsertConversion(Material $material, array $payload): void
    {
        // Skip if both are empty (nothing to define)
        if (empty($payload['from_unit']) && empty($payload['to_unit'])) {
            return;
        }

        // If one is empty and the other is not, it's still a partial conversion definition,
        // but it might lead to duplicate entries in unique constraint if not careful.
        // However, the DB now allows NULLs, so we can proceed.

        UnitConversion::query()->updateOrCreate(
            [
                'material_id' => $material->id,
                'from_unit' => $payload['from_unit'],
                'to_unit' => $payload['to_unit'],
            ],
            [
                'factor' => (float) ($payload['factor'] ?? 1.0),
            ]
        );
    }

    public function openSdsModal(int $materialId): void
    {
        $this->sdsMaterialId = $materialId;
        $this->sdsUpload = null;
        $this->showSdsModal = true;
    }

    public function uploadSds(): void
    {
        $this->validate();

        /** @var Material $m */
        $m = Material::query()->findOrFail($this->sdsMaterialId);

        // This assumes Spatie Media Library is installed and used in Material model
        if (method_exists($m, 'addMedia')) {
            $m->addMedia($this->sdsUpload->getRealPath())
                ->usingFileName($this->sdsUpload->getClientOriginalName())
                ->toMediaCollection('sds');
        }

        $this->showSdsModal = false;
        $this->dispatch('toast', type: 'success', message: 'SDS uploaded successfully');
    }

    public function deleteSds(): void
    {
        /** @var Material $m */
        $m = Material::query()->findOrFail($this->sdsMaterialId);

        if (method_exists($m, 'clearMediaCollection')) {
            $m->clearMediaCollection('sds');
        }

        $this->showSdsModal = false;
        $this->dispatch('toast', type: 'success', message: 'SDS deleted');
    }
};

?>

<div class="p-6 space-y-6">
    <x-procflow::topmenu />
    <h1 class="text-xl font-semibold">{{ __('procflow::materials.title') }}</h1>

    <div class="flex flex-wrap items-end gap-4">
        <div class="grow max-w-96">
            <flux:input wire:model.live.debounce.300ms="q" placeholder="{{ __('procflow::materials.filters.search_placeholder') }}" />
        </div>
        <div>
            <label class="block text-sm text-neutral-600 mb-1">{{ __('procflow::materials.form.applicable_regulation') }}</label>
            <flux:input wire:model.live.debounce.300ms="regulation_q" placeholder="{{ __('procflow::materials.filters.regulation_placeholder') }}" />
        </div>
        <div>
            <label class="block text-sm text-neutral-600 mb-1">{{ __('procflow::materials.filters.category') }}</label>
            <select class="w-60 border rounded p-2 bg-white dark:bg-neutral-900" wire:model.live="category_id">
                <option value="">{{ __('procflow::materials.filters.all') }}</option>
                @foreach($this->categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-center gap-4 h-10">
            <flux:checkbox wire:model.live="is_chemical_only" label="{{ __('procflow::materials.filters.chemical_only') }}" />
            <flux:checkbox wire:model.live="is_alert_only" label="{{ __('procflow::materials.filters.alert_only') }}" />
        </div>
        <div>
            <flux:button variant="primary" wire:click="openCreateMaterial">{{ __('procflow::materials.buttons.new') }}</flux:button>
        </div>
    </div>

    <div class="mt-4">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('procflow::materials.table.ghs') }}</flux:table.column>
                <flux:table.column>{{ __('procflow::materials.table.sku') }}</flux:table.column>
                <flux:table.column>{{ __('procflow::materials.table.name') }}</flux:table.column>
                <flux:table.column>{{ __('procflow::materials.form.applicable_regulation') }}</flux:table.column>
                <flux:table.column>{{ __('procflow::materials.filters.category') }}</flux:table.column>
                <flux:table.column>{{ __('procflow::materials.table.manufacturer') }}</flux:table.column>
                <flux:table.column>{{ __('procflow::materials.table.stock') }}</flux:table.column>
                <flux:table.column>{{ __('procflow::materials.table.min_stock') }}</flux:table.column>
                <flux:table.column>{{ __('procflow::materials.table.max_stock') }}</flux:table.column>
                <flux:table.column>{{ __('procflow::materials.table.unit') }}</flux:table.column>
                <flux:table.column>{{ __('procflow::materials.table.moq') }}</flux:table.column>
                <flux:table.column>{{ __('procflow::materials.table.pack_size') }}</flux:table.column>
                <flux:table.column>{{ __('procflow::materials.table.unit_price') }}</flux:table.column>
                <flux:table.column>{{ __('procflow::materials.table.is_chemical') }}</flux:table.column>
                <flux:table.column align="end">{{ __('procflow::materials.table.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($this->materials as $m)
                    @php
                        $stockValue = $m->manage_by_lot ? (float) ($m->lots_sum_qty_on_hand ?? 0) : (float) ($m->current_stock ?? 0);
                        $low = !is_null($m->min_stock) && $stockValue < (float) $m->min_stock;
                        $overLimit = $m->is_chemical && !is_null($m->specified_quantity) && $m->specified_quantity > 0 && $stockValue >= (float) $m->specified_quantity;
                    @endphp
                    <flux:table.row :class="$low || $overLimit ? 'bg-red-50/40 dark:bg-red-950/20' : ''">
                        <flux:table.cell>
                            @php($icons = method_exists($m, 'ghsIconNames') ? $m->ghsIconNames() : [])
                            @if(!empty($icons))
                                <div class="flex flex-wrap items-center gap-1">
                                    @foreach($icons as $icon)
                                        <flux:icon :name="$icon" class="size-6" />
                                    @endforeach
                                </div>
                            @else
                                <div class="w-10 h-10 bg-neutral-100 dark:bg-neutral-800 text-[10px] grid place-items-center text-neutral-400">{{ __('procflow::materials.table.na') }}</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">{{ $m->sku }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <span class="font-medium">{{ $m->name }}</span>
                                @if(!($m->is_active ?? true))
                                    <flux:badge size="sm" color="zinc">{{ __('procflow::materials.badges.inactive') }}</flux:badge>
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
                                    <flux:badge size="sm" color="purple">{{ __('procflow::materials.badges.lot') }}</flux:badge>
                                @endif
                                @php($hasSds = \Illuminate\Support\Facades\Schema::hasTable('media') ? (bool) $m->getFirstMedia('sds') : false)
                                <flux:badge size="sm" color="{{ $hasSds ? 'emerald' : 'zinc' }}">{{ $hasSds ? __('procflow::materials.sds.badge_has') : __('procflow::materials.sds.badge_none') }}</flux:badge>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell class="text-neutral-500">{{ $m->manufacturer_name }}</flux:table.cell>
                        <flux:table.cell :class="$low || $overLimit ? 'text-red-600 font-medium' : ''">
                            {{ $stockValue }}
                            @if($overLimit)
                                <flux:tooltip content="指定数量を超過または到達しています">
                                    <flux:icon name="exclamation-triangle" class="size-4 inline text-red-600 ml-1" />
                                </flux:tooltip>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ is_null($m->min_stock) ? '-' : (float) $m->min_stock }}</flux:table.cell>
                        <flux:table.cell>{{ is_null($m->max_stock) ? '-' : (float) $m->max_stock }}</flux:table.cell>
                        <flux:table.cell class="text-neutral-500">{{ $m->unit_stock }}</flux:table.cell>
                        <flux:table.cell>
                            {{ is_null($m->moq) ? __('procflow::materials.table.not_set') : (string) (float) $m->moq }}
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ is_null($m->pack_size) ? __('procflow::materials.table.not_set') : (string) (float) $m->pack_size }}
                        </flux:table.cell>
                        <flux:table.cell>@if(!is_null($m->unit_price)) {{ \Lastdino\ProcurementFlow\Support\Format::moneyUnitPriceMaterials($m->unit_price) }} @endif</flux:table.cell>
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
                                        <flux:menu.item href="{{ route('procurement.materials.show', ['material' => $m->id]) }}" icon="clipboard-document-check">{{ __('procflow::materials.buttons.view') }}</flux:menu.item>
                                        <flux:menu.item href="{{ route('procurement.materials.issue', ['material' => $m->id]) }}" icon="arrow-left-start-on-rectangle">{{ __('procflow::materials.buttons.issue') }}</flux:menu.item>
                                        <flux:menu.item href="{{ route('procurement.settings.labels') }}" icon="qr-code">{{ __('procflow::materials.buttons.shelf_labels') }}</flux:menu.item>
                                        <flux:menu.item icon="qr-code" wire:click="openTokenModal({{ $m->id }})">{{ __('procflow::materials.buttons.issue_token') }}</flux:menu.item>
                                        @php($sds = \Illuminate\Support\Facades\Schema::hasTable('media') ? $m->getFirstMedia('sds') : null)
                                        @if($sds)
                                            @php($dl = URL::temporarySignedRoute('procurement.materials.sds.download', now()->addMinutes(10), ['material' => $m->id]))
                                            <flux:menu.item icon="document-text" href="{{ $dl }}" target="_blank">{{ __('procflow::materials.sds.download') }}</flux:menu.item>
                                        @endif
                                        <flux:menu.item icon="arrow-up-tray" wire:click="openSdsModal({{ $m->id }})">{{ __('procflow::materials.sds.open_modal') }}</flux:menu.item>
                                        <flux:menu.item icon="pencil-square" variant="danger" wire:click="openEditMaterial({{ $m->id }})">{{ __('procflow::materials.buttons.edit') }}</flux:menu.item>
                                        @if($m->is_active)
                                            <flux:menu.item icon="no-symbol" wire:click="toggleActive({{ $m->id }})">{{ __('procflow::materials.buttons.disable') }}</flux:menu.item>
                                        @else
                                            <flux:menu.item icon="power" wire:click="toggleActive({{ $m->id }})">{{ __('procflow::materials.buttons.enable') }}</flux:menu.item>
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="14" class="py-6 text-center text-neutral-500">{{ __('procflow::materials.table.no_materials') }}</flux:cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <div class="mt-4">
        {{ $this->materials->links() }}
    </div>

    {{-- Modal for create/edit material (Flux UI) --}}
    <flux:modal wire:model.self="showMaterialModal" name="material-form">
        <div class="w-full md:w-[56rem] max-w-full">
            <h3 class="text-lg font-semibold mb-3">{{ $editingMaterialId ? __('procflow::materials.modal.material_form_title_edit') : __('procflow::materials.modal.material_form_title_new') }}</h3>

            <div class="space-y-6">
                <div class="space-y-3">
                    <flux:heading size="sm">{{ __('procflow::materials.sections.basic') }}</flux:heading>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <flux:input wire:model="materialForm.sku" label="{{ __('procflow::materials.form.sku') }}"/>
                        <flux:textarea rows="2" wire:model="materialForm.name" label="{{ __('procflow::materials.form.name') }}" />
                        <flux:input wire:model="materialForm.manufacturer_name" label="{{ __('procflow::materials.form.manufacturer_name') }}"/>
                        <flux:field>
                            <flux:label>{{ __('procflow::materials.form.category') }}</flux:label>
                            <flux:select wire:model="materialForm.category_id">
                                <option value="">-</option>
                                @foreach($this->categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </flux:select>
                            <flux:error name="materialForm.category_id" />
                        </flux:field>
                    </div>
                </div>

                <flux:separator />

                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <flux:heading size="sm">{{ __('procflow::materials.sections.units_conversion') }}</flux:heading>
                        <flux:button size="sm" variant="outline" wire:click="addConversion">{{ __('procflow::materials.form.add_conversion') }}</flux:button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <flux:input wire:model="materialForm.unit_stock" label="{{ __('procflow::materials.form.unit_stock') }}"/>
                        <flux:input placeholder="{{ __('procflow::materials.form.unit_order_placeholder') }}" wire:model="materialForm.unit_purchase_default" label="{{ __('procflow::materials.form.unit_order') }}"/>
                        <flux:input type="number" step="0.000001" placeholder="{{ __('procflow::materials.form.conversion_placeholder') }}" wire:model="materialForm.conversion_factor_purchase_to_stock" label="{{ __('procflow::materials.form.conversion') }}"/>
                    </div>

                    @if(!empty($materialForm['conversions']))
                        <div class="space-y-2">
                            @foreach($materialForm['conversions'] as $index => $conv)
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end" wire:key="conv-{{ $index }}">
                                    <flux:input wire:model="materialForm.conversions.{{ $index }}.from_unit" label="{{ __('procflow::materials.form.from_unit') }}" />
                                    <div class="flex items-center h-10 text-neutral-500 justify-center">→ {{ $materialForm['unit_stock'] }}</div>
                                    <flux:input type="number" step="0.000001" wire:model="materialForm.conversions.{{ $index }}.factor" label="{{ __('procflow::materials.form.factor') }}" />
                                    <div class="flex h-10 items-end">
                                        <flux:button variant="danger" size="sm" wire:click="removeConversion({{ $index }})">{{ __('procflow::materials.form.remove') }}</flux:button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <flux:separator />

                <div class="space-y-3">
                    <flux:heading size="sm">{{ __('procflow::materials.sections.stock_category_supplier') }}</flux:heading>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <flux:input type="number" step="0.000001" wire:model="materialForm.current_stock" label="{{ __('procflow::materials.form.current_stock') }}"/>
                        <flux:input type="number" step="0.000001" min="0" wire:model="materialForm.min_stock" label="{{ __('procflow::materials.form.min_stock') }}"/>
                        <flux:input type="number" step="0.000001" min="0" wire:model="materialForm.max_stock" label="{{ __('procflow::materials.form.max_stock') }}"/>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <flux:field>
                            <flux:label>{{ __('procflow::materials.form.tax_code') }}</flux:label>
                            <flux:select wire:model="materialForm.tax_code">
                                @foreach($this->taxCodes as $code => $label)
                                    <option value="{{ $code }}">{{ $label }}</option>
                                @endforeach
                            </flux:select>
                            <flux:error name="materialForm.tax_code" />
                        </flux:field>

                        <flux:field class="md:col-span-2">
                            <flux:label>{{ __('procflow::materials.form.preferred_supplier') }}</flux:label>
                            <flux:select wire:model="materialForm.preferred_supplier_id">
                                <option value="">-</option>
                                @foreach($this->suppliers as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </flux:select>
                            <flux:error name="materialForm.preferred_supplier_id" />
                        </flux:field>
                    </div>
                </div>

                <flux:separator />

                <div class="space-y-3">
                    <flux:heading size="sm">{{ __('procflow::materials.sections.ordering_pricing') }}</flux:heading>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <flux:input type="number" step="0.000001" min="0" wire:model="materialForm.moq" label="{{ __('procflow::materials.form.moq') }}"/>
                        <flux:input type="number" step="0.01" wire:model="materialForm.unit_price" label="{{ __('procflow::materials.form.unit_price') }}"/>
                    </div>
                </div>


                <flux:separator />

                <div class="space-y-3">
                    <flux:heading size="sm">{{ __('procflow::materials.sections.options') }}</flux:heading>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="md:col-span-1 flex items-center gap-2">
                            <flux:switch wire:model.live="materialForm.is_chemical" label="{{ __('procflow::materials.form.is_chemical') }}" align="left"/>
                        </div>
                    </div>

                    @if($materialForm['is_chemical'])
                        <div class="space-y-4 p-4 rounded-lg bg-blue-50/30 dark:bg-blue-950/10 border border-blue-100 dark:border-blue-900">
                            <flux:heading size="sm">{{ __('procflow::materials.sections.chemical_details') }}</flux:heading>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <flux:input wire:model="materialForm.cas_no" label="{{ __('procflow::materials.form.cas_no') }}"/>
                                <flux:input wire:model="materialForm.physical_state" label="{{ __('procflow::materials.form.physical_state') }}" placeholder="{{ __('procflow::materials.form.physical_state_placeholder') }}"/>
                                <flux:input wire:model="materialForm.emergency_contact" label="{{ __('procflow::materials.form.emergency_contact') }}"/>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <flux:textarea rows="2" wire:model="materialForm.ghs_hazard_details" label="{{ __('procflow::materials.form.ghs_hazard_details') }}" placeholder="{{ __('procflow::materials.form.ghs_hazard_details_placeholder') }}"/>
                                <flux:textarea rows="2" wire:model="materialForm.disposal_method" label="{{ __('procflow::materials.form.disposal_method') }}"/>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <flux:input type="number" step="0.000001" wire:model="materialForm.specified_quantity" label="{{ __('procflow::materials.form.specified_quantity') }}"/>
                            </div>

                            <flux:separator variant="subtle" />

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <flux:textarea rows="2" label="{{ __('procflow::materials.form.applicable_regulation') }}" wire:model="materialForm.applicable_regulation"/>
                                <flux:textarea rows="2" label="{{ __('procflow::materials.form.protective_equipment') }}" wire:model="materialForm.protective_equipment"/>
                                <div class="md:col-span-1">
                                    <flux:field >
                                        <flux:label>{{ __('procflow::materials.form.ghs_mark') }}</flux:label>
                                        @php($ghsKeys = \Lastdino\ProcurementFlow\Models\Material::defaultGhsKeys())
                                        <div class="flex flex-wrap gap-2 py-2">
                                            @foreach($ghsKeys as $key)
                                                @php($iconName = strtolower(preg_replace('/(GHS)(\d+)/i', '$1-$2', $key) ?? $key))
                                                <label class="inline-flex items-center gap-2 px-2 py-1 rounded-md border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition-colors cursor-pointer" title="{{ __('procflow::materials.form.ghs_labels.'.$key) ?: $key }}">
                                                    <flux:checkbox size="sm" value="{{ $key }}" wire:model="materialForm.ghs_mark_options" />
                                                    <flux:icon :name="$iconName" class="size-5" />
                                                </label>
                                            @endforeach
                                        </div>
                                        @error('materialForm.ghs_mark_options') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
                                        @error('materialForm.ghs_mark_options.*') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
                                    </flux:field>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="md:col-span-1 flex items-center gap-2">
                            <flux:switch wire:model="materialForm.sync_to_monox" label="monox連動" align="left"/>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="md:col-span-1 flex items-center gap-2">
                            <flux:switch wire:model.live="materialForm.separate_shipping" label="{{ __('procflow::materials.form.separate_shipping') }}" align="left"/>
                        </div>
                        <div>
                            <flux:input type="number" step="0.01" min="0" class="disabled:opacity-60" wire:model="materialForm.shipping_fee_per_order" :disabled="! $this->materialForm['separate_shipping']" label="{{ __('procflow::materials.form.shipping_fee_per_order') }}"/>
                            <flux:text class="text-xs mt-1">{{ __('procflow::materials.form.shipping_fee_help') }}</flux:text>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 flex items-center justify-end gap-3">
                <flux:button variant="outline" x-on:click="$flux.modal('material-form').close()">{{ __('procflow::materials.buttons.cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="saveMaterial" wire:loading.attr="disabled">
                    <span wire:loading.remove>{{ __('procflow::materials.buttons.save') }}</span>
                    <span wire:loading>{{ __('procflow::materials.buttons.saving') }}</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal for issue ordering token --}}
    <flux:modal wire:model="showTokenModal" name="issue-token">
        <div class="w-full md:w-[40rem] max-w-full space-y-4">
            <flux:heading size="sm">{{ __('procflow::materials.token_modal.title') }}</flux:heading>
            <div class="space-y-3">
                <flux:input wire:model.defer="tokenForm.token" label="{{ __('procflow::materials.token_modal.token') }}" />
                <div class="grid gap-3 md:grid-cols-3">
                    <flux:input wire:model.defer="tokenForm.unit_purchase" label="{{ __('procflow::materials.token_modal.unit_purchase') }}" placeholder="e.g. case" />
                    <flux:input type="number" step="0.000001" min="0" wire:model.defer="tokenForm.default_qty" label="{{ __('procflow::materials.token_modal.default_qty') }}" />
                    <flux:switch wire:model.defer="tokenForm.enabled" label="{{ __('procflow::materials.token_modal.enabled') }}" />
                </div>
                <flux:input type="datetime-local" wire:model.defer="tokenForm.expires_at" label="{{ __('procflow::materials.token_modal.expires_at') }}" />
            </div>
            <div class="flex justify-end gap-2">
                <flux:button variant="outline" wire:click="$set('showTokenModal', false)">{{ __('procflow::materials.buttons.cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="saveToken">{{ __('procflow::materials.token_modal.issue') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal for SDS upload --}}
    <flux:modal wire:model.self="showSdsModal" name="sds-form">
        <div class="w-full md:w-[36rem] max-w-full">
            <h3 class="text-lg font-semibold mb-3">{{ __('procflow::materials.sds.title') }}</h3>
            <div class="space-y-4">
                @if($sdsMaterialId)
                    @php($current = \Illuminate\Support\Facades\Schema::hasTable('media') ? optional(\Lastdino\ProcurementFlow\Models\Material::find($sdsMaterialId))->getFirstMedia('sds') : null)
                    @if($current)
                        <div class="flex items-center justify-between p-3 rounded bg-neutral-100 dark:bg-neutral-800">
                            <div class="flex items-center gap-3">
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-600 text-white text-sm">PDF</span>
                                <div>
                                    <div class="font-medium">{{ $current->name }} ({{ $current->file_name }})</div>
                                    <div class="text-xs text-neutral-500">{{ number_format($current->size / 1024, 1) }} KB</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <a class="px-3 py-1.5 rounded bg-blue-600 text-white" href="{{ $current->getUrl() }}" target="_blank" rel="noopener">ダウンロード</a>
                                <button class="px-3 py-1.5 rounded bg-red-600 text-white" wire:click="deleteSds">削除</button>
                            </div>
                        </div>
                    @else
                        <div class="text-neutral-500 text-sm">{{ __('procflow::materials.sds.empty') }}</div>
                    @endif
                @endif

                <div>
                    <label class="block text-sm text-neutral-600 mb-1">{{ __('procflow::materials.sds.upload_label') }}</label>
                    <input type="file" wire:model="sdsUpload" accept="application/pdf" class="w-full border rounded p-2 bg-white dark:bg-neutral-900" />
                    @error('sdsUpload') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
                    <div class="mt-3 flex items-center gap-2">
                        <flux:button wire:click="uploadSds" :disabled="!$sdsUpload" variant="primary">{{ __('procflow::materials.buttons.save') }}</flux:button>
                        <flux:button variant="ghost" @click="$dispatch('close-modal', { name: 'sds-form' })">{{ __('procflow::materials.buttons.cancel') }}</flux:button>
                    </div>
                    <div wire:loading wire:target="sdsUpload" class="text-sm text-neutral-500 mt-1">{{ __('procflow::materials.buttons.processing') }}</div>
                </div>
            </div>
        </div>
    </flux:modal>
</div>
