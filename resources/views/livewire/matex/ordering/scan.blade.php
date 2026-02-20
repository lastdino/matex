<?php

use Illuminate\Contracts\View\View as ViewContract;
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
        'preferred_supplier' => null,
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
        $ot = OrderingToken::query()->where('token', (string) $this->form['token'])->with('material.preferredSupplier')->first();
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
            'preferred_supplier' => $mat->preferredSupplier?->name,
            'unit_purchase' => $ot->unit_purchase ?? $mat->unit_purchase_default,
            'moq' => $mat->moq,
            'pack_size' => $mat->pack_size,
            'default_qty' => $ot->default_qty,
        ];

        // オプショングループ/選択肢をロード（既存PO作成と同等：Activeのみ、並び順あり）
        $this->loadActiveOptions();
        // 既存の選択をリセット
        $this->form['options'] = [];

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

<div class="p-6 space-y-4" x-data @focus-token.window="$refs.token?.focus(); $refs.token?.select()">
    <x-matex::topmenu />
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">{{ __('matex::ordering.title') }}</h1>
        <a href="{{ route('matex.purchase-orders.index') }}" class="text-blue-600 hover:underline">{{ __('matex::ordering.back') }}</a>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <div class="rounded border p-4 space-y-4">
            <flux:heading size="sm">{{ __('matex::ordering.token.title') }}</flux:heading>
            <div class="flex gap-2">
                <flux:input
                    id="token"
                    x-ref="token"
                    wire:model.live.debounce.300ms="form.token"
                    placeholder="{{ __('matex::ordering.token.placeholder') }}"
                    class="flex-1"
                />
                <livewire:matex::qr-scanner wire:model.live="form.token" />
            </div>
            <div class="flex gap-2">
                <flux:button
                    variant="outline"
                    wire:click="lookup"
                    wire:loading.attr="disabled"
                    wire:target="lookup"
                >{{ __('matex::ordering.token.lookup') }}</flux:button>
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
            <flux:heading size="sm">{{ __('matex::ordering.info.title') }}</flux:heading>

            @if ($this->hasInfo)
                <div class="text-sm text-gray-700 space-y-1">
                    <div>{{ __('matex::ordering.info.material') }}: <span class="font-medium">{{ $info['material_name'] }}</span> [<span>{{ $info['material_sku'] }}</span>]</div>
                    <div>{{ __('matex::ordering.info.supplier') }}: <span class="font-medium">{{ $info['preferred_supplier'] ?? __('matex::ordering.common.not_set') }}</span></div>
                    <div>{{ __('matex::ordering.info.unit_purchase') }}: <span class="font-medium">{{ $info['unit_purchase'] ?? '-' }}</span></div>
                    @if($info['moq'])
                        <div>{{ __('matex::ordering.info.moq') }}: <span class="font-medium">{{ $info['moq'] }}</span></div>
                    @endif
                    @if($info['pack_size'])
                        <div>{{ __('matex::ordering.info.pack_size') }}: <span class="font-medium">{{ $info['pack_size'] }}</span></div>
                    @endif
                </div>

                {{-- Options (same style as PO issuance; price impact: none) --}}
                @if (!empty($optionGroups))
                    <div class="mt-4 space-y-3">
                        <flux:heading size="xs">{{ __('matex::ordering.options.title') }}</flux:heading>
                        <div class="grid grid-cols-2 gap-4">
                            @foreach($optionGroups as $g)
                                @php $gid = $g['id']; $opts = $optionsByGroup[$gid] ?? []; @endphp
                                <flux:field>
                                    <flux:label>{{ $g['name'] }}</flux:label>
                                    <select class="w-full border rounded p-2 bg-white dark:bg-neutral-900" wire:model.defer="form.options.{{ $gid }}">
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
            @endif

            <div class="grid gap-3 md:grid-cols-3 items-end">
                <div class="md:col-span-2">
                    <flux:input type="number" step="0.000001" min="0" wire:model.number="form.qty" label="{{ __('matex::ordering.qty.label') }}"/>
                </div>
                <div class="flex gap-2">
                    <flux:button variant="outline" wire:click="decrementQty" title="-1">-</flux:button>
                    <flux:button variant="outline" wire:click="incrementQty" title="+1">+</flux:button>
                </div>
            </div>

            <div class="flex gap-2">
                <flux:button
                    variant="primary"
                    wire:click="order"
                    wire:loading.attr="disabled"
                    wire:target="order"
                >{{ __('matex::ordering.create_draft') }}</flux:button>
            </div>
        </div>
    </div>
</div>
