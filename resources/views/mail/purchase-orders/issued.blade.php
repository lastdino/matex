@php
    /** @var \Lastdino\Matex\Models\PurchaseOrder $po */
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
    小計：{{ \Lastdino\Matex\Support\Format::moneySubtotal($po->subtotal ?? 0) }}<br>
    税額：{{ \Lastdino\Matex\Support\Format::moneyTax($po->tax ?? 0) }}<br>
    合計金額：{{ \Lastdino\Matex\Support\Format::moneyTotal($po->total) }}
</p>

<p>
本メールに注文書(PDF)を添付しております。必要に応じてご査収ください。<br>
ご不明点がございましたら、本メールにご返信ください。
</p>

<p>よろしくお願いいたします。</p>
