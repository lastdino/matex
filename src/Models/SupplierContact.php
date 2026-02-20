<?php

declare(strict_types=1);

namespace Lastdino\Matex\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lastdino\Matex\Support\Tables;

class SupplierContact extends Model
{
    protected $fillable = [
        'supplier_id',
        'department',
        'name',
        'email',
        'email_cc',
        'phone',
        'address',
        'is_primary',
        'is_active',
    ];

    public function getTable()
    {
        return Tables::name('supplier_contacts');
    }

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
