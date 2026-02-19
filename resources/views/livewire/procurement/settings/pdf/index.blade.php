<?php

use Illuminate\Contracts\View\View;
use Lastdino\ProcurementFlow\Support\Settings;
use Livewire\Component;

new class extends Component
{
    public string $companyName = '';

    public string $companyTel = '';

    public string $companyFax = '';

    public string $addressLines = '';

    public string $paymentTerms = '';

    public string $deliveryLocation = '';

    public string $footnotes = '';

    public function mount(): void
    {
        $pdf = Settings::pdf();
        $company = (array) ($pdf['company'] ?? []);
        $this->companyName = (string) ($company['name'] ?? '');
        $this->companyTel = (string) ($company['tel'] ?? '');
        $this->companyFax = (string) ($company['fax'] ?? '');
        $addr = (array) ($company['address_lines'] ?? []);
        $this->addressLines = implode("\n", array_map('strval', $addr));

        $this->paymentTerms = (string) ($pdf['payment_terms'] ?? '');
        $this->deliveryLocation = (string) ($pdf['delivery_location'] ?? '');
        $fn = (array) ($pdf['footnotes'] ?? []);
        $this->footnotes = implode("\n", array_map('strval', $fn));
    }

    public function save(): void
    {
        $this->validate([
            'companyName' => ['nullable', 'string', 'max:255'],
            'companyTel' => ['nullable', 'string', 'max:255'],
            'companyFax' => ['nullable', 'string', 'max:255'],
            'addressLines' => ['nullable', 'string'],
            'paymentTerms' => ['nullable', 'string'],
            'deliveryLocation' => ['nullable', 'string'],
            'footnotes' => ['nullable', 'string'],
        ]);

        $company = [
            'name' => $this->companyName,
            'tel' => $this->companyTel,
            'fax' => $this->companyFax,
            'address_lines' => $this->addressLines === '' ? [] : preg_split('/\r?\n/', $this->addressLines),
        ];

        $payload = [
            'company' => $company,
            'payment_terms' => $this->paymentTerms,
            'delivery_location' => $this->deliveryLocation,
            'footnotes' => $this->footnotes === '' ? [] : preg_split('/\r?\n/', $this->footnotes),
        ];

        Settings::savePdf($payload);
        $this->dispatch('notify', text: __('procflow::settings.pdf.flash.saved'));
    }
};

?>

<div class="p-6 space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">{{ __('procflow::settings.pdf.title') }}</h1>
        <a href="{{ route('procurement.dashboard') }}" class="text-blue-600 hover:underline">{{ __('procflow::settings.pdf.back') }}</a>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <div class="rounded border p-4 space-y-4">
            <flux:heading size="sm">{{ __('procflow::settings.pdf.company.heading') }}</flux:heading>
            <flux:input wire:model="companyName" label="{{ __('procflow::settings.pdf.company.name') }}"/>
            <flux:input wire:model="companyTel" label="{{ __('procflow::settings.pdf.company.tel') }}"/>
            <flux:input wire:model="companyFax" label="{{ __('procflow::settings.pdf.company.fax') }}"/>
            <flux:textarea rows="5" wire:model="addressLines" label="{{ __('procflow::settings.pdf.company.address') }}"></flux:textarea>
        </div>

        <div class="rounded border p-4 space-y-4">
            <flux:heading size="sm">{{ __('procflow::settings.pdf.texts.heading') }}</flux:heading>
            <flux:textarea rows="2" wire:model="paymentTerms" label="{{ __('procflow::settings.pdf.texts.payment_terms') }}"></flux:textarea>
            <flux:textarea rows="3" wire:model="deliveryLocation" label="{{ __('procflow::settings.pdf.texts.delivery_location') }}"></flux:textarea>
            <flux:textarea rows="5" wire:model="footnotes" label="{{ __('procflow::settings.pdf.texts.footnotes') }}"></flux:textarea>
        </div>
    </div>

    <div class="flex justify-end">
        <flux:button variant="primary" wire:click="save" wire:loading.attr="disabled">{{ __('procflow::settings.pdf.buttons.save') }}</flux:button>
    </div>
</div>
