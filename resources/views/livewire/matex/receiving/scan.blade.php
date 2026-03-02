<?php

use Lastdino\Matex\Actions\Receiving\ReceivePurchaseOrderAction;
use Lastdino\Matex\Enums\PurchaseOrderStatus;
use Lastdino\Matex\Models\Material;
use Lastdino\Matex\Models\PurchaseOrderItem;
use Lastdino\Matex\Models\StorageLocation;
use Lastdino\Matex\Services\UnitConversionService;
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
     *   department_name: string|null,
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
        'department_name' => null,
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

    public function resetScan(): void
    {
        $this->resetAfterReceive();
        $this->message = '';
        $this->ok = false;
        $this->dispatch('focus-token');
    }

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
            'form.storage_location_id' => ['required', 'integer'],
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
        $conversion = app(\Lastdino\Matex\Services\UnitConversionService::class);
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
            ->with(['purchaseOrder.department', 'material'])
            ->first();

        if (! $poi) {
            $this->resetInfo();
            $this->setMessage(__('matex::receiving.messages.token_not_found'), false);

            return;
        }

        $po = $poi->purchaseOrder;
        if (! in_array($po->status, [PurchaseOrderStatus::Issued, PurchaseOrderStatus::Receiving], true)) {
            $this->resetInfo();
            $this->setMessage(__('matex::receiving.messages.not_receivable_status'), false);

            return;
        }

        // Shipping lines are not receivable via scan
        if ($poi->unit_purchase === 'shipping') {
            $this->resetInfo();
            $this->setMessage(__('matex::receiving.messages.shipping_line_excluded'), false);

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
                $this->setMessage(__('matex::receiving.messages.not_receivable_status'), false);

                return;
            }

            $this->info = [
                'po_number' => (string) $po->po_number,
                'po_status' => (string) $po->status->value,
                'department_name' => $po->department?->name,
                'material_id' => null,
                'material_name' => '(アドホック項目)',
                'material_sku' => '',
                'ordered_base' => $orderedBase,
                'remaining_base' => $remainingBase,
                'unit_stock' => '',
                'available_units' => [],
            ];
            $this->form['unit'] = '';

            $this->setMessage(__('matex::receiving.messages.recognized_enter_qty_adhoc'), true);

            return;
        }

        $effectiveOrdered = max((float) $poi->qty_ordered - (float) ($poi->qty_canceled ?? 0), 0.0);
        $orderedBase = $effectiveOrdered * (float) $conversion->factor($material, $poi->unit_purchase, $material->unit_stock);
        $receivedBase = (float) $poi->receivingItems()->sum('qty_base');
        $remainingBase = max($orderedBase - $receivedBase, 0.0);
        if ($remainingBase <= 0.0) {
            $this->resetInfo();
            $this->setMessage(__('matex::receiving.messages.not_receivable_status'), false);

            return;
        }

        $this->info = [
            'po_number' => (string) $po->po_number,
            'po_status' => (string) $po->status->value,
            'department_name' => $po->department?->name,
            'material_id' => $material->id,
            'material_name' => (string) $material->name,
            'material_sku' => (string) $material->sku,
            'ordered_base' => $orderedBase,
            'remaining_base' => $remainingBase,
            'unit_stock' => (string) $material->unit_stock,
            'available_units' => $conversion->getAvailableUnits($material),
        ];
        $this->form['unit'] = $poi->unit_purchase;

        $this->setMessage(__('matex::receiving.messages.recognized_enter_qty'), true);
    }

    public function receive(UnitConversionService $conversion, ReceivePurchaseOrderAction $action): void
    {
        // Validate token and qty
        $this->validate([
            'form.token' => ['required', 'string'],
            'form.qty' => ['required', 'numeric', 'gt:0'],
            'form.unit' => ['nullable', 'string'],
            'form.reference_number' => ['nullable', 'string'],
            'form.storage_location_id' => ['required', 'integer'],
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
            $this->setMessage(__('matex::receiving.messages.received_success'), true);
            // Bring focus back to token input for faster scanning flow
            $this->dispatch('focus-token');
        } catch (\Throwable $e) {
            $this->setMessage(__('matex::receiving.messages.receive_failed', ['message' => $e->getMessage()]), false);
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

<x-matex::scan-page-layout
    :has-info="$this->hasInfo"
    :title="__('matex::receiving.title')"
>
    <x-slot name="backLink">
        <flux:button href="{{ route('matex.purchase-orders.index') }}" wire:navigate variant="subtle">{{ __('matex::receiving.back_to_list') }}</flux:button>
    </x-slot>

    <x-slot name="waitTitle">
        入荷用QRコードをスキャンしてください
    </x-slot>

    <x-slot name="waitScanner">
        <livewire:matex::qr-scanner wire:model.live="form.token" />
    </x-slot>

    <x-slot name="waitDescription">
        <p>発注書または現品票のQRコードをスキャンするか、トークンを入力してください。</p>
    </x-slot>

    <x-slot name="waitInput">
        <flux:input
            id="token"
            x-ref="token"
            wire:model.live.debounce.500ms="form.token"
            placeholder="{{ __('matex::receiving.token_placeholder') }}"
            icon="magnifying-glass"
        />
    </x-slot>

    <x-slot name="messages">
        @if ($message)
            <flux:callout :variant="$ok ? 'success' : 'danger'" class="{{ $this->hasInfo ? 'shadow-sm' : 'mt-2 text-center' }}">
                {{ $message }}
            </flux:callout>
        @endif
    </x-slot>

    <x-slot name="infoTitle">
        {{ __('matex::receiving.info') }}
    </x-slot>

    <x-slot name="infoCard">
        <div class="space-y-4">
            <div class="space-y-1">
                <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">部門</label>
                <div class="text-lg font-bold text-gray-900 dark:text-white leading-tight">
                    {{ $info['department_name'] ?: '---' }}
                </div>
            </div>
            <div class="space-y-1">
                <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('matex::receiving.info_material') }}</label>
                <div class="text-lg font-bold text-gray-900 dark:text-white leading-tight">
                    {{ $info['material_name'] }}
                </div>
                <div class="text-sm text-gray-500 font-mono">
                    {{ $info['material_sku'] }}
                </div>
            </div>

            <div class="pt-4 border-t dark:border-neutral-700 space-y-3">
                <div class="flex justify-between items-center">
                    <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('matex::receiving.info_po') }}</label>
                    <flux:badge size="sm" color="blue" inset="top bottom">{{ $info['po_status'] }}</flux:badge>
                </div>
                <div class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                    {{ $info['po_number'] }}
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 pt-4 border-t dark:border-neutral-700">
                <div class="space-y-1">
                    <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('matex::receiving.info_ordered_base') }}</label>
                    <div class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                        {{ $info['ordered_base'] }} <span class="text-xs font-normal text-gray-500">{{ $info['unit_stock'] }}</span>
                    </div>
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('matex::receiving.info_remaining_base') }}</label>
                    <div class="text-sm font-semibold text-red-600 dark:text-red-400">
                        {{ $info['remaining_base'] }} <span class="text-xs font-normal text-gray-500">{{ $info['unit_stock'] }}</span>
                    </div>
                </div>
            </div>
        </div>
    </x-slot>

    <x-slot name="actionForm">
        <div class="rounded-xl border bg-white p-6 shadow-sm dark:bg-neutral-800 dark:border-neutral-700">
            <div class="space-y-6">
                {{-- Quantity & Unit & Storage --}}
                <div class="grid gap-6 sm:grid-cols-2">
                    <div class="space-y-3">
                        <flux:label class="text-base font-bold">{{ __('matex::receiving.qty_received') }}</flux:label>
                        <div class="flex gap-2">
                            <flux:input
                                type="number"
                                step="0.000001"
                                min="0"
                                wire:model.live="form.qty"
                                class="flex-1 !text-xl font-bold"
                            />
                            @if (!empty($info['available_units']))
                                <div class="w-24">
                                    <flux:select wire:model.live="form.unit">
                                        @foreach($info['available_units'] as $u)
                                            <flux:select.option :value="$u">{{ $u }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                </div>
                            @endif
                        </div>
                        <flux:error name="form.qty" />
                    </div>

                    <div class="space-y-3">
                        <flux:label class="text-base font-bold">保管場所</flux:label>
                        <flux:select wire:model.live="form.storage_location_id" placeholder="場所を選択..." required>
                            @foreach(Lastdino\Matex\Models\StorageLocation::query()->where('is_active', true)->orderBy('name')->get() as $loc)
                                <flux:select.option :value="$loc->id">{{ $loc->name }} @if($loc->max_specified_quantity_ratio) (指定数量倍率: {{ $loc->max_specified_quantity_ratio }}) @endif</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="form.storage_location_id" />
                    </div>
                </div>

                @if ($storageWarning)
                    <flux:callout variant="warning" class="shadow-sm">
                        {{ $storageWarning }}
                    </flux:callout>
                @endif

                <div class="pt-6 border-t dark:border-neutral-700">
                    <flux:input wire:model="form.reference_number" label="{{ __('matex::receiving.reference_number') }}" placeholder="納品書番号など"/>
                </div>

                {{-- Lot Info Section --}}
                <div class="pt-6 border-t dark:border-neutral-700 space-y-4">
                    <flux:heading size="md">{{ __('matex::receiving.lot_section_title') }}</flux:heading>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <flux:field>
                            <flux:label>{{ __('matex::receiving.lot_no') }}</flux:label>
                            <flux:input wire:model="form.lot_no" placeholder="{{ __('matex::receiving.lot_no_placeholder') }}"/>
                            <flux:error name="form.lot_no" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('matex::receiving.mfg_date') }}</flux:label>
                            <flux:input type="date" wire:model="form.mfg_date"/>
                            <flux:error name="form.mfg_date" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('matex::receiving.expiry_date') }}</flux:label>
                            <flux:input type="date" wire:model="form.expiry_date"/>
                            <flux:error name="form.expiry_date" />
                        </flux:field>
                    </div>
                    <p class="text-xs text-neutral-500">{{ __('matex::receiving.lot_notice') }}</p>
                </div>

                {{-- Action Button --}}
                <div class="pt-6 border-t dark:border-neutral-700">
                    <flux:button
                        variant="primary"
                        wire:click="receive"
                        wire:loading.attr="disabled"
                        wire:target="receive"
                        icon="arrow-down-tray"
                        class="w-full !py-6 !text-lg font-bold shadow-lg shadow-blue-500/20"
                    >
                        {{ __('matex::receiving.receive') }}
                    </flux:button>
                </div>
            </div>
        </div>
    </x-slot>
</x-matex::scan-page-layout>
