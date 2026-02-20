<?php

use Lastdino\ProcurementFlow\Actions\Receiving\ReceivePurchaseOrderAction;
use Lastdino\ProcurementFlow\Enums\PurchaseOrderStatus;
use Lastdino\ProcurementFlow\Models\Material;
use Lastdino\ProcurementFlow\Models\PurchaseOrderItem;
use Lastdino\ProcurementFlow\Models\StorageLocation;
use Lastdino\ProcurementFlow\Services\UnitConversionService;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    /**
     * Token passed from URL.
     */
    #[Url]
    public string $token = '';

    /**
     * User input form state.
     *
     * @var array{token: string, qty: float|int|null, unit: string|null, reference_number: string|null, storage_location_id: int|null}
     */
    public array $form = [
        'token' => '',
        'qty' => null,
        'unit' => null,
        'reference_number' => null,
        'storage_location_id' => '',
        // lot fields (conditionally required)
        'lot_no' => '-',
        'mfg_date' => null,
        'expiry_date' => null,
    ];

    /**
     * Display info loaded by lookup.
     *
     * @var array{
     *   po_number: string,
     *   po_status: string,
     *   material_id: int|null,
     *   material_name: string,
     *   material_sku: string,
     *   ordered_base: float|int|string,
     *   remaining_base: float|int|string,
     *   unit_stock: string,
     *   available_units: array<int, string>
     * }
     */
    public array $info = [
        'po_number' => '',
        'po_status' => '',
        'material_id' => null,
        'material_name' => '',
        'material_sku' => '',
        'ordered_base' => '',
        'remaining_base' => '',
        'unit_stock' => '',
        'available_units' => [],
    ];

    public string $message = '';

    public bool $ok = false;

    public string $storageWarning = '';

    public function mount(UnitConversionService $conversion): void
    {
        if (! empty($this->token)) {
            $this->form['token'] = $this->token;
            $this->lookup($conversion);
        }
    }

    protected function rules(): array
    {
        return [
            'form.token' => ['required', 'string'],
            'form.qty' => ['nullable', 'numeric', 'gt:0'],
            'form.reference_number' => ['nullable', 'string'],
            'form.storage_location_id' => ['nullable', 'integer'],
            // Lot fields
            'form.lot_no' => ['required', 'string', 'max:128'],
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

    public function updatedFormQty(): void
    {
        $this->checkStorageLimit();
    }

    public function updatedFormUnit(): void
    {
        $this->checkStorageLimit();
    }

    public function updatedFormStorageLocationId(): void
    {
        $this->checkStorageLimit();
    }

    protected function checkStorageLimit(): void
    {
        $this->storageWarning = '';

        if (! $this->form['storage_location_id'] || ! $this->form['qty'] || ! $this->info['material_id']) {
            return;
        }

        /** @var Material|null $material */
        $material = Material::find($this->info['material_id']);
        if (! $material || ! $material->is_chemical || (float) $material->specified_quantity <= 0) {
            return;
        }

        /** @var StorageLocation|null $location */
        $location = StorageLocation::find($this->form['storage_location_id']);
        if (! $location || $location->max_specified_quantity_ratio === null || (float) $location->max_specified_quantity_ratio <= 0) {
            return;
        }

        try {
            /** @var UnitConversionService $conversion */
            $conversion = app(UnitConversionService::class);
            $fromUnit = $this->form['unit'] ?: $material->unit_stock;
            $factor = (float) $conversion->factor($material, $fromUnit, $material->unit_stock);
            $qtyBase = (float) $this->form['qty'] * $factor;

            $currentRatio = $location->currentSpecifiedQuantityRatio();
            $newRatio = $qtyBase / (float) $material->specified_quantity;
            $totalRatio = $currentRatio + $newRatio;

            if ($totalRatio > (float) $location->max_specified_quantity_ratio) {
                $this->storageWarning = "保管場所「{$location->name}」の指定数量倍率（{$location->max_specified_quantity_ratio}）を超過します。現在の合計: ".number_format($totalRatio, 2);
            }
        } catch (\Exception $e) {
            // Silence conversion errors during real-time check
        }
    }

    /**
     * Automatically lookup when token is updated.
     */
    protected function normalizeToken(string $raw): string
    {
        $token = trim($raw);
        try {
            if (preg_match('/token=([A-Za-z0-9\-]+)/', $token, $m)) {
                return (string) ($m[1] ?? $token);
            }

            if (filter_var($token, FILTER_VALIDATE_URL)) {
                $q = parse_url($token, PHP_URL_QUERY) ?: '';
                if (is_string($q) && $q !== '') {
                    parse_str($q, $qs);
                    if (! empty($qs['token'])) {
                        return (string) $qs['token'];
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return $token;
    }

    public function updatedFormToken(string $value): void
    {
        // Accept either a raw token or a full URL containing ?token=...
        $parsed = $this->normalizeToken($value);
        if ($parsed !== $this->form['token']) {
            $this->form['token'] = $parsed;
        }

        if ($parsed === '') {
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
        // Normalize in case lookup is triggered manually after raw URL was set
        $this->form['token'] = $this->normalizeToken((string) ($this->form['token'] ?? ''));
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
                'material_id' => null,
                'material_name' => '(アドホック項目)',
                'material_sku' => '',
                'ordered_base' => $orderedBase,
                'remaining_base' => $remainingBase,
                'unit_stock' => '',
                'available_units' => [],
            ];
            $this->form['unit'] = '';

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
            'material_id' => $material->id,
            'material_name' => (string) $material->name,
            'material_sku' => (string) $material->sku,
            'ordered_base' => $orderedBase,
            'remaining_base' => $remainingBase,
            'unit_stock' => (string) $material->unit_stock,
            'available_units' => $conversion->getAvailableUnits($material),
        ];
        $this->form['unit'] = $poi->unit_purchase;

        $this->setMessage(__('procflow::receiving.messages.recognized_enter_qty'), true);
    }

    public function receive(UnitConversionService $conversion, ReceivePurchaseOrderAction $action): void
    {
        // Validate token and qty
        $this->validate([
            'form.token' => ['required', 'string'],
            'form.qty' => ['required', 'numeric', 'gt:0'],
            'form.unit' => ['nullable', 'string'],
            'form.reference_number' => ['nullable', 'string'],
            'form.lot_no' => ['required', 'string', 'max:128'],
            'form.mfg_date' => ['nullable', 'date'],
            'form.expiry_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        try {
            $receiving = $action->byScan([
                'token' => (string) $this->form['token'],
                'qty' => (float) $this->form['qty'],
                'unit_purchase' => $this->form['unit'] ?: '',
                'reference_number' => $this->form['reference_number'] ?? null,
                'storage_location_id' => $this->form['storage_location_id'] ?? '',
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
            'material_id' => null,
            'material_name' => '',
            'material_sku' => '',
            'ordered_base' => '',
            'remaining_base' => '',
            'unit_stock' => '',
            'available_units' => [],
        ];
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
            'unit' => '',
            'reference_number' => null,
            'storage_location_id' => '',
            'lot_no' => '-',
            'mfg_date' => null,
            'expiry_date' => null,
        ];

        // Clear displayed information
        $this->resetInfo();
    }
};

?>

<div class="p-6 space-y-4" x-data @focus-token.window="$refs.token?.focus(); $refs.token?.select()">
    <x-procflow::topmenu />
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">{{ __('procflow::receiving.title') }}</h1>
        <a href="{{ route('procurement.purchase-orders.index') }}" class="text-blue-600 hover:underline">{{ __('procflow::receiving.back_to_list') }}</a>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <div class="rounded border p-4 space-y-4">
            <flux:heading size="sm">{{ __('procflow::receiving.token') }}</flux:heading>
            <div class="flex gap-2">
                <flux:input
                    id="token"
                    x-ref="token"
                    wire:model.live.debounce.300ms="form.token"
                    placeholder="{{ __('procflow::receiving.token_placeholder') }}"
                    class="flex-1"
                />
                <livewire:procflow::qr-scanner wire:model.live="form.token" />
            </div>
            <div class="flex gap-2">
                <flux:button
                    variant="outline"
                    wire:click="lookup"
                    wire:loading.attr="disabled"
                    wire:target="lookup"
                >{{ __('procflow::receiving.lookup') }}</flux:button>
            </div>

            @if ($message)
                @if ($ok)
                    <flux:callout variant="success" class="mt-2">{{ $message }}</flux:callout>
                @else
                    <flux:callout variant="danger" class="mt-2">{{ $message }}</flux:callout>
                @endif
            @endif
        </div>

        <div class="rounded border p-4 space-y-4">
            <flux:heading size="sm">{{ __('procflow::receiving.info') }}</flux:heading>

            @if ($this->hasInfo)
                <div class="text-sm text-gray-700 space-y-1">
                    <div>{{ __('procflow::receiving.info_po') }}: <span class="font-medium">{{ $info['po_number'] }}</span> (<span>{{ $info['po_status'] }}</span>)</div>
                    <div>{{ __('procflow::receiving.info_material') }}: <span class="font-medium">{{ $info['material_name'] }}</span> [<span>{{ $info['material_sku'] }}</span>]</div>
                    <div>{{ __('procflow::receiving.info_ordered_base') }}: <span class="font-medium">{{ $info['ordered_base'] }}</span>@if($info['unit_stock']) <span class="ml-1 text-gray-500">{{ $info['unit_stock'] }}</span>@endif</div>
                    <div>{{ __('procflow::receiving.info_remaining_base') }}: <span class="font-medium">{{ $info['remaining_base'] }}</span>@if($info['unit_stock']) <span class="ml-1 text-gray-500">{{ $info['unit_stock'] }}</span>@endif</div>
                </div>
            @endif

            <div class="grid gap-3 md:grid-cols-2">
                <div class="flex gap-2 items-end">
                    <flux:input type="number" step="0.000001" min="0" wire:model.live="form.qty" label="{{ __('procflow::receiving.qty_received') }}"/>
                    @if ($this->hasInfo && !empty($info['available_units']))
                        <div class="w-32">
                            <flux:select wire:model.live="form.unit">
                                @foreach($info['available_units'] as $u)
                                    <flux:select.option :value="$u">{{ $u }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                    @endif
                </div>
                <flux:select wire:model.live="form.storage_location_id" label="保管場所" placeholder="場所を選択...">
                    @foreach(Lastdino\ProcurementFlow\Models\StorageLocation::query()->where('is_active', true)->orderBy('name')->get() as $loc)
                        <flux:select.option :value="$loc->id">{{ $loc->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="form.reference_number" label="{{ __('procflow::receiving.reference_number') }}"/>
            </div>

            @if ($storageWarning)
                <flux:callout variant="warning" class="mt-2">{{ $storageWarning }}</flux:callout>
            @endif

            @if ($this->hasInfo)
                <div class="mt-4 space-y-3">
                    <flux:heading size="xs">{{ __('procflow::receiving.lot_section_title') }}</flux:heading>
                    <div class="grid gap-3 md:grid-cols-3">
                        <flux:input wire:model="form.lot_no" label="{{ __('procflow::receiving.lot_no') }}" placeholder="{{ __('procflow::receiving.lot_no_placeholder') }}"/>
                        <flux:input type="date" wire:model="form.mfg_date" label="{{ __('procflow::receiving.mfg_date') }}"/>
                        <flux:input type="date" wire:model="form.expiry_date" label="{{ __('procflow::receiving.expiry_date') }}"/>
                    </div>
                    <p class="text-xs text-gray-500">{{ __('procflow::receiving.lot_notice') }}</p>
                </div>
            @endif
            <flux:button
                variant="primary"
                wire:click="receive"
                wire:loading.attr="disabled"
                wire:target="receive"
            >{{ __('procflow::receiving.receive') }}</flux:button>
        </div>
    </div>
</div>
