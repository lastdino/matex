<?php

use Illuminate\Contracts\View\View;
use Lastdino\ProcurementFlow\Support\Settings;
use Livewire\Component;

new class extends Component
{
    /** @var array<string,int> */
    public array $decimals = [
        'qty' => 0,
        'unit_price' => 0,
        'unit_price_materials' => 2,
        'line_total' => 0,
        'subtotal' => 0,
        'tax' => 0,
        'total' => 0,
        'percent' => 1,
    ];

    public string $currencySymbol = '¥';

    public string $currencyPosition = 'prefix';

    public bool $currencySpace = false;

    public function mount(): void
    {
        $this->decimals = Settings::displayDecimals();
        $cur = Settings::displayCurrency();
        $this->currencySymbol = (string) $cur['symbol'];
        $this->currencyPosition = (string) $cur['position'];
        $this->currencySpace = (bool) $cur['space'];
    }

    public function save(): void
    {
        $this->validate([
            'decimals' => ['required', 'array'],
            'decimals.qty' => ['required', 'integer', 'min:0', 'max:6'],
            'decimals.unit_price' => ['required', 'integer', 'min:0', 'max:6'],
            'decimals.unit_price_materials' => ['required', 'integer', 'min:0', 'max:6'],
            'decimals.line_total' => ['required', 'integer', 'min:0', 'max:6'],
            'decimals.subtotal' => ['required', 'integer', 'min:0', 'max:6'],
            'decimals.tax' => ['required', 'integer', 'min:0', 'max:6'],
            'decimals.total' => ['required', 'integer', 'min:0', 'max:6'],
            'decimals.percent' => ['required', 'integer', 'min:0', 'max:6'],

            'currencySymbol' => ['required', 'string', 'max:4'],
            'currencyPosition' => ['required', 'string', 'in:prefix,suffix'],
            'currencySpace' => ['boolean'],
        ]);

        Settings::saveDisplayDecimals($this->decimals);
        Settings::saveDisplayCurrency([
            'symbol' => $this->currencySymbol,
            'position' => $this->currencyPosition,
            'space' => $this->currencySpace,
        ]);

        $this->dispatch('notify', text: __('procflow::settings.display.flash.saved'));
    }
};

?>

<div class="p-6 space-y-6">
    <x-procflow::topmenu />
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">{{ __('procflow::settings.display.title') }}</h1>
        <a href="{{ route('procurement.dashboard') }}" class="text-blue-600 hover:underline">{{ __('procflow::settings.display.back') }}</a>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <div class="rounded border p-4 space-y-4">
            <flux:heading size="sm">{{ __('procflow::settings.display.decimals.heading') }}</flux:heading>
            <div class="grid md:grid-cols-2 gap-3">
                <flux:input type="number" min="0" max="6" wire:model="decimals.qty" label="{{ __('procflow::settings.display.decimals.qty') }}" />
                <flux:input type="number" min="0" max="6" wire:model="decimals.unit_price" label="{{ __('procflow::settings.display.decimals.unit_price') }}" />
                <flux:input type="number" min="0" max="6" wire:model="decimals.unit_price_materials" label="{{ __('procflow::settings.display.decimals.unit_price_materials') }}" />
                <flux:input type="number" min="0" max="6" wire:model="decimals.line_total" label="{{ __('procflow::settings.display.decimals.line_total') }}" />
                <flux:input type="number" min="0" max="6" wire:model="decimals.subtotal" label="{{ __('procflow::settings.display.decimals.subtotal') }}" />
                <flux:input type="number" min="0" max="6" wire:model="decimals.tax" label="{{ __('procflow::settings.display.decimals.tax') }}" />
                <flux:input type="number" min="0" max="6" wire:model="decimals.total" label="{{ __('procflow::settings.display.decimals.total') }}" />
                <flux:input type="number" min="0" max="6" wire:model="decimals.percent" label="{{ __('procflow::settings.display.decimals.percent') }}" />
            </div>
        </div>

        <div class="rounded border p-4 space-y-4">
            <flux:heading size="sm">{{ __('procflow::settings.display.currency.heading') }}</flux:heading>
            <div class="grid md:grid-cols-2 gap-3">
                <flux:input wire:model="currencySymbol" label="{{ __('procflow::settings.display.currency.symbol') }}" />
                <flux:select wire:model="currencyPosition" label="{{ __('procflow::settings.display.currency.position') }}">
                    <option value="prefix">{{ __('procflow::settings.display.currency.prefix') }}</option>
                    <option value="suffix">{{ __('procflow::settings.display.currency.suffix') }}</option>
                </flux:select>
                <div class="md:col-span-2">
                    <label class="text-sm">{{ __('procflow::settings.display.currency.space') }}</label>
                    <div class="mt-2">
                        <input id="cur_space" type="checkbox" class="size-4" wire:model="currencySpace" />
                        <label for="cur_space" class="ml-2 text-sm text-neutral-700 dark:text-neutral-300">{{ __('procflow::settings.display.currency.space_hint') }}</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="flex items-center justify-end gap-2">
        <flux:button variant="primary" wire:click="save" wire:loading.attr="disabled">{{ __('procflow::settings.display.buttons.save') }}</flux:button>
    </div>
</div>
