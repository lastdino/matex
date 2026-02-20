<?php

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Lastdino\Matex\Enums\PurchaseOrderStatus;
use Lastdino\Matex\Models\Material;
use Lastdino\Matex\Models\PurchaseOrder;
use Lastdino\Matex\Models\PurchaseOrderItem;
use Livewire\Component;

new class extends Component
{
    public function getOpenPoCountProperty(): int
    {
        $query = fn () => (int) PurchaseOrder::query()
            ->whereIn('status', [
                PurchaseOrderStatus::Draft,
                PurchaseOrderStatus::Issued,
                PurchaseOrderStatus::Receiving,
            ])
            ->count();

        if (config('app.env') === 'production') {
            return Cache::remember('procflow.dashboard.open_po_count', now()->addMinutes(3), $query);
        }

        return $query();
    }

    public function getThisMonthTotalProperty(): float
    {
        $query = fn () => (float) PurchaseOrder::query()
            ->whereNotNull('issue_date')
            ->whereBetween('issue_date', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('total');

        if (config('app.env') === 'production') {
            return Cache::remember('procflow.dashboard.this_month_total', now()->addMinutes(3), $query);
        }

        return $query();
    }

    public function getLowStocksProperty()
    {
        $query = fn () => Material::query()
            ->select(['id', 'sku', 'name', 'current_stock', 'min_stock'])
            ->withSum('lots', 'qty_on_hand')
            ->whereNotNull('min_stock')
            ->where(function ($q) {
                $lotsTable = (new \Lastdino\Matex\Models\MaterialLot)->getTable();
                $materialsTable = (new \Lastdino\Matex\Models\Material)->getTable();

                $sub = "(select COALESCE(sum({$lotsTable}.qty_on_hand), 0) from {$lotsTable} where {$lotsTable}.material_id = {$materialsTable}.id)";

                $q->whereRaw("{$sub} < COALESCE(min_stock, 0)");
            })
            ->orderByRaw('COALESCE(lots_sum_qty_on_hand, 0) asc')
            ->limit(10)
            ->get();

        if (config('app.env') === 'production') {
            return Cache::remember('procflow.dashboard.low_stocks', now()->addMinute(), $query);
        }

        return $query();
    }

    public function getOverduePoCountProperty(): int
    {
        $query = fn () => (int) PurchaseOrder::query()
            ->whereIn('status', [
                PurchaseOrderStatus::Issued,
                PurchaseOrderStatus::Receiving,
            ])
            ->whereNotNull('expected_date')
            ->where('expected_date', '<', now()->startOfDay())
            ->count();

        if (config('app.env') === 'production') {
            return Cache::remember('procflow.dashboard.overdue_po_count', now()->addMinutes(3), $query);
        }

        return $query();
    }

    public function getUpcomingPoCount7dProperty(): int
    {
        $start = now()->startOfDay();
        $end = now()->copy()->addDays(7)->endOfDay();

        $query = fn () => (int) PurchaseOrder::query()
            ->whereIn('status', [
                PurchaseOrderStatus::Issued,
                PurchaseOrderStatus::Receiving,
            ])
            ->whereBetween('expected_date', [$start, $end])
            ->count();

        if (config('app.env') === 'production') {
            return Cache::remember('procflow.dashboard.upcoming_po_count_7d', now()->addMinutes(3), $query);
        }

        return $query();
    }

    public function getLowStockCriticalCountProperty(): int
    {
        // Critical: stock <= 50% of min_stock
        $query = function () {
            $materials = Material::query()
                ->select(['id', 'current_stock', 'min_stock'])
                ->withSum('lots', 'qty_on_hand')
                ->whereNotNull('min_stock')
                ->get();

            $count = 0;
            foreach ($materials as $m) {
                $minQty = (float) ($m->min_stock ?? 0);
                $threshold = 0.5 * $minQty;
                $stock = (float) ($m->lots_sum_qty_on_hand ?? 0);
                if ($stock <= $threshold) {
                    $count++;
                }
            }

            return $count;
        };

        if (config('app.env') === 'production') {
            return (int) Cache::remember('procflow.dashboard.low_stocks_critical_count', now()->addMinutes(1), $query);
        }

        return $query();
    }

    public function getSupplierTop3Property(): Collection
    {
        $query = function () {
            $since = now()->copy()->subDays(30);

            $rows = PurchaseOrder::query()
                ->select(['supplier_id'])
                ->selectRaw('SUM(COALESCE(total,0)) as spend_total')
                ->whereNotNull('issue_date')
                ->where('issue_date', '>=', $since)
                ->whereNotNull('supplier_id')
                ->with(['supplier:id,name,is_active'])
                ->groupBy('supplier_id')
                ->orderByDesc('spend_total')
                ->limit(3)
                ->get();

            return $rows->map(function ($po) {
                return [
                    'supplier_id' => (int) $po->supplier_id,
                    'name' => (string) ($po->supplier->name ?? '—'),
                    'total' => (float) $po->spend_total,
                ];
            });
        };

        if (config('app.env') === 'production') {
            return Cache::remember('procflow.dashboard.supplier_top3', now()->addMinutes(5), $query);
        }

        return $query();
    }

    public function getWeeklySpendSparklineProperty(): array
    {
        $query = function () {
            $end = now()->endOfWeek();
            $start = now()->copy()->subWeeks(11)->startOfWeek();

            $pos = PurchaseOrder::query()
                ->select(['id', 'issue_date', 'total'])
                ->whereNotNull('issue_date')
                ->whereBetween('issue_date', [$start, $end])
                ->get();

            // Bucket by week start date (Y-m-d)
            $buckets = [];
            for ($i = 11; $i >= 0; $i--) {
                $wkStart = now()->copy()->subWeeks($i)->startOfWeek()->format('Y-m-d');
                $buckets[$wkStart] = 0.0;
            }

            foreach ($pos as $po) {
                /** @var \Illuminate\Support\CarbonImmutable|\Illuminate\Support\Carbon $d */
                $d = $po->issue_date;
                $wkStart = $d->copy()->startOfWeek()->format('Y-m-d');
                if (array_key_exists($wkStart, $buckets)) {
                    $buckets[$wkStart] += (float) ($po->total ?? 0);
                }
            }

            return collect($buckets)->map(function ($total, $wkStart) {
                return ['week_start' => $wkStart, 'total' => (float) $total];
            })->values()->all();
        };

        if (config('app.env') === 'production') {
            return Cache::remember('procflow.dashboard.weekly_spend_12', now()->addMinutes(5), $query);
        }

        return $query();
    }

    public function getOtif30dProperty(): array
    {
        $query = function () {
            $since = now()->copy()->subDays(30);

            // Load PO Items within timeframe (by PO issue_date) with related receivings
            $items = PurchaseOrderItem::query()
                ->select(['id', 'purchase_order_id', 'material_id', 'unit_purchase', 'qty_ordered', 'qty_canceled', 'expected_date'])
                ->with([
                    'purchaseOrder:id,issue_date,expected_date',
                    'receivingItems:id,purchase_order_item_id,receiving_id,qty_received',
                    'receivingItems.receiving:id,received_at',
                ])
                ->whereHas('purchaseOrder', function ($q) use ($since) {
                    $q->whereNotNull('issue_date')->where('issue_date', '>=', $since);
                })
                // Exclude only shipping fee rows (unit_purchase = 'shipping')
                ->where(function ($q) {
                    $q->whereNull('unit_purchase')
                        ->orWhere('unit_purchase', '!=', 'shipping');
                })
                ->get();

            $total = 0;
            $onTimeFull = 0;

            foreach ($items as $item) {
                $total++;

                // Exclude fully canceled lines from OTIF
                $ordered = max(((float) ($item->qty_ordered ?? 0)) - ((float) ($item->qty_canceled ?? 0)), 0.0);
                if ($ordered <= 0) {
                    // No effective order quantity -> skip from denominator
                    $total--;

                    continue;
                }
                $receivedTotal = 0.0;
                $lastReceivedAt = null;

                foreach ($item->receivingItems as $ri) {
                    $receivedTotal += (float) ($ri->qty_received ?? 0);
                    $rec = $ri->receiving?->received_at;
                    if ($rec !== null) {
                        $ts = $rec->timestamp;
                        if ($lastReceivedAt === null || $ts > $lastReceivedAt) {
                            $lastReceivedAt = $ts;
                        }
                    }
                }

                $expected = $item->expected_date ?: $item->purchaseOrder?->expected_date;

                $isFull = $receivedTotal >= $ordered && $ordered > 0;
                $isOnTime = false;
                if ($expected !== null) {
                    // Compare last received at end-of-day against expected end-of-day
                    $isOnTime = $lastReceivedAt !== null && $lastReceivedAt <= $expected->copy()->endOfDay()->timestamp;
                }

                if ($isFull && $isOnTime) {
                    $onTimeFull++;
                }
            }

            $percent = $total > 0 ? (100.0 * $onTimeFull / $total) : 100.0;

            return [
                'percent' => round($percent, 1),
                'on_time_full' => $onTimeFull,
                'total' => $total,
            ];
        };

        if (config('app.env') === 'production') {
            return Cache::remember('procflow.dashboard.otif_30d', now()->addMinutes(5), $query);
        }

        return $query();
    }
};

?>

<div class="p-6 space-y-6">
    <x-matex::topmenu />
    <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
        <flux:card class="flex flex-col justify-between">
            <div>
                <flux:heading size="sm" level="3" class="text-neutral-500">{{ __('matex::dashboard.cards.open_pos') }}</flux:heading>
                <div class="text-3xl font-semibold mt-2">{{ $this->openPoCount }}</div>
            </div>
        </flux:card>

        <flux:card class="flex flex-col justify-between">
            <div>
                <flux:heading size="sm" level="3" class="text-neutral-500">{{ __('matex::dashboard.cards.this_month_total') }}</flux:heading>
                <div class="text-3xl font-semibold mt-2">{{ \Lastdino\Matex\Support\Format::moneyTotal($this->thisMonthTotal) }}</div>
            </div>
        </flux:card>

        <flux:card class="flex flex-col justify-between">
            <div>
                <flux:heading size="sm" level="3" class="text-neutral-500">{{ __('matex::dashboard.cards.low_stock_materials') }}</flux:heading>
                <div class="text-3xl font-semibold mt-2">{{ $this->lowStocks->count() }}</div>
                <div class="mt-1">
                    <flux:badge color="red" size="sm" inset="top bottom">{{ __('matex::dashboard.cards.critical') }}: {{ $this->lowStockCriticalCount }}</flux:badge>
                </div>
            </div>
        </flux:card>

        <flux:card class="flex flex-col justify-between">
            <div>
                <flux:heading size="sm" level="3" class="text-neutral-500">{{ __('matex::dashboard.cards.overdue_pos') }}</flux:heading>
                <div class="text-3xl font-semibold mt-2 text-red-600">{{ $this->overduePoCount }}</div>
            </div>
        </flux:card>

        <flux:card class="flex flex-col justify-between">
            <div>
                <flux:heading size="sm" level="3" class="text-neutral-500">{{ __('matex::dashboard.cards.incoming_7d') }}</flux:heading>
                <div class="text-3xl font-semibold mt-2 text-emerald-600">{{ $this->upcomingPoCount7d }}</div>
            </div>
        </flux:card>

        <flux:card class="flex flex-col justify-between">
            <div>
                <flux:heading size="sm" level="3" class="text-neutral-500">{{ __('matex::dashboard.cards.otif_30d') }}</flux:heading>
                @php $otif = $this->otif30d; @endphp
                @php
                    $pct = (float) ($otif['percent'] ?? 0);
                    $color = $pct < 80 ? 'text-red-600' : ($pct < 95 ? 'text-amber-600' : 'text-emerald-600');
                @endphp
                <div class="text-3xl font-semibold mt-2 {{ $color }}">{{ \Lastdino\Matex\Support\Format::percent($pct) }}%</div>
                <div class="text-xs text-neutral-500 mt-1">{{ __('matex::dashboard.cards.otif.on_time_full', ['on' => $otif['on_time_full'] ?? 0, 'total' => $otif['total'] ?? 0]) }}</div>
            </div>
        </flux:card>
    </div>

    <flux:card>
        <flux:heading size="lg" level="2" class="mb-4">{{ __('matex::dashboard.low_stock.title') }}</flux:heading>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('matex::dashboard.low_stock.table.sku') }}</flux:table.column>
                <flux:table.column>{{ __('matex::dashboard.low_stock.table.name') }}</flux:table.column>
                <flux:table.column>{{ __('matex::dashboard.low_stock.table.stock') }}</flux:table.column>
                <flux:table.column>{{ __('matex::dashboard.low_stock.table.min_qty') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($this->lowStocks as $m)
                    @php
                        $stockValue = (float) ($m->lots_sum_qty_on_hand ?? 0);
                    @endphp
                    <flux:table.row>
                        <flux:table.cell class="whitespace-nowrap">{{ $m->sku }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $m->name }}
                        </flux:table.cell>
                        <flux:table.cell class="text-red-600">{{ $stockValue }}</flux:cell>
                        <flux:table.cell>{{ (float) $m->min_stock }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center text-neutral-500 py-4">{{ __('matex::dashboard.low_stock.empty') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <flux:card>
            <flux:heading size="lg" level="2" class="mb-4">{{ __('matex::dashboard.top_suppliers.title') }}</flux:heading>
            @php $tops = $this->supplierTop3; @endphp
            @if(($tops?->count() ?? 0) > 0)
                <div class="space-y-2">
                    @foreach ($tops as $row)
                        <div class="flex items-center justify-between py-2 border-b last:border-0 border-neutral-200 dark:border-neutral-800">
                            <div class="font-medium">{{ $row['name'] }}</div>
                            <div class="tabular-nums">{{ \Lastdino\Matex\Support\Format::moneyTotal($row['total']) }}</div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-sm text-neutral-500">{{ __('matex::dashboard.top_suppliers.empty') }}</div>
            @endif
        </flux:card>

        <flux:card>
            <flux:heading size="lg" level="2" class="mb-4">{{ __('matex::dashboard.spend_trend.title') }}</flux:heading>
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
            <div class="text-xs text-neutral-500 mt-2">{{ __('matex::dashboard.spend_trend.points', ['count' => $cnt]) }}</div>
        </flux:card>
    </div>
</div>
