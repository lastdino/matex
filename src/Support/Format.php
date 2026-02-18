<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Support;

final class Format
{
    /**
     * Get configured decimals for a given key with sane defaults.
     */
    public static function decimals(string $key): int
    {
        // Prefer AppSetting via Settings helper, fallback to config inside helper
        return (int) Settings::displayDecimals($key);
    }

    public static function qty(null|int|float $value): string
    {
        $num = (float) ($value ?? 0);

        return number_format($num, self::decimals('qty'));
    }

    public static function unitPrice(null|int|float $value): string
    {
        $num = (float) ($value ?? 0);

        return number_format($num, self::decimals('unit_price'));
    }

    public static function unitPriceMaterials(null|int|float $value): string
    {
        $num = (float) ($value ?? 0);

        return number_format($num, self::decimals('unit_price_materials'));
    }

    public static function lineTotal(null|int|float $value): string
    {
        $num = (float) ($value ?? 0);

        return number_format($num, self::decimals('line_total'));
    }

    public static function subtotal(null|int|float $value): string
    {
        $num = (float) ($value ?? 0);

        return number_format($num, self::decimals('subtotal'));
    }

    public static function tax(null|int|float $value): string
    {
        $num = (float) ($value ?? 0);

        return number_format($num, self::decimals('tax'));
    }

    public static function total(null|int|float $value): string
    {
        $num = (float) ($value ?? 0);

        return number_format($num, self::decimals('total'));
    }

    public static function percent(null|int|float $value): string
    {
        $num = (float) ($value ?? 0);
        // If value looks like a fractional rate (e.g., 0.1 for 10%), scale to percentage.
        // Keep values already in percentage (e.g., 10, 95.5) as-is.
        if (abs($num) <= 1) {
            $num *= 100;
        }

        return number_format($num, self::decimals('percent'));
    }

    /**
     * 通貨記号の付与（config に従う）
     */
    public static function currencyWrap(string $formattedNumber): string
    {
        $cur = Settings::displayCurrency();
        $symbol = (string) ($cur['symbol'] ?? '¥');
        $position = (string) ($cur['position'] ?? 'prefix');
        $space = (bool) ($cur['space'] ?? false);

        $sp = $space ? ' ' : '';

        return $position === 'suffix'
            ? $formattedNumber.$sp.$symbol
            : $symbol.$sp.$formattedNumber;
    }

    // 金額系（通貨記号付き）
    public static function moneyUnitPrice(null|int|float $value): string
    {
        return self::currencyWrap(self::unitPrice($value));
    }

    public static function moneyUnitPriceMaterials(null|int|float $value): string
    {
        return self::currencyWrap(self::unitPriceMaterials($value));
    }

    public static function moneyLineTotal(null|int|float $value): string
    {
        return self::currencyWrap(self::lineTotal($value));
    }

    public static function moneySubtotal(null|int|float $value): string
    {
        return self::currencyWrap(self::subtotal($value));
    }

    public static function moneyTax(null|int|float $value): string
    {
        return self::currencyWrap(self::tax($value));
    }

    public static function moneyTotal(null|int|float $value): string
    {
        return self::currencyWrap(self::total($value));
    }
}
