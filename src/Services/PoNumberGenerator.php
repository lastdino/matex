<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Lastdino\ProcurementFlow\Support\Tables;

class PoNumberGenerator
{
    public function generate(?CarbonImmutable $when = null): string
    {
        $when = $when ?: CarbonImmutable::now();
        $prefix = $when->format('Ym');
        // Use a simple monthly sequence based on current count
        $table = Tables::name('purchase_orders');
        $count = (int) DB::table($table)
            ->whereNotNull('po_number')
            ->where('po_number', 'like', $prefix.'-%')
            ->count();

        $seq = $count + 1;

        return sprintf('%s-%04d', $prefix, $seq);
    }
}
