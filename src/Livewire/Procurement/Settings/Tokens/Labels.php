<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Livewire\Procurement\Settings\Tokens;

use Illuminate\Contracts\View\View as ViewContract;
use Livewire\Component;
use Lastdino\ProcurementFlow\Models\{OrderingToken, Material};

class Labels extends Component
{
    public string $search = '';
    public ?int $materialId = null;
    public int $perPage = 48;
    public int $columns = 3; // for print layout

    /**
     * Label payload mode:
     * - token: encode only token
     * - url: encode app URL to scan page with token param (informational)
     */
    public string $payload = 'token';

    public function getRowsProperty()
    {
        $q = OrderingToken::query()->with('material')->where('enabled', true);
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
        return $q->orderBy('material_id')->limit($this->perPage)->get();
    }

    public function makeQrData(string $token): string
    {
        if ($this->payload === 'url') {
            // Use relative route to ordering scan with token as query param for convenience
            $url = route('procurement.ordering.scan', [], false) . '?token=' . urlencode($token);
            return $url;
        }
        return $token; // default: encode only token
    }

    public function render(): ViewContract
    {
        $materials = Material::query()->orderBy('name')->limit(200)->get(['id','name','sku']);
        return view('procflow::livewire.procurement.settings.tokens.labels', [
            'materials' => $materials,
            'rows' => $this->rows,
        ]);
    }
}
