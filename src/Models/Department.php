<?php

declare(strict_types=1);

namespace Lastdino\Matex\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Lastdino\Matex\Support\Tables;

class Department extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'api_token',
        'is_active',
        'sort_order',
    ];

    public function getTable()
    {
        return config('matex.departments_table') ?: Tables::name('departments');
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * APIトークンの取得（カラム存在チェック付き）
     */
    public function getApiTokenAttribute($value)
    {
        if (Schema::hasColumn($this->getTable(), 'api_token')) {
            return $value;
        }

        // カラムが存在しない場合は null を返す（共通設定が使われるようになります）
        return null;
    }

    /**
     * Scope: only active departments.
     * カラムが存在しない場合はフィルタリングをスキップします。
     */
    public function scopeActive($query)
    {
        if (Schema::hasColumn($this->getTable(), 'is_active')) {
            return $query->where('is_active', true);
        }

        return $query;
    }

    /**
     * Scope: ordered.
     * 利用可能なカラムに基づいて並び替えを行います。
     */
    public function scopeOrdered($query)
    {
        $table = $this->getTable();

        if (Schema::hasColumn($table, 'sort_order')) {
            $query->orderBy('sort_order');
        }

        if (Schema::hasColumn($table, 'name')) {
            $query->orderBy('name');
        } elseif (Schema::hasColumn($table, 'code')) {
            $query->orderBy('code');
        }

        return $query;
    }
}
