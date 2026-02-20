<?php

use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Lastdino\Matex\Models\Material;
use Lastdino\Matex\Models\OrderingToken;
use Lastdino\Matex\Support\Tables;

new class extends Component
{
    public bool $show = false;
    public ?int $materialId = null;

    public array $tokenForm = [
        'token' => null,
        'material_id' => null,
        'unit_purchase' => null,
        'default_qty' => null,
        'enabled' => true,
        'expires_at' => null,
    ];

    #[On('matex:open-token')]
    public function open(int $materialId): void
    {
        $this->materialId = $materialId;
        $m = Material::query()->findOrFail($materialId);
        $this->tokenForm = [
            'token' => strtoupper(Str::random(12)),
            'material_id' => $materialId,
            'unit_purchase' => $m->unit_purchase_default,
            'default_qty' => (float) ($m->pack_size ?: 1.0),
            'enabled' => true,
            'expires_at' => null,
        ];
        $this->show = true;
    }

    protected function rules(): array
    {
        return [
            'tokenForm.token' => ['required', 'string', 'unique:'.Tables::name('ordering_tokens').',token'],
            'tokenForm.material_id' => ['required', 'exists:'.Tables::name('materials').',id'],
            'tokenForm.unit_purchase' => ['required', 'string'],
            'tokenForm.default_qty' => ['required', 'numeric', 'min:0'],
            'tokenForm.enabled' => ['boolean'],
            'tokenForm.expires_at' => ['nullable', 'date'],
        ];
    }

    public function saveToken(): void
    {
        $this->validate();
        OrderingToken::query()->create($this->tokenForm);
        $this->show = false;
        $this->dispatch('toast', type: 'success', message: 'Token issued successfully');
    }
};

?>

<div>
    <flux:modal wire:model="show" name="issue-token" class="w-full md:w-160 max-w-full space-y-4">
        <flux:heading size="sm">{{ __('matex::materials.token_modal.title') }}</flux:heading>
        <div class="space-y-3">
            <flux:input wire:model="tokenForm.token" label="{{ __('matex::materials.token_modal.token') }}" />
            <div class="grid gap-3 md:grid-cols-3">
                <flux:input wire:model="tokenForm.unit_purchase" label="{{ __('matex::materials.token_modal.unit_purchase') }}" placeholder="e.g. case" />
                <flux:input type="number" step="0.000001" min="0" wire:model="tokenForm.default_qty" label="{{ __('matex::materials.token_modal.default_qty') }}" />
                <flux:switch wire:model="tokenForm.enabled" label="{{ __('matex::materials.token_modal.enabled') }}" />
            </div>
            <flux:input type="datetime-local" wire:model="tokenForm.expires_at" label="{{ __('matex::materials.token_modal.expires_at') }}" />
        </div>
        <div class="flex justify-end gap-2">
            <flux:button variant="outline" wire:click="$set('show', false)">{{ __('matex::materials.buttons.cancel') }}</flux:button>
            <flux:button variant="primary" wire:click="saveToken">{{ __('matex::materials.token_modal.issue') }}</flux:button>
        </div>
    </flux:modal>
</div>
