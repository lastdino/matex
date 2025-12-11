<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Livewire\Procurement\PendingReceiving;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Lastdino\ProcurementFlow\Enums\PurchaseOrderStatus;
use Lastdino\ProcurementFlow\Models\PurchaseOrder;

class Index extends Component
{
    public string $q = '';

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
            ->limit(50)
            ->get();
    }

    public function render(): View
    {
        return view('procflow::livewire.procurement.pending-receiving.index');
    }
}
