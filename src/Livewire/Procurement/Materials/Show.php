<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Livewire\Procurement\Materials;

use Illuminate\Contracts\View\View as ViewContract;
use Livewire\Component;
use Lastdino\ProcurementFlow\Models\Material;
use Lastdino\ProcurementFlow\Models\MaterialLot;
use Lastdino\ProcurementFlow\Models\StockMovement;

class Show extends Component
{
    public int $materialId;

    public function mount(Material $material): void
    {
        $this->materialId = (int) $material->getKey();
    }

    public function getMaterialProperty(): Material
    {
        /** @var Material $m */
        $m = Material::query()->with(['lots' => function ($q) {
            $q->orderBy('expiry_date')->orderBy('lot_no');
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

    public function render(): ViewContract
    {
        return view('procflow::livewire.procurement.materials.show');
    }
}
