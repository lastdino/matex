<?php

use Illuminate\Contracts\View\View as ViewContract;
use Lastdino\ProcurementFlow\Models\Material;
use Lastdino\ProcurementFlow\Models\OrderingToken;
use Livewire\Component;

new class extends Component
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
    public string $payload = 'url';

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
            // Use absolute route to ordering scan with token as query param
            $url = route('procurement.ordering.scan', ['token' => $token], true);

            return $url;
        }

        return $token; // default: encode only token
    }

    public function getMaterialsProperty()
    {
        return Material::query()->orderBy('name')->limit(200)->get(['id', 'name', 'sku']);
    }
};

?>

<div class="p-6 space-y-6">
    <x-procflow::topmenu />

    <div class="flex items-center justify-between print:hidden">
        <h1 class="text-xl font-semibold">{{ __('procflow::settings.labels.title') }}</h1>
        <a href="{{ route('procurement.settings.tokens') }}" class="text-blue-600 hover:underline">{{ __('procflow::settings.labels.to_tokens') }}</a>
    </div>

    <div class="grid gap-4 md:grid-cols-5 items-end print:hidden">
        <flux:field class="md:col-span-2">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('procflow::settings.labels.filters.search_placeholder') }}" />
        </flux:field>

        <flux:field>
            <flux:select wire:model.live="materialId">
                <option value="">{{ __('procflow::settings.labels.filters.all_materials') }}</option>
                @foreach($this->materials as $m)
                    <option value="{{ $m->id }}">{{ $m->name }} ({{ $m->sku }})</option>
                @endforeach
            </flux:select>
        </flux:field>

        <flux:field label="{{ __('procflow::settings.labels.filters.payload') }}">
            <flux:select wire:model.live="payload">
                <option value="token">{{ __('procflow::settings.labels.filters.payload_token_only') }}</option>
                <option value="url">{{ __('procflow::settings.labels.filters.payload_url') }}</option>
            </flux:select>
        </flux:field>

        <flux:field label="{{ __('procflow::settings.labels.filters.per_page') }}">
            <flux:input type="number" min="1" max="200" wire:model.live="perPage" />
        </flux:field>
    </div>

    <div class="print:grid print:grid-cols-{{ max(1, min(6, $columns)) }} grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-{{ max(1, min(6, $columns)) }} gap-4">
        @foreach($this->rows as $row)
            <div class="p-4 border rounded break-inside-avoid">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-sm text-gray-500">{{ $row->material?->sku }}</div>
                        <div class="font-semibold">{{ $row->material?->name }}</div>
                        <div class="text-xs mt-1">{{ __('procflow::settings.labels.card.unit') }}: {{ $row->unit_purchase ?? '-' }}</div>
                        @if($row->material)
                            <div class="text-xs text-gray-600">{{ __('procflow::settings.labels.card.moq_and_pack', ['moq' => $row->material->moq ?? '-', 'pack' => $row->material->pack_size ?? '-']) }}</div>
                        @endif
                        <div class="mt-2 text-xs font-mono">{{ $row->token }}</div>
                    </div>
                    <div>
                        {!! \tbQuar\Facades\Quar::size(128)->margin(1)->generate($this->makeQrData($row->token)) !!}
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="print:hidden">
        <flux:button variant="primary" onclick="window.print()">{{ __('procflow::settings.labels.buttons.print') }}</flux:button>
    </div>

    <style>
        @media print {
            .print\:hidden { display: none !important; }
            .print\:grid { display: grid !important; }
            .break-inside-avoid { break-inside: avoid; }
            body { margin: 8mm; }
        }
    </style>
</div>
