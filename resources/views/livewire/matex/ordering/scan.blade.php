<?php

use Illuminate\Http\Request;
use Lastdino\Matex\Actions\Ordering\CreateDraftPurchaseOrderFromScanAction;
use Lastdino\Matex\Models\OrderingToken;
use Lastdino\Matex\Services\OptionCatalogService;
use Lastdino\Matex\Services\OptionSelectionRuleBuilder;
use Livewire\Component;

new class extends Component
{
    /**
     * @var array{token:string, qty:float|int|null}
     */
    public array $form = [
        'token' => '',
        'qty' => null,
        // オプション選択（group_id => option_id）
        'options' => [],
    ];

    /**
     * @var array{material_name:string,material_sku:string,preferred_supplier:string|null,unit_purchase:string|null,moq:string|float|int|null,pack_size:string|float|int|null,default_qty:string|float|int|null}
     */
    public array $info = [
        'material_name' => '',
        'material_sku' => '',
        'department_name' => null,
        'preferred_supplier' => null,
        'preferred_supplier_contact' => null,
        'unit_purchase' => null,
        'moq' => null,
        'pack_size' => null,
        'default_qty' => null,
    ];

    /**
     * @var array<int,array{id:int,name:string}>
     */
    public array $optionGroups = [];

    /**
     * @var array<int,array<int,array{id:int,name:string}>>
     */
    public array $optionsByGroup = [];

    public string $message = '';

    public bool $ok = false;

    public function resetScan(): void
    {
        $this->resetAfterOrder();
        $this->message = '';
        $this->ok = false;
        $this->dispatch('focus-token');
    }

    protected function rules(): array
    {
        return [
            'form.token' => ['required', 'string'],
            'form.qty' => ['nullable', 'numeric', 'gt:0'],
            'form.options' => ['array'],
        ];
    }

    public function getHasInfoProperty(): bool
    {
        return (bool) ($this->info['material_name'] ?? false);
    }

    public function setMessage(string $text, bool $ok = false): void
    {
        $this->message = $text;
        $this->ok = $ok;
    }

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
        $parsed = $this->normalizeToken((string) $value);
        if ($parsed !== $this->form['token']) {
            $this->form['token'] = $parsed;
        }

        if ($parsed === '') {
            $this->resetInfo();
            $this->message = '';
            $this->ok = false;

            return;
        }

        $this->lookup();
    }

    public function mount(Request $request): void
    {
        // If QR payload used URL with ?token=..., prefill and auto-lookup
        $rawToken = trim((string) $request->query('token', ''));
        if ($rawToken !== '') {
            $this->form['token'] = $this->normalizeToken($rawToken);
            // Defer lookup to next tick to allow hydration
            $this->dispatch('focus-token');
            $this->lookup();
        }
    }

    public function lookup(): void
    {
        // Normalize in case lookup is triggered manually after raw URL was set
        $this->form['token'] = $this->normalizeToken((string) ($this->form['token'] ?? ''));
        $this->validateOnly('form.token');

        /** @var OrderingToken|null $ot */
        $ot = OrderingToken::query()
            ->where('token', (string) $this->form['token'])
            ->with(['material.preferredSupplier', 'material.preferredSupplierContact', 'department'])
            ->first();
        if (! $ot || ! $ot->enabled || ($ot->expires_at && now()->greaterThan($ot->expires_at))) {
            $this->resetInfo();
            $this->setMessage(__('matex::ordering.messages.invalid_or_expired_token'), false);

            return;
        }

        $mat = $ot->material;
        if (! $mat) {
            $this->resetInfo();
            $this->setMessage(__('matex::ordering.messages.material_not_found'), false);

            return;
        }

        if (! (bool) ($mat->is_active ?? true)) {
            $this->resetInfo();
            $this->setMessage(__('matex::ordering.messages.material_not_found'), false);

            return;
        }

        $this->info = [
            'material_name' => (string) ($mat->name ?? ''),
            'material_sku' => (string) ($mat->sku ?? ''),
            'department_name' => $ot->department?->name,
            'preferred_supplier' => $mat->preferredSupplier?->name,
            'preferred_supplier_contact' => $mat->preferredSupplierContact?->name,
            'unit_purchase' => $ot->unit_purchase ?? $mat->unit_purchase_default,
            'moq' => $mat->moq,
            'pack_size' => $mat->pack_size,
            'default_qty' => $ot->default_qty,
        ];

        // オプショングループ/選択肢をロード（既存PO作成と同等：Activeのみ、並び順あり）
        $this->loadActiveOptions();
        // 既定の選択をセット（トークンに保存されている場合）、なければ空
        $this->form['options'] = $ot->options ?? [];

        // 既定数量がある場合はフォームに反映
        if (empty($this->form['qty']) && $this->info['default_qty']) {
            $this->form['qty'] = (float) $this->info['default_qty'];
        }

        $this->setMessage(__('matex::ordering.messages.recognized_enter_qty'), true);
    }

    public function incrementQty(float $step = 1): void
    {
        $current = (float) ($this->form['qty'] ?? 0);
        $this->form['qty'] = $current + $step;
    }

    public function decrementQty(float $step = 1): void
    {
        $current = (float) ($this->form['qty'] ?? 0);
        $next = $current - $step;
        $this->form['qty'] = $next > 0 ? $next : null;
    }

    public function order(CreateDraftPurchaseOrderFromScanAction $action): void
    {
        // Build dynamic rules to require options for all active groups
        $rules = [
            'form.token' => ['required', 'string'],
            'form.qty' => ['required', 'numeric', 'gt:0'],
        ];

        $activeGroups = app(OptionCatalogService::class)->getActiveGroups();
        $optionRules = app(OptionSelectionRuleBuilder::class)->build('form.options', $activeGroups);
        $rules = $rules + $optionRules;

        $this->validate($rules);

        try {
            $po = $action->handle([
                'token' => (string) $this->form['token'],
                'qty' => (float) $this->form['qty'],
                'options' => (array) ($this->form['options'] ?? []),
            ]);
            $this->resetAfterOrder();
            $this->setMessage(__('matex::ordering.messages.draft_created'), true);
            // 作成したPOへ遷移する場合は以下を有効化
            // $this->redirectRoute('matex.purchase-orders.show', ['po' => $po->id]);
            $this->dispatch('focus-token');
        } catch (\Throwable $e) {
            $this->setMessage(__('matex::ordering.messages.order_failed', ['message' => $e->getMessage()]), false);
        }
    }

    public function resetInfo(): void
    {
        $this->info = [
            'material_name' => '',
            'material_sku' => '',
            'preferred_supplier' => null,
            'preferred_supplier_contact' => null,
            'unit_purchase' => null,
            'moq' => null,
            'pack_size' => null,
            'default_qty' => null,
        ];
    }

    public function resetAfterOrder(): void
    {
        $this->resetInfo();
        $this->form['token'] = '';
        $this->form['qty'] = null;
        $this->form['options'] = [];
        $this->optionGroups = [];
        $this->optionsByGroup = [];
    }

    protected function loadActiveOptions(): void
    {
        $catalog = app(OptionCatalogService::class);
        $groups = $catalog->getActiveGroups();
        $this->optionGroups = [];
        foreach ($groups as $g) {
            $this->optionGroups[] = [
                'id' => (int) $g->getKey(),
                'name' => (string) $g->getAttribute('name'),
            ];
        }

        $this->optionsByGroup = $catalog->getActiveOptionsByGroup();
    }
};

?>

<x-matex::scan-page-layout
    :has-info="$this->hasInfo"
    :title="__('matex::ordering.title')"
>
    <x-slot name="backLink">
        <flux:button href="{{ route('matex.purchase-orders.index') }}" wire:navigate variant="subtle">{{ __('matex::ordering.back') }}</flux:button>
    </x-slot>

    <x-slot name="waitTitle">
        発注用QRコードをスキャンしてください
    </x-slot>

    <x-slot name="waitScanner">
        <livewire:matex::qr-scanner wire:model.live="form.token" />
    </x-slot>

    <x-slot name="waitDescription">
        <p>発注用QRコードをスキャンするか、トークンを入力してください。</p>
    </x-slot>

    <x-slot name="waitInput">
        <flux:input
            id="token"
            x-ref="token"
            wire:model.live.debounce.500ms="form.token"
            placeholder="{{ __('matex::ordering.token.placeholder') }}"
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
        {{ __('matex::ordering.info.title') }}
    </x-slot>

    <x-slot name="infoCard">
        <div class="space-y-4">
            <div class="space-y-1">
                <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">部門</label>
                <div class="text-lg font-bold text-gray-900 dark:text-white leading-tight">
                    {{ $this->info['department_name'] ?: '---' }}
                </div>
            </div>
            <div class="space-y-1">
                <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('matex::ordering.info.material') }}</label>
                <div class="text-lg font-bold text-gray-900 dark:text-white leading-tight">
                    {{ $info['material_name'] }}
                </div>
                <div class="text-sm text-gray-500 font-mono">
                    {{ $info['material_sku'] }}
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 pt-4 border-t dark:border-neutral-700">
                <div class="space-y-1">
                    <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('matex::ordering.info.supplier') }}</label>
                    <div class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                        {{ $info['preferred_supplier'] ?? __('matex::ordering.common.not_set') }}
                    </div>
                    @if($info['preferred_supplier_contact'])
                        <div class="text-xs text-gray-500 font-normal">
                            ({{ $info['preferred_supplier_contact'] }})
                        </div>
                    @endif
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('matex::ordering.info.unit_purchase') }}</label>
                    <div class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                        {{ $info['unit_purchase'] ?? '-' }}
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                @if($info['moq'])
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('matex::ordering.info.moq') }}</label>
                        <div class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $info['moq'] }}</div>
                    </div>
                @endif
                @if($info['pack_size'])
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('matex::ordering.info.pack_size') }}</label>
                        <div class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $info['pack_size'] }}</div>
                    </div>
                @endif
            </div>
        </div>
    </x-slot>

    <x-slot name="actionForm">
        <div class="rounded-xl border bg-white p-6 shadow-sm dark:bg-neutral-800 dark:border-neutral-700">
            <div class="space-y-6">
                {{-- Quantity Selector --}}
                <div class="space-y-3">
                    <flux:label class="text-base font-bold">{{ __('matex::ordering.qty.label') }}</flux:label>
                    <div class="flex items-stretch gap-4">
                        <div class="flex-1 relative">
                            <flux:input
                                type="number"
                                step="0.000001"
                                min="0"
                                wire:model.number="form.qty"
                                class="!text-2xl !py-4 font-bold text-center"
                            />
                        </div>
                        <div class="flex flex-col gap-2">
                            <flux:button variant="outline" class="flex-1 px-6" wire:click="incrementQty" title="+1">
                                <flux:icon.plus class="h-6 w-6" />
                            </flux:button>
                            <flux:button variant="outline" class="flex-1 px-6" wire:click="decrementQty" title="-1">
                                <flux:icon.minus class="h-6 w-6" />
                            </flux:button>
                        </div>
                    </div>
                    <flux:error name="form.qty" />
                </div>

                {{-- Options Selection --}}
                @if (!empty($optionGroups))
                    <div class="pt-6 border-t dark:border-neutral-700">
                        <flux:heading size="md" class="mb-4">{{ __('matex::ordering.options.title') }}</flux:heading>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            @foreach($optionGroups as $g)
                                @php $gid = $g['id']; $opts = $optionsByGroup[$gid] ?? []; @endphp
                                <flux:field>
                                    <flux:label class="font-medium text-gray-700 dark:text-gray-300">{{ $g['name'] }}</flux:label>
                                    <select
                                        class="w-full border border-gray-300 rounded-lg p-3 bg-white text-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-neutral-900 dark:border-neutral-600 dark:text-white"
                                        wire:model.defer="form.options.{{ $gid }}"
                                    >
                                        <option value="">-</option>
                                        @foreach($opts as $o)
                                            <option value="{{ $o['id'] }}">{{ $o['name'] }}</option>
                                        @endforeach
                                    </select>
                                    <flux:error name="form.options.{{ $gid }}" />
                                </flux:field>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Action Button --}}
                <div class="pt-6 border-t dark:border-neutral-700">
                    <flux:button
                        variant="primary"
                        wire:click="order"
                        wire:loading.attr="disabled"
                        wire:target="order"
                        icon="shopping-cart"
                        class="w-full !py-6 !text-lg font-bold shadow-lg shadow-blue-500/20"
                    >
                        {{ __('matex::ordering.create_draft') }}
                    </flux:button>
                </div>
            </div>
        </div>
    </x-slot>
</x-matex::scan-page-layout>
