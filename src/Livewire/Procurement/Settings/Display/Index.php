<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Livewire\Procurement\Settings\Display;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Lastdino\ProcurementFlow\Support\Settings;

class Index extends Component
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

    public function render(): View
    {
        return view('procflow::livewire.procurement.settings.display.index');
    }
}
