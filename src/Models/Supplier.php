<?php

declare(strict_types=1);

namespace Lastdino\Matex\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lastdino\Matex\Support\Tables;

class Supplier extends Model
{
    protected $fillable = [
        'name', 'email', 'email_cc', 'phone', 'address', 'is_active',
        'contact_person_name',
        'auto_send_po',
    ];

    public function getTable()
    {
        return Tables::name('suppliers');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'supplier_id');
    }

    protected function casts(): array
    {
        return [
            'auto_send_po' => 'boolean',
        ];
    }
}
