<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Livewire\Procurement\Settings\Taxes;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Lastdino\ProcurementFlow\Models\AppSetting;
use Lastdino\ProcurementFlow\Support\Settings;

class Index extends Component
{
    // Item tax
    public float $itemDefaultRate = 0.10;
    /** @var array<int,array{key:string,rate:float}> */
    public array $itemRates = [];
    public string $itemScheduleJson = '';

    // Shipping
    public bool $shippingTaxable = true;
    public float $shippingTaxRate = 0.10;

    public function mount(): void
    {
        $item = AppSetting::getArray('procurement.item_tax') ?? (array) config('procurement-flow.item_tax', []);
        $this->itemDefaultRate = (float) ($item['default_rate'] ?? 0.10);
        $rates = (array) ($item['rates'] ?? []);
        $this->itemRates = [];
        foreach ($rates as $k => $v) {
            $this->itemRates[] = ['key' => (string) $k, 'rate' => (float) $v];
        }
        $schedule = (array) ($item['schedule'] ?? []);
        $this->itemScheduleJson = $schedule ? json_encode($schedule, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '';

        $shipping = Settings::shipping();
        $this->shippingTaxable = (bool) $shipping['taxable'];
        $this->shippingTaxRate = (float) $shipping['tax_rate'];
    }

    public function addRateRow(): void
    {
        $this->itemRates[] = ['key' => '', 'rate' => 0.0];
    }

    public function removeRateRow(int $index): void
    {
        if (array_key_exists($index, $this->itemRates)) {
            unset($this->itemRates[$index]);
            $this->itemRates = array_values($this->itemRates);
        }
    }

    public function save(): void
    {
        $this->validate([
            'itemDefaultRate' => ['required', 'numeric', 'min:0'],
            'itemRates' => ['array'],
            'itemRates.*.key' => ['required', 'string', 'max:50'],
            'itemRates.*.rate' => ['required', 'numeric', 'min:0'],
            'itemScheduleJson' => ['nullable', 'string'],
            'shippingTaxable' => ['boolean'],
            'shippingTaxRate' => ['required', 'numeric', 'min:0'],
        ]);

        // Build item rates map
        $rates = [];
        foreach ($this->itemRates as $row) {
            if ($row['key'] !== '') {
                $rates[$row['key']] = (float) $row['rate'];
            }
        }

        // Decode schedule JSON if provided
        $schedule = [];
        if (trim($this->itemScheduleJson) !== '') {
            $decoded = json_decode($this->itemScheduleJson, true);
            if (! is_array($decoded)) {
                $this->addError('itemScheduleJson', __('procflow::settings.taxes.errors.invalid_json'));
                return;
            }
            $schedule = $decoded;
        }

        Settings::saveItemTax([
            'default_rate' => (float) $this->itemDefaultRate,
            'rates' => $rates,
            'schedule' => $schedule,
        ]);

        Settings::saveShipping([
            'taxable' => (bool) $this->shippingTaxable,
            'tax_rate' => (float) $this->shippingTaxRate,
        ]);

        $this->dispatch('notify', text: __('procflow::settings.taxes.flash.saved'));
    }

    public function render(): View
    {
        return view('procflow::livewire.procurement.settings.taxes.index');
    }
}
