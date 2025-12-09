<div>
    <div class="flex items-center justify-between gap-4 print:hidden">
        <h1 class="text-lg font-semibold">{{ __('procflow::menu.app_title') }}</h1>

        <div class="flex items-center gap-2">
            <div>
                <flux:navbar>
                    <flux:navbar.item href="{{ route('procurement.dashboard') }}">{{ __('procflow::menu.dashboard') }}</flux:navbar.item>
                    <flux:navbar.item href="{{ route('procurement.purchase-orders.index') }}">{{ __('procflow::menu.purchase_orders') }}</flux:navbar.item>
                    <flux:navbar.item href="{{ route('procurement.receiving.scan') }}">{{ __('procflow::menu.receiving') }}</flux:navbar.item>
                    <flux:navbar.item href="{{ route('procurement.ordering.scan') }}">{{ __('procflow::menu.quick_ordering') }}</flux:navbar.item>
                    <flux:navbar.item href="{{ route('procurement.pending-receiving.index') }}">{{ __('procflow::menu.pending_receiving') }}</flux:navbar.item>
                    <flux:navbar.item href="{{ route('procurement.materials.index') }}">{{ __('procflow::menu.materials') }}</flux:navbar.item>
                    <flux:navbar.item href="{{ route('procurement.suppliers.index') }}">{{ __('procflow::menu.suppliers') }}</flux:navbar.item>

                    <flux:dropdown>
                        <flux:navbar.item class="max-lg:hidden" square icon="cog-6-tooth" href="#" label="{{ __('procflow::menu.settings') }}" />
                        <flux:menu>
                            <flux:menu.item href="{{ route('procurement.settings.options') }}">{{ __('procflow::menu.options') }}</flux:menu.item>
                            <flux:menu.item href="{{ route('procurement.settings.approval') }}">{{ __('procflow::menu.approval_flow') }}</flux:menu.item>
                            <flux:menu.item href="{{ route('procurement.settings.taxes') }}">{{ __('procflow::menu.taxes') }}</flux:menu.item>
                            <flux:menu.item href="{{ route('procurement.settings.categories') }}">{{ __('procflow::menu.categories') }}</flux:menu.item>
                            <flux:menu.item href="{{ route('procurement.settings.pdf') }}">{{ __('procflow::menu.pdf') }}</flux:menu.item>
                            <flux:menu.separator />
                        </flux:menu>
                    </flux:dropdown>
                </flux:navbar>
            </div>
        </div>
    </div>
</div>
