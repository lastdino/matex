@php
    /** @var \Lastdino\Matex\Models\PurchaseOrder $po */
    $supplier = $po->supplier;
    $contact = $po->contact;
    $poNo = $po->po_number ?? ('Draft-' . $po->id);
    $cfg = \Lastdino\Matex\Support\Settings::pdf();
    $company = $cfg['company'] ?? [];
    $footnotes = $cfg['footnotes'] ?? [];
    $paymentTerms = $cfg['payment_terms'] ?? '';
    // 発注単位の納品先が設定されていれば優先。無ければPDF設定の既定値を使用。
    $deliveryLocation = ($po->delivery_location ?? '') !== ''
        ? $po->delivery_location
        : ($cfg['delivery_location'] ?? '');
    $requesterName = optional($po->requester)->name;
    // ApprovalFlow (BC/GL) names - both from latest two approvals
    $Name = [];

    try {
        /** @var \Lastdino\ApprovalFlow\Models\ApprovalFlowTask|null $afTask */
        $afTask = $po->ApprovalFlowTask ?? null;
        if ($afTask) {
            // Get the latest two approved histories (oldest among the two -> BC, latest -> GL)
            $histories = $afTask->histories; // lazy loads if not preloaded
            if ($histories && count($histories) > 0) {
                $approvedList = $histories->filter(function ($h) {
                    $label = (string) ($h->name ?? '');
                    return in_array($label, ['承認', 'Approved', 'approved'], true);
                })->values();
                if ($approvedList->count() > 0) {
                    // Ensure chronological order, then take last two
                    $ordered = $approvedList->take(-2);


                    $count = $ordered->count();
                    foreach ($ordered as $o){
                        $Name[]=optional($o->user)->name;
                    }
                }
            }
        }
    } catch (Throwable $e) {
        // ignore approval flow lookup errors in view context
    }
@endphp
    <!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <title>注文書 {{ $poNo }}</title>
</head>
<body>
<article class="sheet flex flex-col">
    {{--注文No--}}
    <div class="flex justify-end">{{ $poNo }}</div>
    {{--タイトル--}}
    <div class="self-center m-6">
        <div class="text-3xl border-b-2 border-black font-bold">注　　文　　書</div>
    </div>
    {{--宛先、住所など--}}
    <div class="flex flex-row justify-between m-6">
        {{-- 左側 --}}
        <div class="flex flex-col">
            <div class="mb-16">発注日　{{ optional($po->issue_date)->format('Y年m月d日') }}</div>
            <div class="flex flex-row mb-12">
                <div>宛先　</div>
                <div class="border-b-2 border-black w-full font-bold">
                    {{ $supplier->name }}
                    @if($contact)
                        <br>
                        @if($contact->department) {{ $contact->department }}　@endif
                        {{ $contact->name }}
                    @elseif(!empty($supplier->contact_person_name))
                        　{{ $supplier->contact_person_name }}
                    @endif
                </div>
            </div>
            @if($contact && $contact->address)
                <div class="mb-4 text-sm">
                    所在地：{{ $contact->address }}
                </div>
            @endif
            <div class="whitespace-pre-line">毎度格別のお引き立てを賜り厚くお礼申し上げます。
                下記内容の通り御注文申し上げます。
            </div>
        </div>
        {{-- 右側 --}}
        <div>
            <div class="font-bold text-xl">@if(!empty($company['name']))<div>{{ $company['name'] }}</div>@endif</div>
            <div class="m-5">
                @foreach(($company['address_lines'] ?? []) as $line)
                    <div>{{ $line }}</div>
                @endforeach
                @if(!empty($company['tel']))<div>TEL：{{ $company['tel'] }}</div>@endif
                @if(!empty($company['fax']))<div>FAX：{{ $company['fax'] }}</div>@endif
                <table>
                    <tr>
                        <td class="border border-black text-center"></td>
                        <td class="border border-black text-center"></td>
                        <td class="border border-black text-center">担当</td>
                    </tr>
                    <tr>
                        <td class="border border-black w-14 h-14">
                            @if(!empty($Name[1]))
                                <div class="w-14 h-14 p-1">
                                    <div class="rounded-full border border-red-500 w-full h-full flex items-center justify-center text-red-500 font-bold">
                                        <div class="text-sm w-3.5 " x-data="aaa" x-ref="seal" :style="{ transform: `scaleY(${scale})` }">
                                            {{ mb_substr((string) $Name[1], 0, 2) }}
                                        </div>
                                    </div>
                                </div>

                            @endif
                        </td>
                        <td class="border border-black w-14 h-14">
                            @if(!empty($Name[0]))
                                <div class="w-14 h-14 p-1">
                                    <div class="rounded-full border border-red-500 w-full h-full flex items-center justify-center text-red-500 font-bold">
                                        <div class="text-sm w-3.5 " x-data="aaa" x-ref="seal" :style="{ transform: `scaleY(${scale})` }">
                                            {{ mb_substr((string) $Name[0], 0, 2) }}
                                        </div>
                                    </div>
                                </div>

                            @endif
                        </td>
                        <td class="border border-black w-14 h-14 text-center">
                            <div class="w-14 h-14 p-1">
                                <div class="rounded-full border border-red-500 w-full h-full flex items-center justify-center text-red-500 font-bold">
                                    <div class="text-sm w-3.5 " x-data="aaa" x-ref="seal" :style="{ transform: `scaleY(${scale})` }">
                                        {{ mb_substr((string) $requesterName, 0, 2) }}
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    <div class=" m-6">
        <table class="">
            <tbody>
            <tr>
                <td class="border border-black p-1">
                    支払条件
                </td>
                <td class="border border-black p-1">
                    {{ $paymentTerms }}
                </td>
            </tr>
            <tr>
                <td class="border border-black p-1">
                    受渡場所
                </td>
                <td class="border border-black p-1 whitespace-pre-line">{{ $deliveryLocation }}</td>
            </tr>
            </tbody>
        </table>
    </div>
    {{--注文内容--}}
    <div class="mr-6 ml-6 mb-6">
        <table class="w-full">
            <thead>
            <tr>
                <th class="border border-black text-center min-w-[1.5rem]"></th>
                <th class="border border-black text-center min-w-[14rem]">品番（品名）</th>
                <th class="border border-black text-center min-w-[2.5rem]">個数</th>
                <th class="border border-black text-center ">単価</th>
                <th class="border border-black text-center w-12">希望納期</th>
                <th class="border border-black text-center w-12">回答納期</th>
                <th class="border border-black text-center ">備考</th>
            </tr>
            </thead>
            <tbody>
            @php($hasCanceled = false)
            @foreach($po->items as $idx => $item)
                @php($mat = $item->relationLoaded('material') ? $item->material : $item->material)
                @php($name = $mat->name ?? ($item->description ?? ''))
                @php($qtyOrdered = (float) ($item->qty_ordered ?? 0))
                @php($qtyCanceled = (float) ($item->qty_canceled ?? 0))
                @php($effectiveQty = max($qtyOrdered - $qtyCanceled, 0))
                @php($isShipping = (string) ($item->unit_purchase ?? '') === 'shipping')
                @php($hasCanceled = $hasCanceled || ($qtyCanceled > 0))
                <tr >
                    <td class="border border-black text-center">{{ $idx + 1 }}</td>
                    <td class="border border-black p-1">
                        <div class="whitespace-pre-line">{{ $name }}</div>
                    </td>
                    <td class="border border-black text-center">
                        {{ \Lastdino\Matex\Support\Format::qty($effectiveQty) }}
                        @if(!$isShipping && $qtyCanceled > 0)
                            <span class="text-xs text-red-600">（キャンセル: {{ \Lastdino\Matex\Support\Format::qty($qtyCanceled) }}）</span>
                        @endif
                    </td>
                    <td class="border border-black text-center">{{ \Lastdino\Matex\Support\Format::unitPrice($item->price_unit) }}</td>
                    <td class="border border-black text-center">{{ optional($item->desired_date)->format('m/d') }}</td>
                    <td class="border border-black text-center"></td>
                    <td class="border border-black whitespace-pre-wrap">{{ $item->note ?? '' }}</td>
                </tr>
            @endforeach
            <tr>
                <td class="border border-black text-center " colspan="2">小計</td>
                <td class="border border-black text-center " colspan="5">{{ \Lastdino\Matex\Support\Format::moneySubtotal($po->subtotal) }}</td>
            </tr>
            <tr>
                <td class="border border-black text-center " colspan="2">消費税</td>
                <td class="border border-black text-center " colspan="5">{{ \Lastdino\Matex\Support\Format::moneyTax($po->tax) }}</td>
            </tr>
            <tr>
                <td class="border border-black text-center font-bold" colspan="2">合計金額</td>
                <td class="border border-black text-center font-bold" colspan="5">{{ \Lastdino\Matex\Support\Format::moneyTotal($po->total) }}</td>
            </tr>
            </tbody>
        </table>
    </div>
    {{--納期回答の依頼文章--}}
    <div class="mr-6 ml-6">
        @if(!empty($footnotes))
            <div class="mt-6 small">
                @foreach($footnotes as $note)
                    <div>＊ {{ $note }}</div>
                @endforeach
            </div>
        @endif
        @if($hasCanceled)
            <div class="mt-4 text-xs text-neutral-700">
                改訂: 行キャンセル反映済み（{{ now()->format('Y-m-d H:i') }}）
            </div>
        @endif
    </div>
</article>
</body>
</html>
