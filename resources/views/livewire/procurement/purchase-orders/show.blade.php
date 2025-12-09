<div class="p-6 space-y-4">
    <x-procflow::topmenu />
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">Purchase Order Detail</h1>
        <a href="{{ route('procurement.purchase-orders.index') }}" class="text-blue-600 hover:underline">Back to list</a>
    </div>

    <div class="grid gap-4">
        <div class="rounded border p-4">
            <div class="grid sm:grid-cols-2 gap-2">
                <div>
                    <div class="text-sm text-gray-500">PO Number</div>
                    <div class="font-medium">{{ $po->po_number ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Supplier</div>
                    <div class="font-medium">{{ $po->supplier->name ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Requester</div>
                    <div class="font-medium">{{ $po->requester->name ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Status</div>
                    <div class="font-medium">{{ $po->status?->value ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Issue Date</div>
                    <div class="font-medium">{{ optional($po->issue_date)->format('Y-m-d H:i') ?? '—' }}</div>
                </div>
            </div>
        </div>

        <div class="rounded border p-4">
            <h2 class="font-semibold mb-2">Items</h2>
            @if ($po->items->isEmpty())
                <div class="text-gray-500">No items.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-gray-600">
                            <tr>
                                <th class="py-2 pr-4">Name</th>
                                <th class="py-2 pr-4">Description</th>
                                <th class="py-2 pr-4">Qty</th>
                                <th class="py-2 pr-4">Unit</th>
                                <th class="py-2 pr-4">Unit Price</th>
                                <th class="py-2 pr-4">Tax Rate</th>
                                <th class="py-2 pr-4">QR</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($po->items as $item)
                                <tr class="border-t align-top">
                                    <td class="py-2 pr-4">{{ $item->material->name ?? '—' }}</td>
                                    <td class="py-2 pr-4">{{ $item->description ?? '—' }}</td>
                                    <td class="py-2 pr-4">{{ $item->qty_ordered ?? '—' }}</td>
                                    <td class="py-2 pr-4">{{ $item->unit_purchase ?? '—' }}</td>
                                    <td class="py-2 pr-4">{{ $item->price_unit ?? '—' }}</td>
                                    <td class="py-2 pr-4">{{ $item->tax_rate ?? '—' }}</td>
                                    <td class="py-2 pr-4">
                                        @if (!empty($item->scan_token))
                                            <div class="flex items-center gap-2">
                                                <div class="qr-svg w-[50px] h-[50px]">
                                                    {!! \tbQuar\Facades\Quar::size(50)->generate($item->scan_token) !!}
                                                </div>
                                                <div class="text-xs text-gray-500 select-all">{{ substr($item->scan_token, 0, 8) }}…</div>
                                            </div>
                                        @else
                                            <span class="text-gray-400">No token</span>
                                        @endif
                                    </td>

                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('procurement.purchase-orders.index') }}" class="px-3 py-1.5 border rounded">Back</a>
            <button type="button" onclick="window.print()" class="px-3 py-1.5 border rounded">Print Labels</button>
            <a
                href="{{ route('procurement.purchase-orders.pdf', ['po' => $po->id]) }}"
                class="px-3 py-1.5 border rounded"
                target="_blank"
                rel="noopener"
            >PDFダウンロード</a>
        </div>
    </div>
</div>

