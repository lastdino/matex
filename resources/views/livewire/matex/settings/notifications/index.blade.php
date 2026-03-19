<?php

use Lastdino\Matex\Support\Settings;
use Livewire\Component;

new class extends Component
{
    public array $notification = [
        'accounting_email' => '',
        'accounting_name' => '',
        'enable_receiving_notification' => false,
        'enable_requester_receiving_notification' => false,
    ];

    public function mount(): void
    {
        $this->notification = Settings::notification();
    }

    public function save(): void
    {
        $this->validate([
            'notification.accounting_email' => ['nullable', 'email'],
            'notification.accounting_name' => ['nullable', 'string', 'max:255'],
            'notification.enable_receiving_notification' => ['boolean'],
            'notification.enable_requester_receiving_notification' => ['boolean'],
        ]);

        Settings::saveNotification($this->notification);

        $this->dispatch('notify', text: __('matex::settings.notifications.flash.saved'));
    }
};

?>

<div class="p-6 space-y-6">
    <x-matex::topmenu />
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">{{ __('matex::settings.notifications.title') }}</h1>
        <a href="{{ route('matex.dashboard') }}" class="text-blue-600 hover:underline">{{ __('matex::settings.notifications.back') }}</a>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <div class="rounded border p-4 space-y-4">
            <flux:heading size="sm">{{ __('matex::settings.notifications.receiving.heading') }}</flux:heading>
            <div class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="notification.accounting_email" label="{{ __('matex::settings.notifications.receiving.accounting_email') }}" placeholder="{{ __('matex::settings.notifications.receiving.email_placeholder') }}" />
                    <flux:input wire:model="notification.accounting_name" label="{{ __('matex::settings.notifications.receiving.accounting_name') }}" placeholder="{{ __('matex::settings.notifications.receiving.name_placeholder') }}" />
                </div>
                <flux:checkbox wire:model="notification.enable_receiving_notification" label="{{ __('matex::settings.notifications.receiving.enable') }}" />
                <flux:checkbox wire:model="notification.enable_requester_receiving_notification" label="{{ __('matex::settings.notifications.receiving.enable_requester') }}" />
            </div>
        </div>
    </div>

    <div class="flex items-center justify-end gap-2">
        <flux:button variant="primary" wire:click="save" wire:loading.attr="disabled">{{ __('matex::settings.notifications.save') }}</flux:button>
    </div>
</div>
