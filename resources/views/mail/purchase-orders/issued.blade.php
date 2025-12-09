@php
    /** @var \Lastdino\ProcurementFlow\Models\PurchaseOrder $po */
    $supplier = $po->supplier;
    $poNo = $po->po_number ?? ('Draft-' . $po->id);
@endphp

<p>{{ $supplier->name }} 御中</p>

<p>
平素より大変お世話になっております。<br>
以下の内容にて注文書（PDF）をお送りします。ご確認のうえ、ご対応をお願いいたします。
</p>

<p>
    注文番号：{{ $poNo }}<br>
    発行日：{{ optional($po->issue_date)->format('Y-m-d') }}<br>
    小計：¥{{ number_format((float) ($po->subtotal ?? 0), 0) }}<br>
    税額：¥{{ number_format((float) ($po->tax ?? 0), 0) }}<br>
    合計金額：¥{{ number_format((float) $po->total, 0) }}
</p>

<p>
本メールに注文書(PDF)を添付しております。必要に応じてご査収ください。<br>
ご不明点がございましたら、本メールにご返信ください。
</p>

<p>よろしくお願いいたします。</p>
