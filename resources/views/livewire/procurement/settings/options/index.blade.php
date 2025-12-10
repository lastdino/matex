<div class="p-6 space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">{{ __('procflow::settings.options.title') }}</h1>
        <a href="{{ route('procurement.dashboard') }}" class="text-blue-600 hover:underline">{{ __('procflow::settings.options.back') }}</a>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <!-- Groups Pane -->
        <div class="rounded border p-4 space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading size="sm">{{ __('procflow::settings.options.groups.heading') }}</flux:heading>
                <flux:button size="sm" variant="primary" wire:click="openCreateGroup">{{ __('procflow::settings.options.groups.add') }}</flux:button>
            </div>

            <flux:field>
                <flux:input wire:model.live.debounce.300ms="groupSearch" placeholder="{{ __('procflow::settings.options.groups.search_placeholder') }}" />
            </flux:field>

            <div class="divide-y">
                @foreach ($groups as $g)
                    <div class="py-2 flex items-center justify-between {{ $selectedGroupId === $g->id ? 'bg-gray-50 px-2 rounded' : '' }}">
                        <button class="text-left flex-1" wire:click="selectGroup({{ $g->id }})">
                            <div class="font-medium">{{ $g->name }}</div>
                            <div class="text-xs text-gray-500">{{ __('procflow::settings.options.groups.sort') }}: {{ $g->sort_order }} @if (! $g->is_active) <span class="ml-2 text-red-500">{{ __('procflow::settings.options.groups.inactive') }}</span> @endif</div>
                        </button>
                        <div class="flex items-center gap-1">
                            <flux:button size="xs" variant="ghost" wire:click="moveGroupUp({{ $g->id }})" title="{{ __('procflow::settings.options.groups.buttons.up') }}">
                                <flux:icon name="chevron-up" />
                            </flux:button>
                            <flux:button size="xs" variant="ghost" wire:click="moveGroupDown({{ $g->id }})" title="{{ __('procflow::settings.options.groups.buttons.down') }}">
                                <flux:icon name="chevron-down" />
                            </flux:button>
                            <flux:button size="xs" variant="outline" wire:click="openEditGroup({{ $g->id }})">{{ __('procflow::settings.options.groups.buttons.edit') }}</flux:button>
                            <flux:button size="xs" variant="outline" wire:click="toggleGroup({{ $g->id }})">{{ $g->is_active ? __('procflow::settings.options.groups.buttons.disable') : __('procflow::settings.options.groups.buttons.enable') }}</flux:button>
                        </div>
                    </div>
                @endforeach
            </div>

            <div>
                {{ $groups->links() }}
            </div>
        </div>

        <!-- Options Pane -->
        <div class="rounded border p-4 space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading size="sm">{{ __('procflow::settings.options.items.heading') }} @if($selectedGroup) <span class="text-gray-500 text-xs">({{ __('procflow::settings.options.items.group_hint', ['name' => $selectedGroup->name]) }})</span> @endif</flux:heading>
                <flux:button size="sm" variant="primary" wire:click="openCreateOption" :disabled="! $selectedGroupId">{{ __('procflow::settings.options.items.add') }}</flux:button>
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <flux:input wire:model.live.debounce.300ms="optionSearch" placeholder="{{ __('procflow::settings.options.items.search_placeholder') }}" />
            </div>

            @if (! $selectedGroupId)
                <flux:callout variant="warning">{{ __('procflow::settings.options.items.select_group_warning') }}</flux:callout>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-gray-500">
                            <tr>
                                <th class="py-2">{{ __('procflow::settings.options.items.table.code') }}</th>
                                <th class="py-2">{{ __('procflow::settings.options.items.table.name') }}</th>
                                <th class="py-2">{{ __('procflow::settings.options.items.table.sort') }}</th>
                                <th class="py-2">{{ __('procflow::settings.options.items.table.status') }}</th>
                                <th class="py-2 text-right">{{ __('procflow::settings.options.items.table.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                        @foreach ($options as $o)
                            <tr>
                                <td class="py-2">{{ $o->code }}</td>
                                <td class="py-2">{{ $o->name }}</td>
                                <td class="py-2">{{ $o->sort_order }}</td>
                                <td class="py-2">
                                    @if($o->deleted_at)
                                        <span class="text-red-600">{{ __('procflow::settings.options.items.table.status_deleted') }}</span>
                                    @else
                                        <span class="{{ $o->is_active ? 'text-green-600' : 'text-red-600' }}">{{ $o->is_active ? __('procflow::settings.options.items.table.status_active') : __('procflow::settings.options.items.table.status_inactive') }}</span>
                                    @endif
                                </td>
                                <td class="py-2">
                                    <div class="flex justify-end gap-1">
                                        <flux:button size="xs" variant="ghost" wire:click="moveOptionUp({{ $o->id }})" title="{{ __('procflow::settings.options.items.buttons.up') }}">
                                            <flux:icon name="chevron-up" />
                                        </flux:button>
                                        <flux:button size="xs" variant="ghost" wire:click="moveOptionDown({{ $o->id }})" title="{{ __('procflow::settings.options.items.buttons.down') }}">
                                            <flux:icon name="chevron-down" />
                                        </flux:button>
                                        <flux:button size="xs" variant="outline" wire:click="openEditOption({{ $o->id }})">{{ __('procflow::settings.options.items.buttons.edit') }}</flux:button>
                                        <flux:button size="xs" variant="outline" wire:click="toggleOption({{ $o->id }})">{{ $o->is_active ? __('procflow::settings.options.items.buttons.disable') : __('procflow::settings.options.items.buttons.enable') }}</flux:button>
                                        @if($o->deleted_at)
                                            <flux:button size="xs" variant="outline" wire:click="restoreOption({{ $o->id }})">{{ __('procflow::settings.options.items.buttons.restore') }}</flux:button>
                                        @else
                                            <flux:button size="xs" variant="danger" wire:click="deleteOption({{ $o->id }})">{{ __('procflow::settings.options.items.buttons.delete') }}</flux:button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div>
                    {{ $options->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- Group Modal -->
    <flux:modal wire:model="showGroupModal">
        <flux:heading size="sm">{{ $groupForm['id'] ? __('procflow::settings.options.groups.modal.title_edit') : __('procflow::settings.options.groups.modal.title_create') }}</flux:heading>
        <div class="space-y-3 mt-3">
            <flux:input wire:model="groupForm.name" label="{{ __('procflow::settings.options.groups.modal.name') }}"/>
            <flux:textarea wire:model="groupForm.description" label="{{ __('procflow::settings.options.groups.modal.description') }}"></flux:textarea>
            <flux:switch wire:model="groupForm.is_active" label="{{ __('procflow::settings.options.groups.modal.active') }}"/>
            <flux:input type="number" wire:model="groupForm.sort_order" label="{{ __('procflow::settings.options.groups.modal.sort_order') }}"/>
        </div>
        <div class="mt-4 flex justify-end gap-2">
            <flux:button variant="outline" wire:click="$set('showGroupModal', false)">{{ __('procflow::settings.options.groups.modal.cancel') }}</flux:button>
            <flux:button variant="primary" wire:click="saveGroup" wire:loading.attr="disabled">{{ __('procflow::settings.options.groups.modal.save') }}</flux:button>
        </div>
    </flux:modal>

    <!-- Option Modal -->
    <flux:modal wire:model="showOptionModal">
        <flux:heading size="sm">{{ $optionForm['id'] ? __('procflow::settings.options.items.modal.title_edit') : __('procflow::settings.options.items.modal.title_create') }}</flux:heading>
        <div class="space-y-3 mt-3">
            <flux:input wire:model="optionForm.code" label="{{ __('procflow::settings.options.items.modal.code') }}"/>
            <flux:input wire:model="optionForm.name" label="{{ __('procflow::settings.options.items.modal.name') }}"/>
            <flux:textarea wire:model="optionForm.description" label="{{ __('procflow::settings.options.items.modal.description') }}"></flux:textarea>
            <flux:switch wire:model="optionForm.is_active" label="{{ __('procflow::settings.options.items.modal.active') }}"/>
            <flux:input type="number" wire:model="optionForm.sort_order" label="{{ __('procflow::settings.options.items.modal.sort_order') }}"/>
        </div>
        <div class="mt-4 flex justify-end gap-2">
            <flux:button variant="outline" wire:click="$set('showOptionModal', false)">{{ __('procflow::settings.options.items.modal.cancel') }}</flux:button>
            <flux:button variant="primary" wire:click="saveOption" wire:loading.attr="disabled">{{ __('procflow::settings.options.items.modal.save') }}</flux:button>
        </div>
    </flux:modal>
</div>
