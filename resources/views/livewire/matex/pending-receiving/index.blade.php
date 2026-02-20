<?php

use Illuminate\Contracts\View\View;
use Lastdino\Matex\Enums\PurchaseOrderStatus;
use Lastdino\Matex\Models\PurchaseOrder;
use Livewire\Component;

new class extends Component
{
    public string $q = '';

    public int $perPage = 25;

    public function getOrdersProperty()
    {
        $q = (string) $this->q;

        return PurchaseOrder::query()
            ->with(['supplier', 'items', 'items.material', 'requester'])
            ->whereIn('status', [
                PurchaseOrderStatus::Issued,
                PurchaseOrderStatus::Receiving,
            ])
            ->when($q !== '', function ($query) use ($q) {
                // フリーワードを空白区切りで分割し、複数語のときは AND、各語は対象フィールド内で OR
                $keywords = preg_split('/\s+/u', trim((string) $q)) ?: [];

                if (count($keywords) > 1) {
                    foreach ($keywords as $word) {
                        $like = "%{$word}%";
                        $query->where(function ($and) use ($like) {
                            $and
                                // 資材マスタの品名/メーカー名
                                ->orWhereHas('items.material', function ($mq) use ($like) {
                                    $mq->where(function ($mm) use ($like) {
                                        $mm->where('name', 'like', $like)
                                            ->orWhere('manufacturer_name', 'like', $like);
                                    });
                                })
                                // 発注アイテムの説明/単発メーカー名
                                ->orWhereHas('items', function ($iq) use ($like) {
                                    $iq->where(function ($iqq) use ($like) {
                                        $iqq->where('description', 'like', $like)
                                            ->orWhere('manufacturer', 'like', $like);
                                    });
                                });
                        });
                    }
                } else {
                    $single = $keywords[0] ?? $q;
                    $query->where(function ($sub) use ($single) {
                        $sub->where('po_number', 'like', "%{$single}%")
                            ->orWhere('notes', 'like', "%{$single}%")
                            // サプライヤー名
                            ->orWhereHas('supplier', function ($sq) use ($single) {
                                $sq->where('name', 'like', "%{$single}%");
                            })
                            // 発注者（作成者）名
                            ->orWhereHas('requester', function ($rq) use ($single) {
                                $rq->where('name', 'like', "%{$single}%");
                            })
                            // 資材マスタの品名/メーカー名
                            ->orWhereHas('items.material', function ($mq) use ($single) {
                                $mq->where(function ($mm) use ($single) {
                                    $mm->where('name', 'like', "%{$single}%")
                                        ->orWhere('manufacturer_name', 'like', "%{$single}%");
                                });
                            })
                            // 発注アイテムのスキャン用トークン（先頭一致/全一致）
                            ->orWhereHas('items', function ($iq) use ($single) {
                                $iq->where(function ($iqq) use ($single) {
                                    $iqq->where('scan_token', $single)
                                        ->orWhere('scan_token', 'like', $single.'%');
                                });
                            })
                            // 単発（アドホック）品目の説明
                            ->orWhereHas('items', function ($iq) use ($single) {
                                $iq->where('description', 'like', "%{$single}%");
                            });
                    });
                }
            })
            ->latest('id')
            ->paginate($this->perPage);
    }
};

?>

<div class="p-6 space-y-6">
    <x-matex::topmenu />
    <h1 class="text-xl font-semibold">{{ __('matex::pending.title') }}</h1>

    <div class="flex items-end gap-3">
        <div class="grow max-w-96">
            <flux:input wire:model.live.debounce.300ms="q" placeholder="{{ __('matex::pending.search_placeholder') }}" />
        </div>
    </div>

    <div class="rounded-lg border overflow-x-auto bg-white dark:bg-neutral-900 mt-4">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left text-neutral-500">
                    <th class="py-2 px-3">{{ __('matex::pending.table.po_number') }}</th>
                    <th class="py-2 px-3">{{ __('matex::pending.table.supplier') }}</th>
                    <th class="py-2 px-3">{{ __('matex::pending.table.requester') }}</th>
                    <th class="py-2 px-3">{{ __('matex::pending.table.status') }}</th>
                    <th class="py-2 px-3">{{ __('matex::pending.table.items') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->orders as $po)
                    <tr class="border-t hover:bg-neutral-50 dark:hover:bg-neutral-800">
                        <td class="py-2 px-3">
                            <a href="{{ route('matex.purchase-orders.show', ['po' => $po->id]) }}" class="text-blue-600 hover:underline">
                                {{ $po->po_number ?? __('matex::po.labels.draft_with_id', ['id' => $po->id]) }}
                            </a>
                        </td>
                        <td class="py-2 px-3">{{ $po->supplier->name ?? '-' }}</td>
                        <td class="py-2 px-3">{{ $po->requester->name ?? '-' }}</td>
                        <td class="py-2 px-3">
                            @php $status = is_string($po->status) ? $po->status : ($po->status->value ?? 'draft'); @endphp
                            @php
                                $color = match ($status) {
                                    'closed' => 'green',
                                    'issued' => 'yellow',
                                    'receiving' => 'cyan',
                                    'canceled' => 'red',
                                    default  => 'zinc',
                                };
                            @endphp
                            <flux:badge color="{{ $color }}" size="sm">{{ __('matex::po.status.' . $status) }}</flux:badge>
                        </td>
                        <td class="py-2 px-3">{{ $po->items->count() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-6 text-center text-neutral-500">{{ __('matex::pending.table.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        <div>
            {{ $this->orders->links() }}
        </div>
    </div>
</div>
