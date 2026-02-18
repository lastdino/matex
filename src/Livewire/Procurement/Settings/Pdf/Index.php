<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Livewire\Procurement\Settings\Pdf;

use Illuminate\Contracts\View\View;
use Lastdino\ProcurementFlow\Support\Settings;
use Livewire\Component;

class Index extends Component
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

    public function render(): View
    {
        return view('procflow::livewire.procurement.settings.pdf.index');
    }
}
