<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Lastdino\Matex\Models\Material;
use Lastdino\Matex\Models\MaterialLot;
use Lastdino\Matex\Models\StockMovement;
use Lastdino\Matex\Services\UnitConversionService;
use Livewire\Component;

new class extends Component
{
    public int $materialId;

    public ?int $prefLotId = null;

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

    public string $reason = '';

    public string $message = '';

    public bool $ok = false;

    public function mount(Material $material): void
    {
        $this->materialId = (int) $material->getKey();
        // Initialize lots
        $lots = MaterialLot::query()
            ->where('material_id', $material->id)
            ->where('qty_on_hand', '>', 0)
            ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expiry_date')
            ->orderBy('id')
            ->get(['id']);
        foreach ($lots as $lot) {
            $this->lotQty[(int) $lot->id] = null;
            $this->lotUnit[(int) $lot->id] = $material->unit_stock;
        }

        // Optional: pre-select a lot from query string (?lot=ID)
        $lotId = (int) (request()->query('lot') ?? 0);
        if ($lotId > 0) {
            $exists = MaterialLot::query()
                ->where('material_id', $material->id)
                ->whereKey($lotId)
                ->exists();
            if ($exists) {
                $this->prefLotId = $lotId;
                // Optional preset quantity (?qty=)
                $qty = request()->query('qty');
                if (! is_null($qty)) {
                    $qtyNum = (float) $qty;
                    if ($qtyNum > 0) {
                        $this->lotQty[$lotId] = $qtyNum;
                    }
                }
            }
        }
    }

    protected function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function getMaterialProperty(): Material
    {
        /** @var Material $m */
        $m = Material::query()->findOrFail($this->materialId);

        return $m;
    }

    public function getLotsProperty()
    {
        return MaterialLot::query()
            ->where('material_id', $this->materialId)
            ->where('qty_on_hand', '>', 0)
            ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
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
            if (!is_null($material->min_stock) && (float) $stockValue < (float) $material->min_stock) {
                $this->dispatch('toast', type: 'warning', message: '在庫数が最小量を下回りました（現在: ' . (float) $stockValue . ' ' . $material->unit_stock . '）');
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

<div class="p-6 space-y-6">
    <x-matex::topmenu />

    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">{{ __('matex::materials.issue.title_prefix') }}: {{ $this->material->sku }} — {{ $this->material->name }}</h1>
        <a class="px-3 py-1.5 rounded bg-neutral-200 dark:bg-neutral-800" href="{{ route('matex.materials.show', ['material' => $this->material->id]) }}">{{ __('matex::materials.issue.back') }}</a>
    </div>

    @if ($message)
        @if ($ok)
            <flux:callout variant="success">{{ $message }}</flux:callout>
        @else
            <flux:callout variant="danger">{{ $message }}</flux:callout>
        @endif
    @endif

    <div class="rounded border bg-white dark:bg-neutral-900">
        <div class="p-4 border-b flex items-center justify-between">
            <h2 class="text-lg font-medium">{{ __('matex::materials.issue.from_lot_title') }}</h2>
            <div class="text-sm text-neutral-600">{{ __('matex::materials.issue.unit_label') }}: {{ $this->material->unit_stock }}</div>
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
                        data-lot-id="{{ $lot->id }}" @if($this->prefLotId === (int) $lot->id) data-selected="true" @endif>
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
                                    $availableUnits = app(\Lastdino\Matex\Services\UnitConversionService::class)->getAvailableUnits($this->material);
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
                                    <span class="text-xs text-neutral-500">{{ $this->material->unit_stock }}</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-6 text-center text-neutral-500">{{ __('matex::materials.issue.table.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t space-y-3">
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
</div>
