<?php

declare(strict_types=1);

namespace Lastdino\Matex\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReceivingByScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'received_at' => ['nullable', 'date'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            // Lot related (conditionally required in controller depending on material.manage_by_lot)
            'lot_no' => ['nullable', 'string', 'max:128'],
            'mfg_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }
}
