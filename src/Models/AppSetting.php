<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Lastdino\ProcurementFlow\Support\Tables;

class AppSetting extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = ['key', 'value'];

    public $timestamps = true;

    public function getTable()
    {
        return Tables::name('app_settings');
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        /** @var self|null $row */
        $row = static::query()->where('key', $key)->first();

        return $row?->value ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * Get JSON array setting.
     *
     * @return array<mixed>|null
     */
    public static function getArray(string $key, ?array $default = null): ?array
    {
        $raw = static::get($key);
        if ($raw === null || $raw === '') {
            return $default;
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : $default;
    }

    /**
     * Set JSON array setting.
     *
     * @param  array<mixed>|null  $value
     */
    public static function setArray(string $key, ?array $value): void
    {
        static::set($key, $value === null ? null : json_encode($value, JSON_UNESCAPED_UNICODE));
    }
}
