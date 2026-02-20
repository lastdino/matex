<?php

use Illuminate\Contracts\View\View as ViewContract;
use Lastdino\ProcurementFlow\Models\Material;
use Lastdino\ProcurementFlow\Models\MaterialInspection;
use Lastdino\ProcurementFlow\Models\MaterialLot;
use Lastdino\ProcurementFlow\Models\MaterialRiskAssessment;
use Lastdino\ProcurementFlow\Models\StockMovement;
use Livewire\Component;

new class extends Component
{
    public int $materialId;

    public bool $showRaModal = false;
    public bool $showInspectionModal = false;
    public bool $showTransferModal = false;

    public ?int $transferLotId = null;
    public array $transferForm = [
        'qty' => null,
        'to_storage_location_id' => '',
        'reason' => '',
    ];

    public array $raForm = [
        'assessment_date' => null,
        'risk_level' => null,
        'assessment_results' => null,
        'countermeasures' => null,
        'next_assessment_date' => null,
        'assessor_name' => null,
    ];

    public array $inspectionForm = [
        'inspection_date' => null,
        'inspector_name' => null,
        'container_status' => true,
        'label_status' => true,
        'details' => null,
    ];

    public function mount(Material $material): void
    {
        $this->materialId = (int) $material->getKey();
        $this->raForm['assessment_date'] = now()->format('Y-m-d');
        $this->inspectionForm['inspection_date'] = now()->format('Y-m-d');
    }

    public function getMaterialProperty(): Material
    {
        /** @var Material $m */
        $m = Material::query()->with(['lots' => function ($q) {
            $q->orderBy('expiry_date')->orderBy('lot_no');
        }, 'riskAssessments' => function($q) {
            $q->orderByDesc('assessment_date');
        }, 'inspections' => function($q) {
            $q->orderByDesc('inspection_date');
        }])->findOrFail($this->materialId);

        return $m;
    }

    public function getLotsProperty()
    {
        return MaterialLot::query()
            ->where('material_id', $this->materialId)
            ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expiry_date')
            ->orderBy('lot_no')
            ->get();
    }

    public function getMovementsProperty()
    {
        return StockMovement::query()
            ->where('material_id', $this->materialId)
            ->orderByDesc('occurred_at')
            ->limit(200)
            ->get();
    }

    public function openRaModal(): void
    {
        $this->raForm = [
            'assessment_date' => now()->format('Y-m-d'),
            'risk_level' => null,
            'assessment_results' => null,
            'countermeasures' => null,
            'next_assessment_date' => null,
            'assessor_name' => null,
        ];
        $this->showRaModal = true;
    }

    public function saveRa(): void
    {
        $data = $this->validate([
            'raForm.assessment_date' => 'required|date',
            'raForm.risk_level' => 'nullable|string',
            'raForm.assessment_results' => 'nullable|string',
            'raForm.countermeasures' => 'nullable|string',
            'raForm.next_assessment_date' => 'nullable|date',
            'raForm.assessor_name' => 'nullable|string',
        ]);

        $payload = $data['raForm'];
        $payload['material_id'] = $this->materialId;

        MaterialRiskAssessment::create($payload);

        $this->showRaModal = false;
        $this->dispatch('toast', type: 'success', message: __('procflow::materials.ra.saved'));
    }

    public function openInspectionModal(): void
    {
        $this->inspectionForm = [
            'inspection_date' => now()->format('Y-m-d'),
            'inspector_name' => null,
            'container_status' => true,
            'label_status' => true,
            'details' => null,
        ];
        $this->showInspectionModal = true;
    }

    public function saveInspection(): void
    {
        $data = $this->validate([
            'inspectionForm.inspection_date' => 'required|date',
            'inspectionForm.inspector_name' => 'nullable|string',
            'inspectionForm.container_status' => 'boolean',
            'inspectionForm.label_status' => 'boolean',
            'inspectionForm.details' => 'nullable|string',
        ]);

        $payload = $data['inspectionForm'];
        $payload['material_id'] = $this->materialId;

        MaterialInspection::create($payload);

        $this->showInspectionModal = false;
        $this->dispatch('toast', type: 'success', message: __('procflow::materials.inspections.saved'));
    }

    public function openTransferModal(int $lotId): void
    {
        $lot = MaterialLot::findOrFail($lotId);
        $this->transferLotId = $lotId;
        $this->transferForm = [
            'qty' => (float) $lot->qty_on_hand,
            'to_storage_location_id' => $lot->storage_location_id,
            'reason' => '',
        ];
        $this->showTransferModal = true;
    }

    public function transfer(): void
    {
        $data = $this->validate([
            'transferForm.qty' => 'required|numeric|gt:0',
            'transferForm.to_storage_location_id' => 'required|exists:Lastdino\ProcurementFlow\Models\StorageLocation,id',
            'transferForm.reason' => 'nullable|string|max:255',
        ]);

        $lot = MaterialLot::findOrFail($this->transferLotId);
        $qtyToMove = (float) $this->transferForm['qty'];

        if ($qtyToMove > (float) $lot->qty_on_hand) {
            $this->addError('transferForm.qty', '移動数量が在庫数を超えています。');
            return;
        }

        if ((int) $this->transferForm['to_storage_location_id'] === (int) $lot->storage_location_id) {
            $this->addError('transferForm.to_storage_location_id', '移動先が現在の場所と同じです。');
            return;
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($lot, $qtyToMove) {
            $material = $lot->material;
            $user = \Illuminate\Support\Facades\Auth::user();

            // 1. Create Transfer Out Movement for source lot
            StockMovement::create([
                'material_id' => $material->id,
                'lot_id' => $lot->id,
                'type' => 'transfer_out',
                'source_type' => self::class,
                'source_id' => $this->materialId,
                'qty_base' => $qtyToMove,
                'unit' => $lot->unit,
                'occurred_at' => now(),
                'reason' => $this->transferForm['reason'],
                'causer_type' => $user ? get_class($user) : null,
                'causer_id' => $user?->getAuthIdentifier(),
            ]);

            // 2. Decrement source lot
            $lot->decrement('qty_on_hand', $qtyToMove);

            // 3. Find or Create destination lot
            $destLot = MaterialLot::query()
                ->where('material_id', $material->id)
                ->where('lot_no', $lot->lot_no)
                ->where('storage_location_id', $this->transferForm['to_storage_location_id'])
                ->first();

            if ($destLot) {
                $destLot->increment('qty_on_hand', $qtyToMove);
            } else {
                $destLot = $lot->replicate();
                $destLot->qty_on_hand = $qtyToMove;
                $destLot->storage_location_id = $this->transferForm['to_storage_location_id'];
                $destLot->save();
            }

            // 4. Create Transfer In Movement for destination lot
            StockMovement::create([
                'material_id' => $material->id,
                'lot_id' => $destLot->id,
                'type' => 'transfer_in',
                'source_type' => self::class,
                'source_id' => $this->materialId,
                'qty_base' => $qtyToMove,
                'unit' => $destLot->unit,
                'occurred_at' => now(),
                'reason' => $this->transferForm['reason'],
                'causer_type' => $user ? get_class($user) : null,
                'causer_id' => $user?->getAuthIdentifier(),
            ]);
        });

        $this->showTransferModal = false;
        $this->dispatch('toast', type: 'success', message: '在庫移動が完了しました。');
    }
};

?>

<div class="p-6 space-y-6">
    <x-procflow::topmenu />

    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <h1 class="text-xl font-semibold">
                {{ __('procflow::materials.show.title_prefix') }}: {{ $this->material->sku }} — {{ $this->material->name }}
            </h1>
            <div class="flex gap-1">
                @php
                    $stockValue = (float) ($this->material->lots->sum('qty_on_hand') ?? 0);
                    $low = !is_null($this->material->min_stock) && $stockValue < (float) $this->material->min_stock;
                    $overLimit = $this->material->is_chemical && !is_null($this->material->specified_quantity) && $this->material->specified_quantity > 0 && $stockValue >= (float) $this->material->specified_quantity;
                @endphp
                @if($this->material->is_chemical)
                    <flux:badge size="sm" color="blue" icon="beaker">{{ __('procflow::materials.table.is_chemical') }}</flux:badge>
                @endif

                @if($low)
                    <flux:badge size="sm" color="red" icon="exclamation-circle">在庫僅少</flux:badge>
                @endif
                @if($overLimit)
                    <flux:badge size="sm" color="red" icon="exclamation-triangle">指定数量超過/到達</flux:badge>
                @endif
            </div>
        </div>
        <div class="flex gap-2">
            <a class="px-3 py-1.5 rounded bg-neutral-200 dark:bg-neutral-800" href="{{ route('procurement.materials.index') }}">{{ __('procflow::materials.show.back_to_list') }}</a>
            <a class="px-3 py-1.5 rounded bg-blue-600 text-white" href="{{ route('procurement.materials.issue', ['material' => $this->material->id]) }}">{{ __('procflow::materials.show.issue') }}</a>
        </div>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <div class="space-y-6">
            <div class="rounded border p-4 bg-white dark:bg-neutral-900 shadow-sm">
                <h2 class="text-lg font-medium mb-3">{{ __('procflow::materials.sections.basic') }}</h2>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <div class="text-neutral-500">{{ __('procflow::materials.form.manufacturer_name') }}</div>
                        <div class="font-medium">{{ $this->material->manufacturer_name ?: '-' }}</div>
                    </div>
                </div>

                @if($this->material->is_chemical)
                    <div class="mt-6 border-t pt-4 space-y-4">
                        <h3 class="font-medium text-blue-700 dark:text-blue-400 flex items-center gap-2">
                            <flux:icon name="beaker" class="size-4" />
                            {{ __('procflow::materials.sections.chemical_details') }}
                        </h3>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <div class="text-neutral-500">{{ __('procflow::materials.form.cas_no') }}</div>
                                <div class="font-medium">{{ $this->material->cas_no ?: '-' }}</div>
                            </div>
                            <div>
                                <div class="text-neutral-500">{{ __('procflow::materials.form.physical_state') }}</div>
                                <div class="font-medium">{{ $this->material->physical_state ?: '-' }}</div>
                            </div>
                            <div class="col-span-2">
                                <div class="text-neutral-500">{{ __('procflow::materials.form.ghs_hazard_details') }}</div>
                                <div class="font-medium whitespace-pre-wrap">{{ $this->material->ghs_hazard_details ?: '-' }}</div>
                            </div>

                            <div class="col-span-2 border-t pt-2 mt-2">
                                <div class="text-neutral-500 text-xs uppercase tracking-wider mb-2">{{ __('procflow::materials.form.ghs_mark') }}</div>
                                @php($icons = method_exists($this->material, 'ghsIconNames') ? $this->material->ghsIconNames() : [])
                                @if(!empty($icons))
                                    <div class="flex flex-wrap items-center gap-2">
                                        @foreach($icons as $icon)
                                            <div class="flex items-center gap-1 bg-neutral-100 dark:bg-neutral-800 px-2 py-1 rounded border border-neutral-200 dark:border-neutral-700">
                                                <flux:icon :name="$icon" class="size-6" />
                                                <span class="text-[10px] font-medium">{{ __('procflow::materials.form.ghs_labels.'.strtoupper(str_replace('-', '', $icon))) }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="text-neutral-400 text-xs italic">{{ __('procflow::materials.table.na') }}</div>
                                @endif
                            </div>

                            <div>
                                <div class="text-neutral-500">{{ __('procflow::materials.form.applicable_regulation') }}</div>
                                <div class="font-medium whitespace-pre-wrap">{{ $this->material->applicable_regulation ?: '-' }}</div>
                            </div>
                            <div>
                                <div class="text-neutral-500">{{ __('procflow::materials.form.protective_equipment') }}</div>
                                <div class="font-medium whitespace-pre-wrap">{{ $this->material->protective_equipment ?: '-' }}</div>
                            </div>

                            <div>
                                <div class="text-neutral-500">{{ __('procflow::materials.form.specified_quantity') }}</div>
                                <div class="font-medium">{{ $this->material->specified_quantity ? (float)$this->material->specified_quantity : '-' }}</div>
                            </div>
                            <div>
                                <div class="text-neutral-500">{{ __('procflow::materials.form.emergency_contact') }}</div>
                                <div class="font-medium">{{ $this->material->emergency_contact ?: '-' }}</div>
                            </div>
                            <div class="col-span-2">
                                <div class="text-neutral-500">{{ __('procflow::materials.form.disposal_method') }}</div>
                                <div class="font-medium whitespace-pre-wrap">{{ $this->material->disposal_method ?: '-' }}</div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="rounded border p-4 bg-white dark:bg-neutral-900 shadow-sm">
                <h2 class="text-lg font-medium mb-3">{{ __('procflow::materials.show.lots_title') }}</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-neutral-500">
                                <th class="py-2 px-3">{{ __('procflow::materials.show.lots.lot_no') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::materials.show.lots.stock') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::materials.show.lots.storage_location') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::materials.show.lots.expiry') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::materials.show.lots.status') }}</th>
                                <th class="py-2 px-3 align-right">{{ __('procflow::materials.show.lots.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($this->lots as $lot)
                            <tr class="border-t {{ $lot->isExpired() ? 'bg-red-50/40 dark:bg-red-950/20' : '' }}">
                                <td class="py-2 px-3">{{ $lot->lot_no }}</td>
                                <td class="py-2 px-3">{{ \Lastdino\ProcurementFlow\Support\Format::qty($lot->qty_on_hand) }} {{ $lot->unit }}</td>
                                <td class="py-2 px-3">{{ $lot->storageLocation?->name ?: '-' }}</td>
                                <td class="py-2 px-3">{{ $lot->expiry_date ?? '-' }}</td>
                                <td class="py-2 px-3">{{ $lot->status ?? '-' }}</td>
                                <td class="py-2 px-3">
                                    <div class="flex gap-2 justify-end">
                                        <flux:button size="xs" variant="outline" wire:click="openTransferModal({{ $lot->id }})">
                                            移動
                                        </flux:button>
                                        <a class="inline-block text-blue-600 hover:underline text-xs"
                                           href="{{ route('procurement.materials.issue', ['material' => $this->material->id, 'lot' => $lot->id]) }}">
                                            {{ __('procflow::materials.show.issue') }}
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-6 text-center text-neutral-500">{{ __('procflow::materials.show.lots.empty') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            @if($this->material->is_chemical)
                <div class="rounded border p-4 bg-white dark:bg-neutral-900 shadow-sm">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-lg font-medium">{{ __('procflow::materials.ra.title') }}</h2>
                        <flux:button size="xs" variant="outline" wire:click="openRaModal">{{ __('procflow::materials.ra.new') }}</flux:button>
                    </div>
                    <div class="space-y-3">
                        @forelse($this->material->riskAssessments as $ra)
                            <div class="p-3 rounded-lg border bg-neutral-50 dark:bg-neutral-800 text-sm">
                                <div class="flex justify-between items-start mb-2">
                                    <span class="font-semibold text-blue-600">{{ $ra->assessment_date->format('Y-m-d') }}</span>
                                    <flux:badge size="sm" color="orange">{{ $ra->risk_level }}</flux:badge>
                                </div>
                                <div class="grid grid-cols-1 gap-1">
                                    <div><span class="text-neutral-500 text-xs">{{ __('procflow::materials.ra.assessment_results') }}:</span> {{ $ra->assessment_results }}</div>
                                    <div><span class="text-neutral-500 text-xs">{{ __('procflow::materials.ra.countermeasures') }}:</span> {{ $ra->countermeasures }}</div>
                                    <div class="mt-2 flex justify-between text-xs text-neutral-400">
                                        <span>{{ __('procflow::materials.ra.assessor_name') }}: {{ $ra->assessor_name }}</span>
                                        @if($ra->next_assessment_date)
                                            <span>{{ __('procflow::materials.ra.next_assessment_date') }}: {{ $ra->next_assessment_date->format('Y-m-d') }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-4 text-neutral-500 text-sm">{{ __('procflow::materials.ra.empty') }}</div>
                        @endforelse
                    </div>
                </div>

                <div class="rounded border p-4 bg-white dark:bg-neutral-900 shadow-sm">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-lg font-medium">{{ __('procflow::materials.inspections.title') }}</h2>
                        <flux:button size="xs" variant="outline" wire:click="openInspectionModal">{{ __('procflow::materials.inspections.new') }}</flux:button>
                    </div>
                    <div class="space-y-3">
                        @forelse($this->material->inspections as $ins)
                            <div class="p-3 rounded-lg border bg-neutral-50 dark:bg-neutral-800 text-sm">
                                <div class="flex justify-between items-start mb-2">
                                    <span class="font-semibold text-emerald-600">{{ $ins->inspection_date->format('Y-m-d') }}</span>
                                    <div class="flex gap-1">
                                        <flux:badge size="sm" :color="$ins->container_status ? 'emerald' : 'red'">
                                            {{ $ins->container_status ? __('procflow::materials.inspections.container_ok') : __('procflow::materials.inspections.container_ng') }}
                                        </flux:badge>
                                        <flux:badge size="sm" :color="$ins->label_status ? 'emerald' : 'red'">
                                            {{ $ins->label_status ? __('procflow::materials.inspections.label_ok') : __('procflow::materials.inspections.label_ng') }}
                                        </flux:badge>
                                    </div>
                                </div>
                                @if($ins->details)
                                    <div class="text-neutral-600 text-xs">{{ $ins->details }}</div>
                                @endif
                                <div class="mt-2 text-xs text-neutral-400">{{ __('procflow::materials.inspections.inspector_name') }}: {{ $ins->inspector_name }}</div>
                            </div>
                        @empty
                            <div class="text-center py-4 text-neutral-500 text-sm">{{ __('procflow::materials.inspections.empty') }}</div>
                        @endforelse
                    </div>
                </div>
            @endif

            <div class="rounded border p-4 bg-white dark:bg-neutral-900">
                <h2 class="text-lg font-medium mb-3">{{ __('procflow::materials.show.movements_title') }}</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-neutral-500">
                                <th class="py-2 px-3">{{ __('procflow::materials.show.movements.occurred_at') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::materials.show.movements.type') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::materials.show.movements.qty') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::materials.show.movements.lot') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::materials.show.movements.reason') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($this->movements as $mv)
                            <tr class="border-t">
                                <td class="py-2 px-3">{{ $mv->occurred_at }}</td>
                                <td class="py-2 px-3">{{ $mv->type }}</td>
                                <td class="py-2 px-3">{{ \Lastdino\ProcurementFlow\Support\Format::qty($mv->qty_base) }} {{ $mv->unit }}</td>
                                <td class="py-2 px-3">{{ optional($mv->lot)->lot_no ?? '-' }}</td>
                                <td class="py-2 px-3">{{ $mv->reason ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-neutral-500">{{ __('procflow::materials.show.movements.empty') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <flux:modal wire:model.self="showRaModal" name="ra-form">
        <div class="w-full md:w-[36rem] max-w-full space-y-4">
            <h3 class="text-lg font-semibold">{{ __('procflow::materials.ra.new') }}</h3>
            <div class="space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <flux:input type="date" wire:model="raForm.assessment_date" label="{{ __('procflow::materials.ra.assessment_date') }}"/>
                    <flux:input wire:model="raForm.risk_level" label="{{ __('procflow::materials.ra.risk_level') }}"/>
                </div>
                <flux:textarea rows="2" wire:model="raForm.assessment_results" label="{{ __('procflow::materials.ra.assessment_results') }}"/>
                <flux:textarea rows="2" wire:model="raForm.countermeasures" label="{{ __('procflow::materials.ra.countermeasures') }}"/>
                <div class="grid grid-cols-2 gap-3">
                    <flux:input type="date" wire:model="raForm.next_assessment_date" label="{{ __('procflow::materials.ra.next_assessment_date') }}"/>
                    <flux:input wire:model="raForm.assessor_name" label="{{ __('procflow::materials.ra.assessor_name') }}"/>
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" x-on:click="$flux.modal('ra-form').close()">{{ __('procflow::materials.buttons.cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="saveRa">{{ __('procflow::materials.buttons.save') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model.self="showInspectionModal" name="inspection-form">
        <div class="w-full md:w-[36rem] max-w-full space-y-4">
            <h3 class="text-lg font-semibold">{{ __('procflow::materials.inspections.new') }}</h3>
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <flux:input type="date" wire:model="inspectionForm.inspection_date" label="{{ __('procflow::materials.inspections.inspection_date') }}"/>
                    <flux:input wire:model="inspectionForm.inspector_name" label="{{ __('procflow::materials.inspections.inspector_name') }}"/>
                </div>
                <div class="flex gap-6">
                    <flux:field>
                        <flux:label>{{ __('procflow::materials.inspections.container_status') }}</flux:label>
                        <flux:switch wire:model="inspectionForm.container_status"
                                     :label="$inspectionForm['container_status'] ? __('procflow::materials.inspections.container_ok') : __('procflow::materials.inspections.container_ng')"/>
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('procflow::materials.inspections.label_status') }}</flux:label>
                        <flux:switch wire:model="inspectionForm.label_status"
                                     :label="$inspectionForm['label_status'] ? __('procflow::materials.inspections.label_ok') : __('procflow::materials.inspections.label_ng')"/>
                    </flux:field>
                </div>
                <flux:textarea rows="3" wire:model="inspectionForm.details" label="{{ __('procflow::materials.inspections.details') }}"/>
            </div>
            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" x-on:click="$flux.modal('inspection-form').close()">{{ __('procflow::materials.buttons.cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="saveInspection">{{ __('procflow::materials.buttons.save') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model.self="showTransferModal" name="transfer-form">
        <div class="w-full md:w-[30rem] max-w-full space-y-4">
            <h3 class="text-lg font-semibold">在庫移動</h3>
            <div class="space-y-4">
                <flux:field>
                    <flux:label>移動先保管場所</flux:label>
                    <flux:select wire:model="transferForm.to_storage_location_id" placeholder="移動先を選択...">
                        @foreach(\Lastdino\ProcurementFlow\Models\StorageLocation::query()->where('is_active', true)->orderBy('name')->get() as $loc)
                            <flux:select.option :value="$loc->id">{{ $loc->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="transferForm.to_storage_location_id" />
                </flux:field>

                <flux:input type="number" step="0.000001" min="0" wire:model="transferForm.qty" label="移動数量 ({{ $this->material->unit_stock }})" />
                <flux:textarea wire:model="transferForm.reason" label="移動理由 (任意)" rows="2" />
            </div>
            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" x-on:click="$flux.modal('transfer-form').close()">キャンセル</flux:button>
                <flux:button variant="primary" wire:click="transfer">移動を実行</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
