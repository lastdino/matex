@php
    $po = $receiving->purchaseOrder;
    $recipientName = \Lastdino\Matex\Support\Settings::notification()['accounting_name'] ?: '経理担当者様';
@endphp
<p>{{ $recipientName }}</p>

<p>以下の通り、入庫処理が完了しましたので通知いたします。</p>

<div style="margin-bottom: 20px; padding: 10px; border: 1px solid #ddd; background-color: #f9f9f9;">
    <strong>■ 入庫基本情報</strong><br>
    入庫日: {{ $receiving->received_at->format('Y-m-d H:i') }}<br>
    発注番号: {{ $po->po_number }}<br>
    部署: {{ $po->department?->name ?? '---' }}<br>
    仕入先: {{ $po->supplier?->name }}<br>
    リファレンス（納品書等）: {{ $receiving->reference_number ?: 'なし' }}<br>
    備考: {{ $receiving->notes ?: 'なし' }}
</div>

<strong>■ 入庫品目明細</strong>
<table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
    <thead>
        <tr style="background-color: #eee;">
            <th style="border: 1px solid #ccc; padding: 8px; text-align: left;">品名 / SKU / 区分</th>
            <th style="border: 1px solid #ccc; padding: 8px; text-align: right;">入庫数量</th>
            <th style="border: 1px solid #ccc; padding: 8px; text-align: left;">単位</th>
            <th style="border: 1px solid #ccc; padding: 8px; text-align: left;">ロット / 期限 / オプション</th>
        </tr>
    </thead>
    <tbody>
        @foreach($receiving->items as $item)
            @php
                $poi = $item->purchaseOrderItem;
                $material = $item->material;
                $itemName = $material ? $material->name : ($poi->description ?: '---');
                $itemSku = $material ? $material->sku : '---';
                $categoryName = $material?->category?->name;
            @endphp
            <tr>
                <td style="border: 1px solid #ccc; padding: 8px;">
                    <strong>{{ $itemName }}</strong><br>
                    <small style="color: #666;">SKU: {{ $itemSku }}</small>
                    @if($categoryName)
                        <br><small style="color: #666;">区分: {{ $categoryName }}</small>
                    @endif
                </td>
                <td style="border: 1px solid #ccc; padding: 8px; text-align: right;">
                    {{ number_format((float)$item->qty_received, 2) }}
                </td>
                <td style="border: 1px solid #ccc; padding: 8px;">
                    {{ $item->unit_purchase }}
                </td>
                <td style="border: 1px solid #ccc; padding: 8px;">
                    @if($item->lot_no)
                        ロット: {{ $item->lot_no }}<br>
                    @endif
                    @if($item->expiry_date)
                        期限: {{ $item->expiry_date->format('Y-m-d') }}<br>
                    @endif

                    {{-- オプション項目の表示 --}}
                    @if($poi && $poi->optionValues->isNotEmpty())
                        <div style="margin-top: 5px; padding-top: 5px; border-top: 1px dashed #eee;">
                            @foreach($poi->optionValues as $val)
                                <small style="display: block; color: #444;">
                                    {{ $val->group?->name }}: {{ $val->option?->name ?? $val->custom_value ?? '---' }}
                                </small>
                            @endforeach
                        </div>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

<p style="margin-top: 20px;">
    詳細はシステムにログインしてご確認ください。
</p>
