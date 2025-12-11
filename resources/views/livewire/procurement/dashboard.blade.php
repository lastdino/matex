<div class="p-6 space-y-6">
    <x-procflow::topmenu />
    <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
        <div class="rounded-lg border p-4 bg-white dark:bg-neutral-900">
            <div class="text-sm text-neutral-500">{{ __('procflow::dashboard.cards.open_pos') }}</div>
            <div class="text-3xl font-semibold mt-2">{{ $this->openPoCount }}</div>
        </div>
        <div class="rounded-lg border p-4 bg-white dark:bg-neutral-900">
            <div class="text-sm text-neutral-500">{{ __('procflow::dashboard.cards.this_month_total') }}</div>
            <div class="text-3xl font-semibold mt-2">{{ \Lastdino\ProcurementFlow\Support\Format::moneyTotal($this->thisMonthTotal) }}</div>
        </div>
        <div class="rounded-lg border p-4 bg-white dark:bg-neutral-900">
            <div class="text-sm text-neutral-500">{{ __('procflow::dashboard.cards.low_stock_materials') }}</div>
            <div class="text-3xl font-semibold mt-2">{{ $this->lowStocks->count() }}</div>
            <div class="text-xs text-red-600 mt-1">{{ __('procflow::dashboard.cards.critical') }}: {{ $this->lowStockCriticalCount }}</div>
        </div>
        <div class="rounded-lg border p-4 bg-white dark:bg-neutral-900">
            <div class="text-sm text-neutral-500">{{ __('procflow::dashboard.cards.overdue_pos') }}</div>
            <div class="text-3xl font-semibold mt-2 text-red-600">{{ $this->overduePoCount }}</div>
        </div>
        <div class="rounded-lg border p-4 bg-white dark:bg-neutral-900">
            <div class="text-sm text-neutral-500">{{ __('procflow::dashboard.cards.incoming_7d') }}</div>
            <div class="text-3xl font-semibold mt-2 text-emerald-600">{{ $this->upcomingPoCount7d }}</div>
        </div>
        <div class="rounded-lg border p-4 bg-white dark:bg-neutral-900">
            <div class="text-sm text-neutral-500">{{ __('procflow::dashboard.cards.otif_30d') }}</div>
            @php $otif = $this->otif30d; @endphp
            @php
                $pct = (float) ($otif['percent'] ?? 0);
                $color = $pct < 80 ? 'text-red-600' : ($pct < 95 ? 'text-amber-600' : 'text-emerald-600');
            @endphp
            <div class="text-3xl font-semibold mt-2 {{ $color }}">{{ \Lastdino\ProcurementFlow\Support\Format::percent($pct) }}%</div>
            <div class="text-xs text-neutral-500 mt-1">{{ __('procflow::dashboard.cards.otif.on_time_full', ['on' => $otif['on_time_full'] ?? 0, 'total' => $otif['total'] ?? 0]) }}</div>
        </div>
    </div>

    <div class="rounded-lg border p-4 bg-white dark:bg-neutral-900">
        <h3 class="text-lg font-semibold mb-4">{{ __('procflow::dashboard.low_stock.title') }}</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-neutral-500">
                        <th class="py-2">{{ __('procflow::dashboard.low_stock.table.sku') }}</th>
                        <th class="py-2">{{ __('procflow::dashboard.low_stock.table.name') }}</th>
                        <th class="py-2">{{ __('procflow::dashboard.low_stock.table.stock') }}</th>
                        <th class="py-2">{{ __('procflow::dashboard.low_stock.table.safety') }}</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($this->lowStocks as $m)
                    @php
                        $stockValue = $m->manage_by_lot ? (float) ($m->lots_sum_qty_on_hand ?? 0) : (float) ($m->current_stock ?? 0);
                    @endphp
                    <tr class="border-t">
                        <td class="py-2">{{ $m->sku }}</td>
                        <td class="py-2">
                            {{ $m->name }}
                            @if($m->manage_by_lot)
                                <span class="ml-2 inline-flex items-center rounded bg-purple-100 text-purple-700 px-2 py-0.5 text-[11px]">{{ __('procflow::dashboard.low_stock.lot_badge') }}</span>
                            @endif
                        </td>
                        <td class="py-2 text-red-600">{{ $stockValue }}</td>
                        <td class="py-2">{{ (float) $m->safety_stock }}</td>
                    </tr>
                @empty
                    <tr><td class="py-4 text-neutral-500" colspan="4">{{ __('procflow::dashboard.low_stock.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="rounded-lg border p-4 bg-white dark:bg-neutral-900">
            <h3 class="text-lg font-semibold mb-3">{{ __('procflow::dashboard.top_suppliers.title') }}</h3>
            @php $tops = $this->supplierTop3; @endphp
            @if(($tops?->count() ?? 0) > 0)
                <ul class="text-sm divide-y divide-neutral-200 dark:divide-neutral-800">
                    @foreach ($tops as $row)
                        <li class="py-2 flex items-center justify-between">
                            <div class="font-medium">{{ $row['name'] }}</div>
                            <div class="tabular-nums">{{ \Lastdino\ProcurementFlow\Support\Format::moneyTotal($row['total']) }}</div>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="text-sm text-neutral-500">{{ __('procflow::dashboard.top_suppliers.empty') }}</div>
            @endif
        </div>

        <div class="rounded-lg border p-4 bg-white dark:bg-neutral-900">
            <h3 class="text-lg font-semibold mb-3">{{ __('procflow::dashboard.spend_trend.title') }}</h3>
            @php $points = $this->weeklySpendSparkline; @endphp
            @php
                // Build simple inline sparkline
                $w = 300; $h = 60;
                $cnt = count($points);
                $max = max(1, ...array_map(fn($p) => (float) ($p['total'] ?? 0), $points));
                $step = $cnt > 1 ? ($w / ($cnt - 1)) : 0;
                $path = '';
                foreach ($points as $i => $p) {
                    $x = $i * $step;
                    $val = (float) ($p['total'] ?? 0);
                    $y = $h - ($max > 0 ? ($val / $max) * $h : 0);
                    $cmd = $i === 0 ? 'M' : 'L';
                    $path .= sprintf('%s %.2f %.2f ', $cmd, $x, $y);
                }
            @endphp
            <div class="overflow-x-auto">
                <svg viewBox="0 0 {{ $w }} {{ $h }}" width="100%" height="80" preserveAspectRatio="none" class="text-emerald-600 dark:text-emerald-400">
                    <path d="{{ trim($path) }}" fill="none" stroke="currentColor" stroke-width="2" />
                </svg>
            </div>
            <div class="text-xs text-neutral-500 mt-2">{{ __('procflow::dashboard.spend_trend.points', ['count' => $cnt]) }}</div>
        </div>
    </div>
</div>
