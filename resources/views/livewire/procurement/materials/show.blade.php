<div class="p-6 space-y-6">
    <x-procflow::topmenu />

    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">
            {{ __('procflow::materials.show.title_prefix') }}: {{ $this->material->sku }} — {{ $this->material->name }}
            @if($this->material->manage_by_lot)
                <span class="ml-2 inline-flex items-center rounded bg-purple-100 text-purple-700 px-2 py-0.5 text-[11px]">{{ __('procflow::materials.badges.lot') }}</span>
            @endif
        </h1>
        <div class="flex gap-2">
            <a class="px-3 py-1.5 rounded bg-neutral-200 dark:bg-neutral-800" href="{{ route('procurement.materials.index') }}">{{ __('procflow::materials.show.back_to_list') }}</a>
            <a class="px-3 py-1.5 rounded bg-blue-600 text-white" href="{{ route('procurement.materials.issue', ['material' => $this->material->id]) }}">{{ __('procflow::materials.show.issue') }}</a>
        </div>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <div class="rounded border p-4 bg-white dark:bg-neutral-900">
            <h2 class="text-lg font-medium mb-3">{{ __('procflow::materials.show.lots_title') }}</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-neutral-500">
                            <th class="py-2 px-3">{{ __('procflow::materials.show.lots.lot_no') }}</th>
                            <th class="py-2 px-3">{{ __('procflow::materials.show.lots.stock') }}</th>
                            <th class="py-2 px-3">{{ __('procflow::materials.show.lots.expiry') }}</th>
                            <th class="py-2 px-3">{{ __('procflow::materials.show.lots.status') }}</th>
                            <th class="py-2 px-3">{{ __('procflow::materials.show.lots.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($this->lots as $lot)
                        @php $expired = $lot->expiry_date && \Illuminate\Support\Carbon::parse($lot->expiry_date)->isPast(); @endphp
                        <tr class="border-t {{ $expired ? 'bg-red-50/40 dark:bg-red-950/20' : '' }}">
                            <td class="py-2 px-3">{{ $lot->lot_no }}</td>
                            <td class="py-2 px-3">{{ \Lastdino\ProcurementFlow\Support\Format::qty($lot->qty_on_hand) }} {{ $lot->unit }}</td>
                            <td class="py-2 px-3">{{ $lot->expiry_date ?? '-' }}</td>
                            <td class="py-2 px-3">{{ $lot->status ?? '-' }}</td>
                            <td class="py-2 px-3">
                                <a class="inline-block text-blue-600 hover:underline"
                                   href="{{ route('procurement.materials.issue', ['material' => $this->material->id, 'lot' => $lot->id]) }}">
                                    {{ __('procflow::materials.show.issue') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-6 text-center text-neutral-500">{{ __('procflow::materials.show.lots.empty') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded border p-4 bg-white dark:bg-neutral-900">
            <h2 class="text-lg font-medium mb-3">{{ __('procflow::materials.show.movements_title') }}</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-neutral-500">
                            <th class="py-2 px-3">{{ __('procflow::materials.show.movements.occurred_at') }}</th>
                            <th class="py-2 px-3">{{ __('procflow::materials.show.movements.type') }}</th>
                            <th class="py-2 px-3">{{ __('procflow::materials.show.movements.qty') }}</th>
                            <th class="py-2 px-3">{{ __('procflow::materials.show.movements.lot') }}</th>
                            <th class="py-2 px-3">{{ __('procflow::materials.show.movements.reason') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($this->movements as $mv)
                        <tr class="border-t">
                            <td class="py-2 px-3">{{ $mv->occurred_at }}</td>
                            <td class="py-2 px-3">{{ $mv->type }}</td>
                            <td class="py-2 px-3">{{ \Lastdino\ProcurementFlow\Support\Format::qty($mv->qty_base) }} {{ $mv->unit }}</td>
                            <td class="py-2 px-3">{{ optional($mv->lot)->lot_no ?? '-' }}</td>
                            <td class="py-2 px-3">{{ $mv->reason ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-neutral-500">{{ __('procflow::materials.show.movements.empty') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
