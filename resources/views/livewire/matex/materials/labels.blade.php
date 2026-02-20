<?php

use Lastdino\Matex\Models\Material;
use Lastdino\Matex\Models\MaterialLot;
use Lastdino\Matex\Models\OrderingToken;
use Livewire\Component;
use tbQuar\Facades\Quar;

new class extends Component
{
    public string $search = '';

    public ?int $materialId = null;

    public ?int $lotId = null;

    public bool $showTokens = false; // false: 出庫用, true: 発注用

    public int $perPage = 48;

    public int $columns = 3; // for print layout

    public function getRowsProperty()
    {
        $q = MaterialLot::query()->with(['material', 'storageLocation']);

        // 特定のロットが指定されている場合は、在庫0でも表示する
        if (is_null($this->lotId)) {
            $q->where('qty_on_hand', '>', 0);
        }

        if ($this->search !== '') {
            $s = $this->search;
            $q->where(function ($qq) use ($s) {
                $qq->where('lot_no', 'like', "%{$s}%")
                    ->orWhereHas('material', function ($mq) use ($s) {
                        $mq->where('name', 'like', "%{$s}%")
                            ->orWhere('sku', 'like', "%{$s}%");
                    });
            });
        }

        if (! is_null($this->materialId)) {
            $q->where('material_id', $this->materialId);
        }

        if (! is_null($this->lotId)) {
            $q->where('id', $this->lotId);
        }

        return $q->orderBy('material_id')
            ->orderBy('expiry_date')
            ->orderBy('lot_no')
            ->limit($this->perPage)
            ->get();
    }

    public function getOrderingTokensProperty()
    {
        if (! $this->materialId) {
            return collect();
        }

        return OrderingToken::query()
            ->with('material')
            ->where('material_id', $this->materialId)
            ->where('enabled', true)
            ->get();
    }

    public function getMaterialsProperty()
    {
        return Material::query()->orderBy('name')->limit(200)->get(['id', 'name', 'sku']);
    }

    public function makeIssueQrData(MaterialLot $lot): string
    {
        return route('matex.issue.scan', [
            'material' => $lot->material_id,
            'lot' => $lot->id,
        ], true);
    }

    public function makeOrderingQrData(string $token): string
    {
        return route('matex.ordering.scan', ['token' => $token], true);
    }
};

?>

<div class="space-y-4">
    <div class="flex items-center justify-end gap-3 print:hidden">
        <flux:switch
            wire:model.live="showTokens"
            label="発注用ラベルを表示"
        />
    </div>

    @if($showTokens)
        <div class="space-y-4 print:mb-8">
            <h2 class="text-lg font-bold border-l-4 border-blue-500 pl-2 print:hidden">発注用QRコード</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-{{ max(1, min(6, $columns)) }} gap-4">
                @forelse($this->orderingTokens as $token)
                    <div class="p-4 border rounded break-inside-avoid bg-white dark:bg-neutral-900">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="text-xs text-blue-600 font-bold">【発注用】</div>
                                <div class="text-xs text-gray-500 truncate">{{ $token->material?->sku }}</div>
                                <div class="font-semibold text-sm line-clamp-2">{{ $token->material?->name }}</div>
                                <div class="text-[10px] mt-1 text-neutral-600">
                                    単位: {{ $token->unit_purchase ?? '設定なし' }} / 既定値: {{ (float)($token->default_qty ?? 0) }}
                                </div>
                                <div class="mt-2 text-[10px] font-mono text-neutral-500">{{ $token->token }}</div>
                            </div>
                            <div class="shrink-0">
                                {!! Quar::size(128)->margin(1)->generate($this->makeOrderingQrData($token->token)) !!}
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-neutral-500 text-sm">この資材に有効な発注トークンはありません。</div>
                @endforelse
            </div>
        </div>
    @else
        <div class="space-y-4">
            <h2 class="text-lg font-bold border-l-4 border-emerald-500 pl-2 mb-4 print:hidden">出庫用QRコード (在庫ロット)</h2>
            <div class="print:grid print:grid-cols-{{ max(1, min(6, $columns)) }} grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-{{ max(1, min(6, $columns)) }} gap-4">
                @foreach($this->rows as $row)
                    <div class="p-4 border rounded break-inside-avoid bg-white dark:bg-neutral-900">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="text-xs text-emerald-600 font-bold">【出庫用】</div>
                                <div class="text-xs text-gray-500 truncate">{{ $row->material?->sku }}</div>
                                <div class="font-semibold text-sm line-clamp-2">{{ $row->material?->name }}</div>
                                <div class="text-xs mt-1 text-neutral-600">LOT: {{ $row->lot_no }}</div>
                                @if($row->storageLocation)
                                    <div class="text-[10px] mt-0.5 text-neutral-500">場所: {{ $row->storageLocation->name }}</div>
                                @endif
                                <div class="text-[10px] mt-2 text-neutral-400 print:block hidden leading-tight">
                                    ※ QRを読み取ると該当ロットの出庫画面に移動します。
                                </div>
                            </div>
                            <div class="shrink-0">
                                {!! Quar::size(128)->margin(1)->generate($this->makeIssueQrData($row)) !!}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            @if($this->rows->isEmpty())
                <div class="text-center py-12 text-neutral-500 print:hidden">
                    該当する在庫ロットが見つかりません。
                </div>
            @endif
        </div>
    @endif
    <div class="print:hidden">
        <flux:button variant="primary" onclick="window.print()">{{ __('matex::settings.labels.buttons.print') }}</flux:button>
    </div>
</div>
