<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Livewire\Procurement\Receiving;

use Illuminate\Contracts\View\View as ViewContract;
use Lastdino\ProcurementFlow\Actions\Receiving\ReceivePurchaseOrderAction;
use Lastdino\ProcurementFlow\Enums\PurchaseOrderStatus;
use Lastdino\ProcurementFlow\Models\Material;
use Lastdino\ProcurementFlow\Models\PurchaseOrderItem;
use Lastdino\ProcurementFlow\Services\UnitConversionService;
use Livewire\Component;

class Scan extends Component
{
    /**
     * User input form state.
     *
     * @var array{token: string, qty: float|int|null, reference_number: string|null}
     */
    public array $form = [
        'token' => '',
        'qty' => null,
        'reference_number' => null,
        // lot fields (conditionally required)
        'lot_no' => null,
        'mfg_date' => null,
        'expiry_date' => null,
    ];

    /**
     * Display info loaded by lookup.
     *
     * @var array{
     *   po_number: string,
     *   po_status: string,
     *   material_name: string,
     *   material_sku: string,
     *   ordered_base: float|int|string,
     *   remaining_base: float|int|string,
     *   manage_by_lot: bool,
     *   unit_stock: string
     * }
     */
    public array $info = [
        'po_number' => '',
        'po_status' => '',
        'material_name' => '',
        'material_sku' => '',
        'ordered_base' => '',
        'remaining_base' => '',
        'manage_by_lot' => false,
        'unit_stock' => '',
    ];

    public string $message = '';

    public bool $ok = false;

    protected function rules(): array
    {
        return [
            'form.token' => ['required', 'string'],
            'form.qty' => ['nullable', 'numeric', 'gt:0'],
            'form.reference_number' => ['nullable', 'string'],
            // Lot fields are validated conditionally in receive()
            'form.lot_no' => ['nullable', 'string', 'max:128'],
            'form.mfg_date' => ['nullable', 'date'],
            'form.expiry_date' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    public function getHasInfoProperty(): bool
    {
        return (bool) ($this->info['po_number'] ?? false);
    }

    public function getCanReceiveProperty(): bool
    {
        $qty = $this->form['qty'];

        return ! empty($this->form['token']) && is_numeric($qty) && (float) $qty > 0;
    }

    public function setMessage(string $text, bool $ok = false): void
    {
        $this->message = $text;
        $this->ok = $ok;
    }

    /**
     * Automatically lookup when token is updated.
     */
    public function updatedFormToken(string $value): void
    {
        $token = trim((string) $value);

        if ($token === '') {
            $this->resetInfo();
            $this->message = '';
            $this->ok = false;

            return;
        }

        /** @var UnitConversionService $conversion */
        $conversion = app(\Lastdino\ProcurementFlow\Services\UnitConversionService::class);
        $this->lookup($conversion);
    }

    public function lookup(UnitConversionService $conversion): void
    {
        $this->validateOnly('form.token');

        /** @var PurchaseOrderItem|null $poi */
        $poi = PurchaseOrderItem::query()
            ->whereScanToken((string) $this->form['token'])
            ->with(['purchaseOrder', 'material'])
            ->first();

        if (! $poi) {
            $this->resetInfo();
            $this->setMessage(__('procflow::receiving.messages.token_not_found'), false);

            return;
        }

        $po = $poi->purchaseOrder;
        if (! in_array($po->status, [PurchaseOrderStatus::Issued, PurchaseOrderStatus::Receiving], true)) {
            $this->resetInfo();
            $this->setMessage(__('procflow::receiving.messages.not_receivable_status'), false);

            return;
        }

        // Shipping lines are not receivable via scan
        if ($poi->unit_purchase === 'shipping') {
            $this->resetInfo();
            $this->setMessage(__('procflow::receiving.messages.shipping_line_excluded'), false);

            return;
        }

        $material = $poi->material;
        if (! $material) {
            // Ad-hoc line (no material): show minimal info without conversion
            $orderedBase = max((float) $poi->qty_ordered - (float) ($poi->qty_canceled ?? 0), 0.0);
            $receivedBase = (float) $poi->receivingItems()->sum('qty_base');
            $remainingBase = max($orderedBase - $receivedBase, 0.0);
            if ($remainingBase <= 0.0) {
                $this->resetInfo();
                $this->setMessage(__('procflow::receiving.messages.not_receivable_status'), false);

                return;
            }

            $this->info = [
                'po_number' => (string) $po->po_number,
                'po_status' => (string) $po->status->value,
                'material_name' => '(アドホック項目)',
                'material_sku' => '',
                'ordered_base' => $orderedBase,
                'remaining_base' => $remainingBase,
                'manage_by_lot' => false,
                'unit_stock' => '',
            ];

            $this->setMessage(__('procflow::receiving.messages.recognized_enter_qty_adhoc'), true);

            return;
        }

        $effectiveOrdered = max((float) $poi->qty_ordered - (float) ($poi->qty_canceled ?? 0), 0.0);
        $orderedBase = $effectiveOrdered * (float) $conversion->factor($material, $poi->unit_purchase, $material->unit_stock);
        $receivedBase = (float) $poi->receivingItems()->sum('qty_base');
        $remainingBase = max($orderedBase - $receivedBase, 0.0);
        if ($remainingBase <= 0.0) {
            $this->resetInfo();
            $this->setMessage(__('procflow::receiving.messages.not_receivable_status'), false);

            return;
        }

        $this->info = [
            'po_number' => (string) $po->po_number,
            'po_status' => (string) $po->status->value,
            'material_name' => (string) $material->name,
            'material_sku' => (string) $material->sku,
            'ordered_base' => $orderedBase,
            'remaining_base' => $remainingBase,
            'manage_by_lot' => (bool) ($material->manage_by_lot ?? false),
            'unit_stock' => (string) $material->unit_stock,
        ];

        $this->setMessage(__('procflow::receiving.messages.recognized_enter_qty'), true);
    }

    public function receive(UnitConversionService $conversion, ReceivePurchaseOrderAction $action): void
    {
        // Validate token and qty
        $this->validate([
            'form.token' => ['required', 'string'],
            'form.qty' => ['required', 'numeric', 'gt:0'],
            'form.reference_number' => ['nullable', 'string'],
            'form.lot_no' => ['nullable', 'string', 'max:128'],
            'form.mfg_date' => ['nullable', 'date'],
            'form.expiry_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        try {
            $receiving = $action->byScan([
                'token' => (string) $this->form['token'],
                'qty' => (float) $this->form['qty'],
                'reference_number' => $this->form['reference_number'] ?? null,
                'lot_no' => $this->form['lot_no'] ?? null,
                'mfg_date' => $this->form['mfg_date'] ?? null,
                'expiry_date' => $this->form['expiry_date'] ?? null,
            ]);

            // Reset token and displayed information after successful receive
            $this->resetAfterReceive();
            $this->setMessage(__('procflow::receiving.messages.received_success'), true);
            // Bring focus back to token input for faster scanning flow
            $this->dispatch('focus-token');
        } catch (\Throwable $e) {
            $this->setMessage(__('procflow::receiving.messages.receive_failed', ['message' => $e->getMessage()]), false);
        }
    }

    public function resetInfo(): void
    {
        $this->info = [
            'po_number' => '',
            'po_status' => '',
            'material_name' => '',
            'material_sku' => '',
            'remaining_base' => '',
            'manage_by_lot' => false,
            'unit_stock' => '',
        ];
    }

    public function render(): ViewContract
    {
        return view('procflow::livewire.procurement.receiving.scan');
    }

    /**
     * Reset form fields and info after a successful receive.
     */
    protected function resetAfterReceive(): void
    {
        // Clear token and input fields
        $this->form = [
            'token' => '',
            'qty' => null,
            'reference_number' => null,
            'lot_no' => null,
            'mfg_date' => null,
            'expiry_date' => null,
        ];

        // Clear displayed information
        $this->resetInfo();
    }
}
