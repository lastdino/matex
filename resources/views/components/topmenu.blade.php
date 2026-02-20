<div>
    <div class="flex items-center justify-between gap-4 print:hidden">
        <h1 class="text-lg font-semibold">{{ __('matex::menu.app_title') }}</h1>

        <div class="flex items-center gap-2">
            <div>
                <flux:navbar>
                    <flux:navbar.item href="{{ route('matex.dashboard') }}">{{ __('matex::menu.dashboard') }}</flux:navbar.item>
                    <flux:navbar.item href="{{ route('matex.purchase-orders.index') }}">{{ __('matex::menu.purchase_orders') }}</flux:navbar.item>
                    <flux:navbar.item href="{{ route('matex.receiving.scan') }}">{{ __('matex::menu.receiving') }}</flux:navbar.item>
                    <flux:navbar.item href="{{ route('matex.ordering.scan') }}">{{ __('matex::menu.quick_ordering') }}</flux:navbar.item>
                    <flux:navbar.item href="{{ route('matex.pending-receiving.index') }}">{{ __('matex::menu.pending_receiving') }}</flux:navbar.item>
                    <flux:navbar.item href="{{ route('matex.materials.index') }}">{{ __('matex::menu.materials') }}</flux:navbar.item>
                    <flux:navbar.item href="{{ route('matex.suppliers.index') }}">{{ __('matex::menu.suppliers') }}</flux:navbar.item>

                    <flux:dropdown>
                        <flux:navbar.item class="max-lg:hidden" square icon="cog-6-tooth" href="#" label="{{ __('matex::menu.settings') }}" />
                        <flux:menu>
                            <flux:menu.item href="{{ route('matex.settings.options') }}">{{ __('matex::menu.options') }}</flux:menu.item>
                            <flux:menu.item href="{{ route('matex.settings.approval') }}">{{ __('matex::menu.approval_flow') }}</flux:menu.item>
                            <flux:menu.item href="{{ route('matex.settings.taxes') }}">{{ __('matex::menu.taxes') }}</flux:menu.item>
                            <flux:menu.item href="{{ route('matex.settings.display') }}">{{ __('matex::menu.display') }}</flux:menu.item>
                            <flux:menu.item href="{{ route('matex.settings.categories') }}">{{ __('matex::menu.categories') }}</flux:menu.item>
                            <flux:menu.item href="{{ route('matex.settings.storage-locations') }}">{{ __('matex::menu.storage_locations') }}</flux:menu.item>
                            <flux:menu.item href="{{ route('matex.settings.pdf') }}">{{ __('matex::menu.pdf') }}</flux:menu.item>
                            <flux:menu.separator />
                            <flux:menu.item href="{{ route('matex.settings.tokens') }}">{{ __('matex::menu.tokens') }}</flux:menu.item>
                            <flux:menu.item href="{{ route('matex.settings.labels') }}">{{ __('matex::menu.labels') }}</flux:menu.item>
                            <flux:menu.separator />
                        </flux:menu>
                    </flux:dropdown>
                </flux:navbar>
            </div>
        </div>
    </div>
</div>
