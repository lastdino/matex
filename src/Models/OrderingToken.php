<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lastdino\ProcurementFlow\Support\Tables;

class OrderingToken extends Model
{
    protected $fillable = [
        'token', 'material_id', 'unit_purchase', 'default_qty', 'enabled', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'default_qty' => 'decimal:6',
            'enabled' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function getTable()
    {
        return Tables::name('ordering_tokens');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
