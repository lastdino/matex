<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Livewire\Procurement\Materials;

use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Lastdino\ProcurementFlow\Models\Material;
use Lastdino\ProcurementFlow\Models\MaterialLot;
use Lastdino\ProcurementFlow\Models\StockMovement;
use Lastdino\ProcurementFlow\Services\UnitConversionService;
use Livewire\Component;

class Issue extends Component
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

    public ?float $nonLotQty = null;

    public string $reason = '';

    public string $message = '';

    public bool $ok = false;

    public function mount(Material $material): void
    {
        $this->materialId = (int) $material->getKey();
        if ((bool) ($material->manage_by_lot ?? false)) {
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
                if ((bool) ($material->manage_by_lot ?? false)) {
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
                        abort_if($qty > (float) $lot->qty_on_hand, 422, 'ロット在庫が不足しています。');

                        // In UI we assume stock unit, but convert in case material units differ in future
                        $factor = $conversion->factor($material, $material->unit_stock, $material->unit_stock);
                        $qtyBase = (float) $qty * (float) $factor;

                        $lot->decrement('qty_on_hand', $qtyBase);
                        $user = Auth::user();
                        StockMovement::create([
                            'material_id' => $material->id,
                            'lot_id' => $lot->id,
                            'type' => 'out',
                            'source_type' => static::class,
                            'source_id' => 0,
                            'qty_base' => $qtyBase,
                            'unit' => $material->unit_stock,
                            'occurred_at' => now()->toISOString(),
                            'reason' => $this->reason,
                            'causer_type' => $user ? get_class($user) : null,
                            'causer_id' => $user?->getAuthIdentifier(),
                        ]);

                        if (! is_null($material->current_stock)) {
                            $material->decrement('current_stock', $qtyBase);
                        }
                    }
                } else {
                    $qty = (float) ($this->nonLotQty ?? 0);
                    abort_if($qty <= 0, 422, '数量を入力してください。');
                    if (! is_null($material->current_stock)) {
                        abort_if($qty > (float) $material->current_stock, 422, '在庫不足です。');
                    }
                    $factor = $conversion->factor($material, $material->unit_stock, $material->unit_stock);
                    $qtyBase = $qty * (float) $factor;

                    $user = Auth::user();
                    StockMovement::create([
                        'material_id' => $material->id,
                        'lot_id' => null,
                        'type' => 'out',
                        'source_type' => static::class,
                        'source_id' => 0,
                        'qty_base' => $qtyBase,
                        'unit' => $material->unit_stock,
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
            // Refresh lots for UI
            $this->lotQty = [];
            if ($this->material->manage_by_lot) {
                foreach ($this->lots as $lot) {
                    $this->lotQty[(int) $lot->id] = null;
                }
            } else {
                $this->nonLotQty = null;
            }
            // keep reason as entered for consecutive issues
        } catch (\Throwable $e) {
            $this->ok = false;
            $this->message = '出庫に失敗しました: '.$e->getMessage();
        }
    }

    public function render(): ViewContract
    {
        return view('procflow::livewire.procurement.materials.issue');
    }
}
