<div class="p-6 space-y-6">
    <x-procflow::topmenu />
    <h1 class="text-xl font-semibold">{{ __('procflow::materials.title') }}</h1>

    <div class="flex flex-wrap items-end gap-4">
        <div class="grow max-w-96">
            <flux:input wire:model.live.debounce.300ms="q" placeholder="{{ __('procflow::materials.filters.search_placeholder') }}" />
        </div>
        <div>
            <label class="block text-sm text-neutral-600 mb-1">{{ __('procflow::materials.filters.category') }}</label>
            <select class="w-60 border rounded p-2 bg-white dark:bg-neutral-900" wire:model.live="category_id">
                <option value="">{{ __('procflow::materials.filters.all') }}</option>
                @foreach($this->categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <flux:button variant="primary" wire:click="openCreateMaterial">{{ __('procflow::materials.buttons.new') }}</flux:button>
        </div>
    </div>

    <div class="rounded-lg border overflow-x-auto bg-white dark:bg-neutral-900">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left text-neutral-500">
                    <th class="py-2 px-3 w-28">GHS</th>
                    <th class="py-2 px-3">{{ __('procflow::materials.table.sku') }}</th>
                    <th class="py-2 px-3">{{ __('procflow::materials.table.name') }}</th>
                    <th class="py-2 px-3">{{ __('procflow::materials.filters.category') }}</th>
                    <th class="py-2 px-3">{{ __('procflow::materials.table.manufacturer') }}</th>
                    <th class="py-2 px-3">{{ __('procflow::materials.table.stock') }}</th>
                    <th class="py-2 px-3">{{ __('procflow::materials.table.safety') }}</th>
                    <th class="py-2 px-3">{{ __('procflow::materials.table.unit') }}</th>
                    <th class="py-2 px-3">{{ __('procflow::materials.table.moq') }}</th>
                    <th class="py-2 px-3">{{ __('procflow::materials.table.pack_size') }}</th>
                    <th class="py-2 px-3">{{ __('procflow::materials.table.unit_price') }}</th>
                    <th class="py-2 px-3 text-right">{{ __('procflow::materials.table.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->materials as $m)
                    @php
                        $stockValue = $m->manage_by_lot ? (float) ($m->lots_sum_qty_on_hand ?? 0) : (float) ($m->current_stock ?? 0);
                        $low = !is_null($m->safety_stock) && $stockValue < (float) $m->safety_stock;
                    @endphp
                    <tr class="border-t hover:bg-neutral-50 dark:hover:bg-neutral-800 {{ $low ? 'bg-red-50/40 dark:bg-red-950/20' : '' }}">
                        <td class="py-2 px-3">
                            @php($urls = method_exists($m, 'ghsImageUrls') ? $m->ghsImageUrls() : [])
                            @if(!empty($urls))
                                <div class="flex flex-wrap items-center gap-1">
                                    @foreach($urls as $u)
                                        <img src="{{ $u }}" alt="GHS" class="w-6 h-6 object-contain" loading="lazy">
                                    @endforeach
                                </div>
                            @else
                                <div class="w-10 h-10 bg-neutral-100 dark:bg-neutral-800 text-[10px] grid place-items-center text-neutral-400">N/A</div>
                            @endif
                        </td>
                        <td class="py-2 px-3">{{ $m->sku }}</td>
                        <td class="py-2 px-3">
                            {{ $m->name }}

                        </td>
                        <td class="py-2 px-3">
                            <div>
                                @if(!empty($m->category->name))
                                    {{ $m->category->name }}
                                @endif
                            </div>
                            <div class="flex items-center gap-1 mt-1">
                                @if($m->manage_by_lot)
                                    <flux:badge size="sm" color="purple">{{ __('procflow::materials.badges.lot') }}</flux:badge>
                                @endif
                                @php($hasSds = (bool) $m->getFirstMedia('sds'))
                                    <flux:badge size="sm" color="{{ $hasSds ? 'emerald' : 'zinc' }}">{{ $hasSds ? __('procflow::materials.sds.badge_has') : __('procflow::materials.sds.badge_none') }}</flux:badge>
                            </div>

                        </td>
                        <td class="py-2 px-3">{{ $m->manufacturer_name }}</td>
                        <td class="py-2 px-3 {{ $low ? 'text-red-600 font-medium' : '' }}">{{ $stockValue }}</td>
                        <td class="py-2 px-3">{{ (float) $m->safety_stock }}</td>
                        <td class="py-2 px-3">{{ $m->unit_stock }}</td>
                        <td class="py-2 px-3 w-40">
                            {{ is_null($m->moq) ? __('procflow::materials.table.not_set') : (string) (float) $m->moq }}
                    </td>
                <td class="py-2 px-3 w-48">
                            {{ is_null($m->pack_size) ? __('procflow::materials.table.not_set') : (string) (float) $m->pack_size }}
                </td>
                        <td class="py-2 px-3">@if(!is_null($m->unit_price)) ¥{{ number_format((float)$m->unit_price, 2) }} @endif</td>
                        <td class="py-2 px-3 text-right space-x-2">
                            <flux:dropdown>
                                <flux:button icon:trailing="chevron-down">{{ __('procflow::materials.buttons.options') }}</flux:button>
                                <flux:menu>
                                    <flux:navmenu.item href="{{ route('procurement.materials.show', ['material' => $m->id]) }}" icon="clipboard-document-check">{{ __('procflow::materials.buttons.view') }}</flux:navmenu.item>
                                    <flux:navmenu.item  href="{{ route('procurement.materials.issue', ['material' => $m->id]) }}" icon="arrow-left-start-on-rectangle">{{ __('procflow::materials.buttons.issue') }}</flux:navmenu.item>
                                    <flux:navmenu.item href="{{ route('procurement.settings.labels') }}" icon="qr-code">{{ __('procflow::materials.buttons.shelf_labels') }}</flux:navmenu.item>
                                    <flux:menu.item icon="qr-code" wire:click="openTokenModal({{ $m->id }})">{{ __('procflow::materials.buttons.issue_token') }}</flux:menu.item>
                                    @php($sds = $m->getFirstMedia('sds'))
                                    @if($sds)
                                        @php($dl = URL::temporarySignedRoute('procurement.materials.sds.download', now()->addMinutes(10), ['material' => $m->id]))
                                        <flux:menu.item icon="document-text" href="{{ $dl }}" target="_blank">{{ __('procflow::materials.sds.download') }}</flux:menu.item>
                                    @endif
                                    <flux:menu.item icon="arrow-up-tray" wire:click="openSdsModal({{ $m->id }})">{{ __('procflow::materials.sds.open_modal') }}</flux:menu.item>
                                    <flux:menu.item icon="pencil-square" variant="danger" wire:click="openEditMaterial({{ $m->id }})">{{ __('procflow::materials.buttons.edit') }}</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="py-6 text-center text-neutral-500">{{ __('procflow::materials.table.no_materials') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal for create/edit material (Flux UI) --}}
    <flux:modal wire:model.self="showMaterialModal" name="material-form">
        <div class="w-full md:w-[56rem] max-w-full">
            <h3 class="text-lg font-semibold mb-3">{{ $editingMaterialId ? __('procflow::materials.modal.material_form_title_edit') : __('procflow::materials.modal.material_form_title_new') }}</h3>

            <div class="space-y-3">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-sm text-neutral-600 mb-1">{{ __('procflow::materials.form.sku') }}</label>
                        <input class="w-full border rounded p-2 bg-white dark:bg-neutral-900" wire:model.live="materialForm.sku" />
                        @error('materialForm.sku') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm text-neutral-600 mb-1">{{ __('procflow::materials.form.name') }}</label>
                        <input class="w-full border rounded p-2 bg-white dark:bg-neutral-900" wire:model.live="materialForm.name" />
                        @error('materialForm.name') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-sm text-neutral-600 mb-1">{{ __('procflow::materials.form.unit_stock') }}</label>
                        <input class="w-full border rounded p-2 bg-white dark:bg-neutral-900" wire:model.live="materialForm.unit_stock" />
                        @error('materialForm.unit_stock') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="block text-sm text-neutral-600 mb-1">{{ __('procflow::materials.form.unit_order') }}</label>
                        <input class="w-full border rounded p-2 bg-white dark:bg-neutral-900" placeholder="{{ __('procflow::materials.form.unit_order_placeholder') }}" wire:model.live="materialForm.unit_purchase_default" />
                        @error('materialForm.unit_purchase_default') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="block text-sm text-neutral-600 mb-1">{{ __('procflow::materials.form.conversion') }}</label>
                        <input type="number" step="0.000001" class="w-full border rounded p-2 bg-white dark:bg-neutral-900" placeholder="{{ __('procflow::materials.form.conversion_placeholder') }}" wire:model.live="materialForm.conversion_factor_purchase_to_stock" />
                        @error('materialForm.conversion_factor_purchase_to_stock') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="block text-sm text-neutral-600 mb-1">{{ __('procflow::materials.form.safety_stock') }}</label>
                        <input type="number" step="0.000001" class="w-full border rounded p-2 bg-white dark:bg-neutral-900" wire:model.live="materialForm.safety_stock" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-sm text-neutral-600 mb-1">{{ __('procflow::materials.form.current_stock') }}</label>
                        <input type="number" step="0.000001" class="w-full border rounded p-2 bg-white dark:bg-neutral-900" wire:model.live="materialForm.current_stock" />
                    </div>
                    <div>
                        <label class="block text-sm text-neutral-600 mb-1">{{ __('procflow::materials.form.tax_code') }}</label>
                        <select class="w-full border rounded p-2 bg-white dark:bg-neutral-900" wire:model.live="materialForm.tax_code">
                            @foreach($this->taxCodes as $code)
                                <option value="{{ $code }}">{{ ucfirst($code) }}</option>
                            @endforeach
                        </select>
                        @error('materialForm.tax_code') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="block text-sm text-neutral-600 mb-1">{{ __('procflow::materials.form.category') }}</label>
                        <select class="w-full border rounded p-2 bg-white dark:bg-neutral-900" wire:model.live="materialForm.category_id">
                            <option value="">-</option>
                            @foreach($this->categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm text-neutral-600 mb-1">{{ __('procflow::materials.form.preferred_supplier') }}</label>
                        <select class="w-full border rounded p-2 bg-white dark:bg-neutral-900" wire:model.live="materialForm.preferred_supplier_id">
                            <option value="">-</option>
                            @foreach($this->suppliers as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                        @error('materialForm.preferred_supplier_id') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
                    </div>
                </div>

                {{-- Ordering Constraints: MOQ & Pack Size --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-sm text-neutral-600 mb-1">{{ __('procflow::materials.form.moq') }}</label>
                        <input type="number" step="0.000001" min="0" class="w-full border rounded p-2 bg-white dark:bg-neutral-900" wire:model.live="materialForm.moq" />
                        @error('materialForm.moq') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="block text-sm text-neutral-600 mb-1">{{ __('procflow::materials.form.pack_size') }}</label>
                        <input type="number" step="0.000001" min="0" class="w-full border rounded p-2 bg-white dark:bg-neutral-900" wire:model.live="materialForm.pack_size" />
                        @error('materialForm.pack_size') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-sm text-neutral-600 mb-1">{{ __('procflow::materials.form.manufacturer_name') }}</label>
                        <input class="w-full border rounded p-2 bg-white dark:bg-neutral-900" wire:model.live="materialForm.manufacturer_name" />
                    </div>
                    <div>
                        <label class="block text-sm text-neutral-600 mb-1">{{ __('procflow::materials.form.storage_location') }}</label>
                        <input class="w-full border rounded p-2 bg-white dark:bg-neutral-900" wire:model.live="materialForm.storage_location" />
                    </div>
                    <div>
                        <label class="block text-sm text-neutral-600 mb-1">{{ __('procflow::materials.form.unit_price') }}</label>
                        <input type="number" step="0.01" class="w-full border rounded p-2 bg-white dark:bg-neutral-900" wire:model.live="materialForm.unit_price" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-sm text-neutral-600 mb-1">{{ __('procflow::materials.form.applicable_regulation') }}</label>
                        <input class="w-full border rounded p-2 bg-white dark:bg-neutral-900" wire:model.live="materialForm.applicable_regulation" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm text-neutral-600 mb-1">GHS</label>
                        @php($ghsCfg = (array) (config('procurement-flow.ghs') ?? config('procurement_flow.ghs') ?? []))
                        @php($ghsMap = (array) ($ghsCfg['map'] ?? []))
                        @php($ghsKeys = array_keys($ghsMap))
                        @php($disk = (string) ($ghsCfg['disk'] ?? 'public'))
                        @php($dir = trim((string) ($ghsCfg['directory'] ?? 'ghs_labels'), '/'))
                        @php($placeholder = isset($ghsCfg['placeholder']) ? (string) $ghsCfg['placeholder'] : null)
                        @if(!empty($ghsKeys))
                            <div class="flex flex-wrap gap-2 py-2">
                                @foreach($ghsKeys as $key)
                                    @php($filename = $ghsMap[$key] ?? null)
                                    @php($imgUrl = null)
                                    @if(is_string($filename) && $filename !== '')
                                        @php($path = $dir.'/'.ltrim($filename, '/'))
                                        @if(\Illuminate\Support\Facades\Storage::disk($disk)->exists($path))
                                            @php($imgUrl = \Illuminate\Support\Facades\Storage::disk($disk)->url($path))
                                        @endif
                                    @endif
                                    @if(!$imgUrl && is_string($placeholder) && $placeholder !== '')
                                        @php($phPath = $dir.'/'.ltrim($placeholder, '/'))
                                        @if(\Illuminate\Support\Facades\Storage::disk($disk)->exists($phPath))
                                            @php($imgUrl = \Illuminate\Support\Facades\Storage::disk($disk)->url($phPath))
                                        @endif
                                    @endif
                                    <label class="inline-flex items-center gap-2 px-2 py-1 rounded border bg-white dark:bg-neutral-900">
                                        <input type="checkbox" class="h-4 w-4" value="{{ $key }}" wire:model.live="materialForm.ghs_mark_options">
                                        @if($imgUrl)
                                            <img src="{{ $imgUrl }}" alt="{{ $key }}" class="w-6 h-6 object-contain" loading="lazy">
                                        @else
                                            <span class="inline-block w-6 h-6 rounded bg-neutral-100 dark:bg-neutral-800"></span>
                                        @endif
                                        <span class="text-sm">{{ $key }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('materialForm.ghs_mark_options') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
                            @error('materialForm.ghs_mark_options.*') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
                        @else
                            <div class="text-sm text-neutral-500">No GHS keys configured.</div>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-sm text-neutral-600 mb-1">{{ __('procflow::materials.form.protective_equipment') }}</label>
                        <input class="w-full border rounded p-2 bg-white dark:bg-neutral-900" wire:model.live="materialForm.protective_equipment" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div class="md:col-span-1 flex items-center gap-2">
                        <input id="manage_by_lot" type="checkbox" class="h-4 w-4" wire:model.live="materialForm.manage_by_lot" />
                        <label for="manage_by_lot" class="text-sm text-neutral-700">{{ __('procflow::materials.form.manage_by_lot_enable') }}</label>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div class="md:col-span-1 flex items-center gap-2">
                        <input id="separate_shipping" type="checkbox" class="h-4 w-4" wire:model.live="materialForm.separate_shipping" />
                        <label for="separate_shipping" class="text-sm text-neutral-700">{{ __('procflow::materials.form.separate_shipping') }}</label>
                    </div>
                    <div class="md:col-span-1">
                        <label class="block text-sm text-neutral-600 mb-1">{{ __('procflow::materials.form.shipping_fee_per_order') }}</label>
                        <input type="number" step="0.01" min="0" class="w-full border rounded p-2 bg-white dark:bg-neutral-900 disabled:opacity-60" wire:model.live="materialForm.shipping_fee_per_order" :disabled="!$wire.materialForm.separate_shipping" />
                        <div class="text-xs text-neutral-500 mt-1">{{ __('procflow::materials.form.shipping_fee_help') }}</div>
                        @error('materialForm.shipping_fee_per_order') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            <div class="mt-4 flex items-center justify-end gap-3">
                <flux:button variant="outline" x-on:click="$flux.modal('material-form').close()">{{ __('procflow::materials.buttons.cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="saveMaterial" wire:loading.attr="disabled">
                    <span wire:loading.remove>{{ __('procflow::materials.buttons.save') }}</span>
                    <span wire:loading>{{ __('procflow::materials.buttons.saving') }}</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal for issue ordering token --}}
    <flux:modal wire:model="showTokenModal" name="issue-token">
        <div class="w-full md:w-[40rem] max-w-full space-y-4">
            <flux:heading size="sm">{{ __('procflow::materials.token_modal.title') }}</flux:heading>
            <div class="space-y-3">
                <flux:input wire:model.defer="tokenForm.token" label="{{ __('procflow::materials.token_modal.token') }}" />
                <div class="grid gap-3 md:grid-cols-3">
                    <flux:input wire:model.defer="tokenForm.unit_purchase" label="{{ __('procflow::materials.token_modal.unit_purchase') }}" placeholder="e.g. case" />
                    <flux:input type="number" step="0.000001" min="0" wire:model.defer="tokenForm.default_qty" label="{{ __('procflow::materials.token_modal.default_qty') }}" />
                    <flux:switch wire:model.defer="tokenForm.enabled" label="{{ __('procflow::materials.token_modal.enabled') }}" />
                </div>
                <flux:input type="datetime-local" wire:model.defer="tokenForm.expires_at" label="{{ __('procflow::materials.token_modal.expires_at') }}" />
            </div>
            <div class="flex justify-end gap-2">
                <flux:button variant="outline" wire:click="$set('showTokenModal', false)">{{ __('procflow::materials.buttons.cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="saveToken">{{ __('procflow::materials.token_modal.issue') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal for SDS upload --}}
    <flux:modal wire:model.self="showSdsModal" name="sds-form">
        <div class="w-full md:w-[36rem] max-w-full">
            <h3 class="text-lg font-semibold mb-3">{{ __('procflow::materials.sds.title') }}</h3>
            <div class="space-y-4">
                @if($sdsMaterialId)
                    @php($current = optional(\Lastdino\ProcurementFlow\Models\Material::find($sdsMaterialId))->getFirstMedia('sds'))
                    @if($current)
                        <div class="flex items-center justify-between p-3 rounded bg-neutral-100 dark:bg-neutral-800">
                            <div class="flex items-center gap-3">
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-600 text-white text-sm">PDF</span>
                                <div>
                                    <div class="font-medium">{{ $current->name }} ({{ $current->file_name }})</div>
                                    <div class="text-xs text-neutral-500">{{ number_format($current->size / 1024, 1) }} KB</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <a class="px-3 py-1.5 rounded bg-blue-600 text-white" href="{{ $current->getUrl() }}" target="_blank" rel="noopener">ダウンロード</a>
                                <button class="px-3 py-1.5 rounded bg-red-600 text-white" wire:click="deleteSds">削除</button>
                            </div>
                        </div>
                    @else
                        <div class="text-neutral-500 text-sm">{{ __('procflow::materials.sds.empty') }}</div>
                    @endif
                @endif

                <div>
                    <label class="block text-sm text-neutral-600 mb-1">{{ __('procflow::materials.sds.upload_label') }}</label>
                    <input type="file" wire:model="sdsUpload" accept="application/pdf" class="w-full border rounded p-2 bg-white dark:bg-neutral-900" />
                    @error('sdsUpload') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
                    <div class="mt-3 flex items-center gap-2">
                        <flux:button wire:click="uploadSds" :disabled="!$sdsUpload" variant="primary">{{ __('procflow::materials.buttons.save') }}</flux:button>
                        <flux:button variant="ghost" @click="$dispatch('close-modal', { name: 'sds-form' })">{{ __('procflow::materials.buttons.cancel') }}</flux:button>
                    </div>
                    <div wire:loading wire:target="sdsUpload" class="text-sm text-neutral-500 mt-1">{{ __('procflow::materials.buttons.processing') }}</div>
                </div>
            </div>
        </div>
    </flux:modal>
</div>
