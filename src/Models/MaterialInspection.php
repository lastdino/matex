<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lastdino\ProcurementFlow\Support\Tables;

class MaterialInspection extends Model
{
    protected $fillable = [
        'material_id', 'inspection_date', 'inspector_name', 'container_status', 'label_status', 'details',
    ];

    public function getTable()
    {
        return Tables::name('material_inspections');
    }

    protected function casts(): array
    {
        return [
            'inspection_date' => 'date',
            'container_status' => 'boolean',
            'label_status' => 'boolean',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}
