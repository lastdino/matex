@props([
    'hasInfo' => false,
    'title' => '',
])

<div class="p-6 space-y-4" x-data @focus-token.window="$refs.token?.focus(); $refs.token?.select()">
    <x-matex::topmenu />

    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">{{ $title }}</h1>
        @if (isset($backLink))
            {{ $backLink }}
        @endif
    </div>

    @if (! $hasInfo)
        {{-- Scan Wait State --}}
        <div class="max-w-xl mx-auto rounded border p-8 space-y-6 text-center bg-white dark:bg-neutral-900 shadow-sm">
            @if (isset($waitTitle))
                <h2 class="text-xl font-semibold">{{ $waitTitle }}</h2>
            @endif

            <div class="flex justify-center py-4">
                {{ $waitScanner }}
            </div>

            <div class="text-sm text-neutral-500 space-y-2">
                {{ $waitDescription ?? '' }}
            </div>

            <div class="pt-4">
                {{ $waitInput }}

                @if (isset($messages))
                    <div class="mt-4">
                        {{ $messages }}
                    </div>
                @endif
            </div>
        </div>
    @else
        {{-- Scanned State --}}
        <div class="grid gap-6 md:grid-cols-3">
            {{-- Left: Info Card --}}
            <div class="md:col-span-1 space-y-4">
                <div class="rounded-xl border bg-white p-6 shadow-sm dark:bg-neutral-800 dark:border-neutral-700">
                    <div class="flex items-center justify-between mb-4">
                        <flux:heading size="lg">{{ $infoTitle ?? __('matex::receiving.info') }}</flux:heading>
                        <flux:button variant="ghost" size="sm" wire:click="resetScan" icon="x-mark">キャンセル</flux:button>
                    </div>

                    {{ $infoCard }}
                </div>

                @if (isset($messages))
                    {{ $messages }}
                @endif
            </div>

            {{-- Right: Action Form --}}
            <div class="md:col-span-2 space-y-6">
                {{ $actionForm }}
            </div>
        </div>
    @endif
</div>
