<div class="p-6 space-y-4">
    <x-procflow::topmenu />
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">{{ __('procflow::po.detail.title') }}</h1>
        <a href="{{ route('procurement.purchase-orders.index') }}" class="text-blue-600 hover:underline">{{ __('procflow::po.detail.back_to_list') }}</a>
    </div>

    <div class="grid gap-4">
        <div class="rounded border p-4">
            <div class="grid sm:grid-cols-2 gap-2">
                <div>
                    <div class="text-sm text-gray-500">{{ __('procflow::po.detail.fields.po_number') }}</div>
                    <div class="font-medium">{{ $po->po_number ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">{{ __('procflow::po.detail.fields.supplier') }}</div>
                    <div class="font-medium">{{ $po->supplier->name ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">{{ __('procflow::po.detail.fields.requester') }}</div>
                    <div class="font-medium">{{ $po->requester->name ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">{{ __('procflow::po.detail.fields.status') }}</div>
                    <div class="font-medium">
                        {{ $po->status ? __('procflow::po.status.' . $po->status->value) : '—' }}
                    </div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">{{ __('procflow::po.detail.fields.issue_date') }}</div>
                    <div class="font-medium">{{ optional($po->issue_date)->format('Y-m-d H:i') ?? '—' }}</div>
                </div>
            </div>
        </div>

        <div class="rounded border p-4">
            <h2 class="font-semibold mb-2">{{ __('procflow::po.detail.items') }}</h2>
            @if ($po->items->isEmpty())
                <div class="text-gray-500">{{ __('procflow::po.detail.no_items') }}.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-gray-600">
                            <tr>
                                <th class="py-2 pr-4">{{ __('procflow::po.detail.table.name') }}</th>
                                <th class="py-2 pr-4">{{ __('procflow::po.detail.table.description') }}</th>
                                <th class="py-2 pr-4">{{ __('procflow::po.detail.table.qty') }}</th>
                                <th class="py-2 pr-4">{{ __('procflow::po.detail.table.unit') }}</th>
                                <th class="py-2 pr-4">{{ __('procflow::po.detail.table.unit_price') }}</th>
                                <th class="py-2 pr-4">{{ __('procflow::po.detail.table.tax_rate') }}</th>
                                <th class="py-2 pr-4">{{ __('procflow::po.detail.table.qr') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($po->items as $item)
                                <tr class="border-t align-top">
                                    <td class="py-2 pr-4">{{ $item->material->name ?? '—' }}</td>
                                    <td class="py-2 pr-4">{{ $item->description ?? '—' }}</td>
                                    <td class="py-2 pr-4">{{ \Lastdino\ProcurementFlow\Support\Format::qty($item->qty_ordered ?? 0) }}</td>
                                    <td class="py-2 pr-4">{{ $item->unit_purchase ?? '—' }}</td>
                                    <td class="py-2 pr-4">{{ \Lastdino\ProcurementFlow\Support\Format::moneyUnitPrice($item->price_unit ?? 0) }}</td>
                                    <td class="py-2 pr-4">{{ is_null($item->tax_rate) ? '—' : (\Lastdino\ProcurementFlow\Support\Format::percent($item->tax_rate) . '%') }}</td>
                                    <td class="py-2 pr-4">
                                        @if ($item->unit_purchase === 'shipping')
                                            <span class="text-gray-400">—</span>
                                        @elseif (!empty($item->scan_token))
                                            <div class="flex items-center gap-2">
                                                <div class="qr-svg w-[50px] h-[50px]">
                                                    {!! \tbQuar\Facades\Quar::size(50)->generate($item->scan_token) !!}
                                                </div>
                                                <div class="text-xs text-gray-500 select-all">{{ substr($item->scan_token, 0, 8) }}…</div>
                                            </div>
                                        @else
                                            <span class="text-gray-400">{{ __('procflow::po.detail.no_token') }}</span>
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
            <a href="{{ route('procurement.purchase-orders.index') }}" class="px-3 py-1.5 border rounded">{{ __('procflow::po.detail.buttons.back') }}</a>
            <button type="button" onclick="window.print()" class="px-3 py-1.5 border rounded">{{ __('procflow::po.detail.buttons.print_labels') }}</button>
            <a
                href="{{ route('procurement.purchase-orders.pdf', ['po' => $po->id]) }}"
                class="px-3 py-1.5 border rounded"
                target="_blank"
                rel="noopener"
            >{{ __('procflow::po.detail.buttons.download_pdf') }}</a>
        </div>
    </div>
</div>

