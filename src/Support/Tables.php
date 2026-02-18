<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Support;

final class Tables
{
    public static function prefix(): string
    {
        $configured = (string) (config('procurement-flow.table_prefix') ?? '');

        return $configured !== '' ? $configured : 'procurement_flow_';
    }

    public static function name(string $base): string
    {
        return self::prefix().$base;
    }
}
