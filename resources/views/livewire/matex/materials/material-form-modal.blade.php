<?php

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lastdino\Matex\Models\Material;
use Lastdino\Matex\Models\MaterialCategory;
use Lastdino\Matex\Models\UnitConversion;
use Lastdino\Matex\Support\Settings;
use Lastdino\Matex\Support\Tables;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public bool $show = false;

    public ?int $editingMaterialId = null;

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
        'conversion_factor_purchase_to_stock' => 1.0,
        'conversions' => [],
        'preferred_supplier_id' => null,
        'preferred_supplier_contact_id' => null,
        'separate_shipping' => false,
        'shipping_fee_per_order' => null,
        'is_chemical' => false,
        'cas_no' => null,
        'physical_state' => null,
        'ghs_hazard_details' => null,
        'specified_quantity' => null,
        'emergency_contact' => null,
        'disposal_method' => null,
        'default_purchase_note' => null,
        'moq' => null,
        'pack_size' => null,
        'sync_to_monox' => false,
        'manage_by_lot' => false,
    ];

    #[On('matex:open-material-form')]
    public function open(?int $id = null): void
    {
        $this->resetMaterialForm();

        if ($id) {
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
                'preferred_supplier_contact_id' => $m->preferred_supplier_contact_id,
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
                'default_purchase_note' => $m->default_purchase_note,
                'moq' => (float) $m->moq,
                'pack_size' => (float) $m->pack_size,
                'sync_to_monox' => (bool) ($m->sync_to_monox ?? false),
            ];
        } else {
            $this->editingMaterialId = null;
        }

        $this->show = true;
    }

    public function getCategoriesProperty()
    {
        return MaterialCategory::query()->orderBy('name')->get();
    }

    public function getSuppliersProperty()
    {
        return \Lastdino\Matex\Models\Supplier::query()->with('contacts')->orderBy('name')->get();
    }

    public function getPreferredSupplierContactsProperty()
    {
        if (! $this->materialForm['preferred_supplier_id']) {
            return [];
        }

        return \Lastdino\Matex\Models\SupplierContact::query()
            ->where('supplier_id', $this->materialForm['preferred_supplier_id'])
            ->orderBy('name')
            ->get();
    }

    public function taxCodeOptions(): array
    {
        return [
            'standard' => __('matex::materials.form.tax_code_options.standard'),
            'reduced' => __('matex::materials.form.tax_code_options.reduced'),
            'zero' => __('matex::materials.form.tax_code_options.zero'),
            'exempt' => __('matex::materials.form.tax_code_options.exempt'),
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

    protected function materialRules(): array
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
            'materialForm.preferred_supplier_contact_id' => ['nullable', 'exists:'.Tables::name('supplier_contacts').',id'],
            'materialForm.separate_shipping' => ['boolean'],
            'materialForm.shipping_fee_per_order' => ['nullable', 'numeric', 'min:0'],
            'materialForm.is_chemical' => ['boolean'],
            'materialForm.cas_no' => ['nullable', 'string', 'max:100'],
            'materialForm.physical_state' => ['nullable', 'string', 'max:255'],
            'materialForm.ghs_hazard_details' => ['nullable', 'string', 'max:2000'],
            'materialForm.specified_quantity' => ['nullable', 'numeric', 'min:0'],
            'materialForm.emergency_contact' => ['nullable', 'string', 'max:255'],
            'materialForm.disposal_method' => ['nullable', 'string', 'max:2000'],
            'materialForm.default_purchase_note' => ['nullable', 'string', 'max:2000'],
            'materialForm.moq' => ['nullable', 'numeric', 'min:0'],
            'materialForm.pack_size' => ['nullable', 'numeric', 'min:0'],
            'materialForm.sync_to_monox' => ['boolean'],
            'materialForm.manage_by_lot' => ['boolean'],
        ];
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

        $this->show = false;
        $this->dispatch('matex:material-saved', materialId: $m->id);
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
            'applicable_regulation' => null,
            'ghs_mark' => null,
            'ghs_mark_options' => [],
            'protective_equipment' => null,
            'unit_price' => null,
            'conversion_factor_purchase_to_stock' => 1.0,
            'conversions' => [],
            'preferred_supplier_id' => null,
            'preferred_supplier_contact_id' => null,
            'separate_shipping' => false,
            'shipping_fee_per_order' => null,
            'manage_by_lot' => false,
            'is_chemical' => false,
            'cas_no' => null,
            'physical_state' => null,
            'ghs_hazard_details' => null,
            'specified_quantity' => null,
            'emergency_contact' => null,
            'disposal_method' => null,
            'default_purchase_note' => null,
            'moq' => null,
            'pack_size' => null,
            'sync_to_monox' => false,
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
        if (empty($payload['from_unit']) && empty($payload['to_unit'])) {
            return;
        }

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
};

?>

<flux:modal wire:model.self="show" name="material-form">
    <div class="w-full md:w-4xl max-w-full">
        <h3 class="text-lg font-semibold mb-3">{{ $editingMaterialId ? __('matex::materials.modal.material_form_title_edit') : __('matex::materials.modal.material_form_title_new') }}</h3>

        <div class="space-y-6">
            <div class="space-y-3">
                <flux:heading size="sm">{{ __('matex::materials.sections.basic') }}</flux:heading>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <flux:input wire:model="materialForm.sku" label="{{ __('matex::materials.form.sku') }}"/>
                    <flux:textarea rows="2" wire:model="materialForm.name" label="{{ __('matex::materials.form.name') }}" />
                    <flux:input wire:model="materialForm.manufacturer_name" label="{{ __('matex::materials.form.manufacturer_name') }}"/>
                    <flux:field>
                        <flux:label>{{ __('matex::materials.form.category') }}</flux:label>
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
                    <flux:heading size="sm">{{ __('matex::materials.sections.units_conversion') }}</flux:heading>
                    <flux:button size="sm" variant="outline" wire:click="addConversion">{{ __('matex::materials.form.add_conversion') }}</flux:button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <flux:input wire:model="materialForm.unit_stock" label="{{ __('matex::materials.form.unit_stock') }}"/>
                    <flux:input placeholder="{{ __('matex::materials.form.unit_order_placeholder') }}" wire:model="materialForm.unit_purchase_default" label="{{ __('matex::materials.form.unit_order') }}"/>
                    <flux:input type="number" step="0.000001" placeholder="{{ __('matex::materials.form.conversion_placeholder') }}" wire:model="materialForm.conversion_factor_purchase_to_stock" label="{{ __('matex::materials.form.conversion') }}"/>
                </div>

                @if(!empty($materialForm['conversions']))
                    <div class="space-y-2">
                        @foreach($materialForm['conversions'] as $index => $conv)
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end" wire:key="conv-{{ $index }}">
                                <flux:input wire:model="materialForm.conversions.{{ $index }}.from_unit" label="{{ __('matex::materials.form.from_unit') }}" />
                                <div class="flex items-center h-10 text-neutral-500 justify-center">→ {{ $materialForm['unit_stock'] }}</div>
                                <flux:input type="number" step="0.000001" wire:model="materialForm.conversions.{{ $index }}.factor" label="{{ __('matex::materials.form.factor') }}" />
                                <div class="flex h-10 items-end">
                                    <flux:button variant="danger" size="sm" wire:click="removeConversion({{ $index }})">{{ __('matex::materials.form.remove') }}</flux:button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <flux:separator />

            <div class="space-y-3">
                <flux:heading size="sm">{{ __('matex::materials.sections.stock_category_supplier') }}</flux:heading>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <flux:input type="number" step="0.000001" wire:model="materialForm.current_stock" label="{{ __('matex::materials.form.current_stock') }}"/>
                    <flux:input type="number" step="0.000001" min="0" wire:model="materialForm.min_stock" label="{{ __('matex::materials.form.min_stock') }}"/>
                    <flux:input type="number" step="0.000001" min="0" wire:model="materialForm.max_stock" label="{{ __('matex::materials.form.max_stock') }}"/>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <flux:field>
                        <flux:label>{{ __('matex::materials.form.tax_code') }}</flux:label>
                        <flux:select wire:model="materialForm.tax_code">
                            @foreach($this->taxCodes as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="materialForm.tax_code" />
                    </flux:field>

                    <flux:field class="md:col-span-1">
                        <flux:label>{{ __('matex::materials.form.preferred_supplier') }}</flux:label>
                        <flux:select wire:model.live="materialForm.preferred_supplier_id">
                            <option value="">-</option>
                            @foreach($this->suppliers as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="materialForm.preferred_supplier_id" />
                    </flux:field>

                    <flux:field class="md:col-span-1">
                        <flux:label>{{ __('matex::suppliers.form.preferred_supplier_contact') }}</flux:label>
                        <flux:select wire:model="materialForm.preferred_supplier_contact_id" :disabled="!$materialForm['preferred_supplier_id']">
                            <option value="">-</option>
                            @foreach($this->preferredSupplierContacts as $c)
                                <option value="{{ $c->id }}">{{ $c->department ? '['.$c->department.'] ' : '' }}{{ $c->name }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="materialForm.preferred_supplier_contact_id" />
                    </flux:field>
                </div>
            </div>

            <flux:separator />

            <div class="space-y-3">
                <flux:heading size="sm">{{ __('matex::materials.sections.ordering_pricing') }}</flux:heading>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <flux:input type="number" step="0.000001" min="0" wire:model="materialForm.moq" label="{{ __('matex::materials.form.moq') }}"/>
                    <flux:input type="number" step="0.000001" min="0" wire:model="materialForm.pack_size" label="{{ __('matex::materials.form.pack_size') }}"/>
                    <flux:input type="number" step="0.01" wire:model="materialForm.unit_price" label="{{ __('matex::materials.form.unit_price') }}"/>
                </div>
                <div class="grid grid-cols-1 gap-3">
                    <flux:textarea rows="2" wire:model="materialForm.default_purchase_note" label="発注時備考（デフォルト）" placeholder="見積No.など"/>
                </div>
            </div>


            <flux:separator />

            <div class="space-y-3">
                <flux:heading size="sm">{{ __('matex::materials.sections.options') }}</flux:heading>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div class="md:col-span-1 flex items-center gap-2">
                        <flux:switch wire:model.live="materialForm.is_chemical" label="{{ __('matex::materials.form.is_chemical') }}" align="left"/>
                    </div>
                    <div class="md:col-span-1 flex items-center gap-2">
                        <flux:switch wire:model="materialForm.manage_by_lot" label="{{ __('matex::materials.badges.lot') }}管理" align="left"/>
                    </div>
                </div>

                @if($materialForm['is_chemical'])
                    <div class="space-y-4 p-4 rounded-lg bg-blue-50/30 dark:bg-blue-950/10 border border-blue-100 dark:border-blue-900">
                        <flux:heading size="sm">{{ __('matex::materials.sections.chemical_details') }}</flux:heading>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <flux:input wire:model="materialForm.cas_no" label="{{ __('matex::materials.form.cas_no') }}"/>
                            <flux:input wire:model="materialForm.physical_state" label="{{ __('matex::materials.form.physical_state') }}" placeholder="{{ __('matex::materials.form.physical_state_placeholder') }}"/>
                            <flux:input wire:model="materialForm.emergency_contact" label="{{ __('matex::materials.form.emergency_contact') }}"/>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <flux:textarea rows="2" wire:model="materialForm.ghs_hazard_details" label="{{ __('matex::materials.form.ghs_hazard_details') }}" placeholder="{{ __('matex::materials.form.ghs_hazard_details_placeholder') }}"/>
                            <flux:textarea rows="2" wire:model="materialForm.disposal_method" label="{{ __('matex::materials.form.disposal_method') }}"/>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <flux:input type="number" step="0.000001" wire:model="materialForm.specified_quantity" label="{{ __('matex::materials.form.specified_quantity') }}"/>
                        </div>

                        <flux:separator variant="subtle" />

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <flux:textarea rows="2" label="{{ __('matex::materials.form.applicable_regulation') }}" wire:model="materialForm.applicable_regulation"/>
                            <flux:textarea rows="2" label="{{ __('matex::materials.form.protective_equipment') }}" wire:model="materialForm.protective_equipment"/>
                            <div class="md:col-span-1">
                                <flux:field >
                                    <flux:label>{{ __('matex::materials.form.ghs_mark') }}</flux:label>
                                    @php($ghsKeys = \Lastdino\Matex\Models\Material::defaultGhsKeys())
                                    <div class="flex flex-wrap gap-2 py-2">
                                        @foreach($ghsKeys as $key)
                                            @php($iconName = strtolower(preg_replace('/(GHS)(\d+)/i', '$1-$2', $key) ?? $key))
                                            <label class="inline-flex items-center gap-2 px-2 py-1 rounded-md border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition-colors cursor-pointer" title="{{ __('matex::materials.form.ghs_labels.'.$key) ?: $key }}">
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
                        <flux:switch wire:model.live="materialForm.separate_shipping" label="{{ __('matex::materials.form.separate_shipping') }}" align="left"/>
                    </div>
                    <div>
                        <flux:input type="number" step="0.01" min="0" class="disabled:opacity-60" wire:model="materialForm.shipping_fee_per_order" :disabled="! $this->materialForm['separate_shipping']" label="{{ __('matex::materials.form.shipping_fee_per_order') }}"/>
                        <flux:text class="text-xs mt-1">{{ __('matex::materials.form.shipping_fee_help') }}</flux:text>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4 flex items-center justify-end gap-3">
            <flux:button variant="outline" x-on:click="$flux.modal('material-form').close()">{{ __('matex::materials.buttons.cancel') }}</flux:button>
            <flux:button variant="primary" wire:click="saveMaterial" wire:loading.attr="disabled">
                <span wire:loading.remove>{{ __('matex::materials.buttons.save') }}</span>
                <span wire:loading>{{ __('matex::materials.buttons.saving') }}</span>
            </flux:button>
        </div>
    </div>
</flux:modal>
