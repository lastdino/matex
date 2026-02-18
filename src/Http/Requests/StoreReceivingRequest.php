<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Lastdino\ProcurementFlow\Support\Tables;

class StoreReceivingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'received_at' => ['required', 'date'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'exists:'.Tables::name('purchase_order_items').',id'],
            'items.*.unit_purchase' => ['nullable', 'string', 'max:32'],
            'items.*.qty_received' => ['required', 'numeric', 'gt:0'],
            // Lot-related optional fields (enforced conditionally in controller when material.manage_by_lot=true)
            'items.*.lot_no' => ['nullable', 'string', 'max:128'],
            'items.*.mfg_date' => ['nullable', 'date'],
            'items.*.expiry_date' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }
}
