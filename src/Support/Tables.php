<?php

declare(strict_types=1);

namespace Lastdino\Matex\Support;

final class Tables
{
    public static function prefix(): string
    {
        $configured = (string) (config('matex.table_prefix') ?? '');

        return $configured !== '' ? $configured : 'matex_';
    }

    public static function name(string $key): string
    {
        return config("matex.{$key}_table") ?: self::prefix().$key;
    }
}
