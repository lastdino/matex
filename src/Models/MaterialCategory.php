<?php

declare(strict_types=1);

namespace Lastdino\Matex\Models;

use Illuminate\Database\Eloquent\Model;
use Lastdino\Matex\Support\Tables;

class MaterialCategory extends Model
{
    protected $fillable = [
        'name',
    ];

    public function getTable()
    {
        return Tables::name('material_categories');
    }
}
