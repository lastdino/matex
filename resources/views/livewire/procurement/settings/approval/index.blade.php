<div>
    <div class="mx-auto w-full max-w-2xl p-6">
        <flux:heading size="xl" class="mb-6">{{ __('procflow::settings.approval.title') }}</flux:heading>

        <div class="space-y-5">
            <flux:select wire:model="purchaseOrderFlowId" placeholder="{{ __('procflow::settings.approval.select.placeholder') }}" label="{{ __('procflow::settings.approval.select.label') }}">
                @foreach($this->flows as $flow)
                    <flux:select.option :value="$flow->id">{{ $flow->name }} (ID: {{ $flow->id }})</flux:select.option>
                @endforeach
            </flux:select>
            <div class="flex gap-3">
                <flux:button wire:click="save" variant="primary">{{ __('procflow::settings.approval.buttons.save') }}</flux:button>
            </div>
        </div>
    </div>
</div>
