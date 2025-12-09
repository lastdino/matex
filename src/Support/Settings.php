<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Support;

use Carbon\Carbon;
use Lastdino\ProcurementFlow\Models\AppSetting;

final class Settings
{
    /**
     * Get item tax configuration with schedule applied for a specific date.
     * Returns shape: ['default_rate' => float, 'rates' => array<string,float>]
     */
    public static function itemTax(?Carbon $at = null): array
    {
        $cfg = AppSetting::getArray('procurement.item_tax');
        if ($cfg === null) {
            $cfg = (array) config('procurement-flow.item_tax', []);
        }

        $default = (float) ($cfg['default_rate'] ?? 0.10);
        $rates = (array) ($cfg['rates'] ?? []);
        $schedule = (array) ($cfg['schedule'] ?? []);

        if ($at && ! empty($schedule)) {
            foreach ($schedule as $entry) {
                $from = $entry['effective_from'] ?? null;
                if ($from && $at->greaterThanOrEqualTo(Carbon::parse($from))) {
                    $default = (float) ($entry['default_rate'] ?? $default);
                    $rates = array_merge($rates, (array) ($entry['rates'] ?? []));
                }
            }
        }

        return ['default_rate' => $default, 'rates' => $rates];
    }

    /**
     * Persist item tax configuration as an array.
     * @param array{default_rate?:float,rates?:array<string,float>,schedule?:array<int,array<string,mixed>>} $value
     */
    public static function saveItemTax(array $value): void
    {
        AppSetting::setArray('procurement.item_tax', $value);
    }

    /**
     * Get shipping config: ['taxable' => bool, 'tax_rate' => float]
     *
     * @return array{taxable:bool,tax_rate:float}
     */
    public static function shipping(): array
    {
        $cfg = AppSetting::getArray('procurement.shipping');
        if ($cfg === null) {
            $cfg = (array) config('procurement-flow.shipping', []);
        }

        return [
            'taxable' => (bool) ($cfg['taxable'] ?? true),
            'tax_rate' => (float) ($cfg['tax_rate'] ?? 0.10),
        ];
    }

    /**
     * @param array{taxable?:bool,tax_rate?:float} $value
     */
    public static function saveShipping(array $value): void
    {
        AppSetting::setArray('procurement.shipping', $value);
    }

    /**
     * Get PDF configuration array.
     *
     * @return array<string,mixed>
     */
    public static function pdf(): array
    {
        $cfg = AppSetting::getArray('procurement.pdf');
        if ($cfg === null) {
            // Backward compatibility: support both keys in config
            $cfg = (array) (config('procurement-flow.pdf') ?? config('procurement_flow.pdf') ?? []);
        }
        return $cfg;
    }

    /**
     * @param array<string,mixed> $value
     */
    public static function savePdf(array $value): void
    {
        AppSetting::setArray('procurement.pdf', $value);
    }
}
