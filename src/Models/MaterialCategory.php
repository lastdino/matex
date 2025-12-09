<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Models;

use Lastdino\ProcurementFlow\Support\Tables;
use Illuminate\Database\Eloquent\Model;

class MaterialCategory extends Model
{
    protected $fillable = [
        'name', 'code',
    ];

    public function getTable()
    {
        return Tables::name('material_categories');
    }
}
