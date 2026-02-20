<?php

declare(strict_types=1);

namespace Lastdino\Matex\Models;

use Illuminate\Database\Eloquent\Model;
use Lastdino\Matex\Support\Tables;

class UnitConversion extends Model
{
    protected $fillable = [
        'material_id', 'from_unit', 'to_unit', 'factor',
    ];

    public function getTable()
    {
        return Tables::name('unit_conversions');
    }

    protected function casts(): array
    {
        return [
            'factor' => 'decimal:6',
        ];
    }
}
