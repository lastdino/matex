<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'occurred_at' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.unit' => ['nullable', 'string', 'max:32'],
            // For lot-managed materials: require lot_no or lot_id (validated in controller based on material flag)
            'items.*.lot_no' => ['nullable', 'string', 'max:128'],
            'items.*.lot_id' => ['nullable', 'integer'],
            // Optional polymorphic source for traceability
            'items.*.source_type' => ['nullable', 'string', 'max:255'],
            'items.*.source_id' => ['nullable', 'integer'],
        ];
    }
}
