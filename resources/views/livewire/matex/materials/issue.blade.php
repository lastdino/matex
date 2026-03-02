<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Lastdino\Matex\Models\Material;
use Lastdino\Matex\Models\MaterialLot;
use Lastdino\Matex\Models\OrderingToken;
use Lastdino\Matex\Models\StockMovement;
use Lastdino\Matex\Services\UnitConversionService;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    #[Url(as: 'material')]
    public ?int $materialId = null;

    #[Url(as: 'lot', history: true)]
    public ?int $prefLotId = null;

    public bool $hasMaterial = false;

    public bool $filterByLot = false;

    public string $qrCode = '';

    /**
     * For lot-managed materials: array of [lot_id => qty_to_issue]
     * For non-lot materials: use $nonLotQty
     *
     * @var array<int, float|int|null>
     */
    public array $lotQty = [];

    /**
     * @var array<int, string|null>
     */
    public array $lotUnit = [];

    public ?float $nonLotQty = null;

    public ?int $departmentId = null;

    public string $reason = '';

    public string $message = '';

    public bool $ok = false;

    public function mount(?Material $material = null): void
    {
        if ($material && $material->exists) {
            $this->materialId = (int) $material->id;
        }

        if ($this->materialId) {
            $this->hasMaterial = true;
            if ($this->prefLotId) {
                $this->filterByLot = true;
            }

            $this->initializeLotData();
        }
    }

    public function getHasMaterialProperty(): bool
    {
        return $this->material !== null;
    }

    public function updatedQrCode($value): void
    {
        if (empty($value)) {
            return;
        }

        $raw = trim((string) $value);
        $parsedLotId = null;
        $parsedMaterialId = null;

        // If QR contains a URL like ...?lot=123 or ...?material=123, extract it
        try {
            if (preg_match('/[?&]lot=(\d+)/', $raw, $m)) {
                $parsedLotId = (int) ($m[1] ?? 0);
            }
            if (preg_match('/[?&]material=(\d+)/', $raw, $m)) {
                $parsedMaterialId = (int) ($m[1] ?? 0);
            }

            if (! $parsedLotId && ! $parsedMaterialId && filter_var($raw, FILTER_VALIDATE_URL)) {
                $q = parse_url($raw, PHP_URL_QUERY) ?: '';
                if (is_string($q) && $q !== '') {
                    parse_str($q, $qs);
                    if (! empty($qs['lot'])) {
                        $parsedLotId = (int) $qs['lot'];
                    }
                    if (! empty($qs['material'])) {
                        $parsedMaterialId = (int) $qs['material'];
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore parse error
        }

        $found = false;

        // 1. If material is already selected, try to find a lot for THIS material
        if ($this->materialId) {
            $lotQuery = MaterialLot::query()->where('material_id', $this->materialId);

            if (is_numeric($raw) || $parsedLotId) {
                $id = $parsedLotId ?: (int) $raw;
                $lot = $lotQuery->whereKey($id)->first();
            } else {
                $lot = $lotQuery->where(function ($q) use ($raw) {
                    $q->where('lot_no', $raw)->orWhere('barcode', $raw);
                })->first();
            }

            if ($lot) {
                $this->prefLotId = $lot->id;
                $this->filterByLot = true;
                $found = true;
            }
        }

        // 2. If material not selected OR lot not found for current material,
        // try to find by material ID first
        if (! $found && $parsedMaterialId) {
            $mat = Material::active()->find($parsedMaterialId);
            if ($mat) {
                $this->materialId = $mat->id;
                // If a lot was also in the URL, try to select it for the newly found material
                if ($parsedLotId) {
                    $lot = MaterialLot::where('material_id', $this->materialId)->whereKey($parsedLotId)->first();
                    if ($lot) {
                        $this->prefLotId = $lot->id;
                        $this->filterByLot = true;
                    }
                }
                $found = true;
            }
        }

        // 3. Try to find a lot globally and switch to its material
        if (! $found) {
            $globalLot = null;
            if (is_numeric($raw) || $parsedLotId) {
                $id = $parsedLotId ?: (int) $raw;
                $globalLot = MaterialLot::query()->find($id);
            } else {
                $globalLot = MaterialLot::query()->where('lot_no', $raw)->orWhere('barcode', $raw)->first();
            }

            if ($globalLot && $globalLot->material && $globalLot->material->is_active) {
                $this->materialId = $globalLot->material_id;
                $this->prefLotId = $globalLot->id;
                $this->filterByLot = true;
                $found = true;
            }
        }

        // 4. Try to find material by SKU/Barcode? (Optional, but good for UX)
        if (! $found) {
            $matBySku = Material::active()->where('sku', $raw)->orWhere('barcode', $raw)->first();
            if ($matBySku) {
                $this->materialId = $matBySku->id;
                $found = true;
            }
        }

        // 5. Try to find by Ordering Token
        if (! $found) {
            $ot = OrderingToken::query()->where('token', $raw)->with('material')->first();
            if ($ot && $ot->material && $ot->material->is_active) {
                $this->materialId = $ot->material_id;
                if ($ot->department_id) {
                    $this->departmentId = $ot->department_id;
                }
                $found = true;
            }
        }

        if ($found) {
            $this->hasMaterial = true;
            $this->initializeLotData();
            $this->qrCode = '';
            $this->dispatch('$refresh');

            return;
        }

        $this->dispatch('toast', type: 'error', message: '該当する資材またはロットが見つかりません');
    }

    public function showAllLots(): void
    {
        $this->prefLotId = null;
        $this->filterByLot = false;
        $this->initializeLotData();
    }

    public function resetMaterial(): void
    {
        $this->materialId = null;
        $this->hasMaterial = false;
        $this->prefLotId = null;
        $this->filterByLot = false;
        $this->qrCode = '';
        $this->lotQty = [];
        $this->lotUnit = [];
        $this->message = '';
        $this->ok = false;

        $this->dispatch('$refresh');
    }

    public function resetScan(): void
    {
        $this->resetMaterial();
        $this->dispatch('focus-token');
    }

    protected function initializeLotData(): void
    {
        $material = $this->material;
        foreach ($this->lots as $lot) {
            $this->lotQty[(int) $lot->id] = $this->lotQty[(int) $lot->id] ?? null;
            $this->lotUnit[(int) $lot->id] = $this->lotUnit[(int) $lot->id] ?? $material->unit_stock;
        }
    }

    protected function rules(): array
    {
        return [
            'departmentId' => ['required', 'exists:'.\Lastdino\Matex\Support\Tables::name('departments').',id'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function getMaterialProperty(): ?Material
    {
        if (! $this->materialId) {
            return null;
        }

        /** @var Material|null $m */
        $m = Material::query()->find($this->materialId);

        if (! $m) {
            $this->materialId = null;
        }

        return $m;
    }

    public function getLotsProperty()
    {
        $query = MaterialLot::query()
            ->where('material_id', $this->materialId)
            ->where('qty_on_hand', '>', 0);

        if ($this->filterByLot && $this->prefLotId) {
            $query->whereKey($this->prefLotId);
        }

        return $query->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expiry_date')
            ->orderBy('id')
            ->get();
    }

    public function issue(UnitConversionService $conversion): void
    {
        $material = $this->material;
        $this->message = '';
        $this->ok = false;

        // Validate input (especially reason)
        $this->validate();

        try {
            DB::transaction(function () use ($material, $conversion) {
                $hasAny = false;
                foreach ($this->lotQty as $lotId => $q) {
                    if ($q !== null && (float) $q > 0) {
                        $hasAny = true;
                        break;
                    }
                }
                abort_if(! $hasAny, 422, '数量を入力してください。');

                foreach ($this->lotQty as $lotId => $qty) {
                    $qty = $qty === null ? 0.0 : (float) $qty;
                    if ($qty <= 0) {
                        continue;
                    }
                    /** @var MaterialLot|null $lot */
                    $lot = MaterialLot::query()->where('material_id', $material->id)->whereKey((int) $lotId)->lockForUpdate()->first();
                    abort_if(! $lot, 422, '対象ロットが見つかりません。');

                    $fromUnit = $this->lotUnit[$lotId] ?? $material->unit_stock;
                    $factor = $conversion->factor($material, $fromUnit, $material->unit_stock);
                    $qtyBase = (float) $qty * (float) $factor;

                    abort_if($qtyBase > (float) $lot->qty_on_hand, 422, 'ロット在庫が不足しています。');

                    $lot->decrement('qty_on_hand', $qtyBase);
                    $user = Auth::user();
                    StockMovement::create([
                        'material_id' => $material->id,
                        'department_id' => $this->departmentId,
                        'lot_id' => $lot->id,
                        'type' => 'out',
                        'source_type' => static::class,
                        'source_id' => 0,
                        'qty_base' => $qtyBase,
                        'unit' => $fromUnit,
                        'occurred_at' => now()->toISOString(),
                        'reason' => $this->reason,
                        'causer_type' => $user ? get_class($user) : null,
                        'causer_id' => $user?->getAuthIdentifier(),
                    ]);

                    if (! is_null($material->current_stock)) {
                        $material->decrement('current_stock', $qtyBase);
                    }
                }
            });

            $this->ok = true;
            $this->message = '出庫が完了しました。';

            // Check if stock is below minimum
            $material->refresh();
            $stockValue = $material->lots()->sum('qty_on_hand');
            if (! is_null($material->min_stock) && (float) $stockValue < (float) $material->min_stock) {
                $this->dispatch('toast', type: 'warning', message: '在庫数が最小量を下回りました（現在: '.(float) $stockValue.' '.$material->unit_stock.'）');
            }

            // Refresh lots for UI
            $this->lotQty = [];
            $this->lotUnit = [];
            foreach ($this->lots as $lot) {
                $this->lotQty[(int) $lot->id] = null;
                $this->lotUnit[(int) $lot->id] = $material->unit_stock;
            }
            // keep reason as entered for consecutive issues
        } catch (\Throwable $e) {
            $this->ok = false;
            $this->message = '出庫に失敗しました: '.$e->getMessage();
        }
    }
};

?>

<x-matex::scan-page-layout
    :has-info="$this->hasMaterial"
    :title="__('matex::materials.issue.title_prefix') . ($this->materialId && $this->material ? ': ' . $this->material->sku . ' — ' . $this->material->name : '')"
>
    <x-slot name="backLink">
        <div class="flex gap-2">
            @if ($this->hasMaterial && $this->material)
                <flux:button variant="subtle" wire:click="resetMaterial">別の資材をスキャン</flux:button>
            @endif
            <flux:button href="{{ route('matex.materials.index', ['id' => $this->materialId]) }}" wire:navigate variant="subtle">資材一覧に戻る</flux:button>
        </div>
    </x-slot>

    <x-slot name="waitTitle">
        出庫する資材をスキャンしてください
    </x-slot>

    <x-slot name="waitScanner">
        <livewire:matex::qr-scanner wire:model.live="qrCode" />
    </x-slot>

    <x-slot name="waitDescription">
        <p>資材ラベル、ロットラベル、または発注トークンのQRコードを読み取ってください。</p>
        <p>SKU（品番）を直接入力することも可能です。</p>
    </x-slot>

    <x-slot name="waitInput">
        <div class="space-y-4">
            <flux:input
                id="token"
                x-ref="token"
                wire:model.live.debounce.500ms="qrCode"
                placeholder="SKU / Lot No / QR内容を入力..."
                icon="magnifying-glass"
            />
            <div class="flex justify-center">
                <a href="{{ route('matex.materials.index') }}" class="text-sm text-blue-600 hover:underline">資材一覧から選択する</a>
            </div>
        </div>
    </x-slot>

    <x-slot name="messages">
        @if ($message)
            <flux:callout :variant="$ok ? 'success' : 'danger'" class="{{ $this->hasMaterial ? 'shadow-sm' : 'mt-2 text-center' }}">
                {{ $message }}
            </flux:callout>
        @endif
    </x-slot>

    <x-slot name="infoTitle">
        資材情報
    </x-slot>

    <x-slot name="infoCard">
        <div class="space-y-4">
            <div class="space-y-1">
                <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">資材</label>
                <div class="text-lg font-bold text-gray-900 dark:text-white leading-tight">
                    {{ $this->material?->name }}
                </div>
                <div class="text-sm text-gray-500 font-mono">
                    {{ $this->material?->sku }}
                </div>
            </div>

            <div class="pt-4 border-t dark:border-neutral-700 space-y-3">
                <div class="flex justify-between items-center">
                    <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">単位</label>
                    <div class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                        {{ $this->material?->unit_stock }}
                    </div>
                </div>
            </div>
        </div>
    </x-slot>

    <x-slot name="actionForm">
        <div class="rounded-xl border bg-white shadow-sm dark:bg-neutral-800 dark:border-neutral-700">
            <div class="p-4 border-b flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <h2 class="text-lg font-medium">{{ __('matex::materials.issue.from_lot_title') }}</h2>
                    <div class="flex gap-2">
                        <livewire:matex::qr-scanner wire:model.live="qrCode" />
                    </div>
                    @if($filterByLot)
                        <flux:button size="xs" variant="subtle" wire:click="showAllLots" icon="x-mark">
                            全ロット表示に戻す
                        </flux:button>
                    @endif
                </div>
                <div class="text-sm text-neutral-600">{{ __('matex::materials.issue.unit_label') }}: {{ $this->material?->unit_stock }}</div>
            </div>
            <div class="p-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                    <tr class="text-left text-neutral-500">
                        <th class="py-2 px-3">{{ __('matex::materials.issue.table.lot_no') }}</th>
                        <th class="py-2 px-3">場所</th>
                        <th class="py-2 px-3">{{ __('matex::materials.issue.table.stock') }}</th>
                        <th class="py-2 px-3">{{ __('matex::materials.issue.table.expiry') }}</th>
                        <th class="py-2 px-3">{{ __('matex::materials.issue.table.qty') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($this->lots as $lot)
                        <tr class="border-t {{ $this->prefLotId === (int) $lot->id ? 'bg-blue-50/40 dark:bg-blue-950/20' : '' }}"
                            data-lot-id="{{ $lot->id }}">
                            <td class="py-2 px-3">
                                <div class="font-medium">{{ $lot->lot_no }}</div>
                            </td>
                            <td class="py-2 px-3 text-neutral-600">
                                {{ $lot->storageLocation?->name ?? '-' }}
                            </td>
                            <td class="py-2 px-3 text-neutral-600">{{ (float) $lot->qty_on_hand }} {{ $lot->unit }}</td>
                            <td class="py-2 px-3">{{ $lot->expiry_date ?? '-' }}</td>
                            <td class="py-2 px-3">
                                <div class="flex gap-2 items-center">
                                    <input type="number" min="0" step="0.000001"
                                           class="w-36 border rounded p-1 bg-white dark:bg-neutral-900"
                                           wire:model.lazy="lotQty.{{ $lot->id }}"
                                           @if($this->prefLotId === (int) $lot->id) x-data x-init="$el.focus()" @endif />

                                    @php
                                        $availableUnits = $this->material ? app(\Lastdino\Matex\Services\UnitConversionService::class)->getAvailableUnits($this->material) : [];
                                    @endphp
                                    @if(count($availableUnits) > 1)
                                        <div class="w-24">
                                            <flux:select wire:model="lotUnit.{{ $lot->id }}" size="sm">
                                                @foreach($availableUnits as $u)
                                                    <flux:select.option :value="$u">{{ $u }}</flux:select.option>
                                                @endforeach
                                            </flux:select>
                                        </div>
                                    @else
                                        <span class="text-xs text-neutral-500">{{ $this->material?->unit_stock }}</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-neutral-500">{{ __('matex::materials.issue.table.empty') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t space-y-3">
                <div class="max-w-xl">
                    <label class="block text-sm text-neutral-600 mb-1">部門</label>
                    <flux:select wire:model="departmentId" placeholder="部門を選択してください...">
                        @foreach(\Lastdino\Matex\Models\Department::active()->ordered()->get() as $dept)
                            <flux:select.option :value="$dept->id">{{ $dept->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @error('departmentId')
                        <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                    @enderror
                </div>
                <div class="max-w-xl">
                    <label class="block text-sm text-neutral-600 mb-1">{{ __('matex::materials.issue.reason_label') }}</label>
                    <flux:textarea wire:model.defer="reason" placeholder="{{ __('matex::materials.issue.reason_placeholder') }}" rows="2" />
                    @error('reason')
                    <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                    @enderror
                </div>
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" wire:click="issue" wire:loading.attr="disabled">
                        <span wire:loading.remove>{{ __('matex::materials.issue.submit') }}</span>
                        <span wire:loading>{{ __('matex::materials.issue.processing') }}</span>
                    </flux:button>
                </div>
            </div>
        </div>
    </x-slot>
</x-matex::scan-page-layout>
