<?php

use Lastdino\Matex\Models\Supplier;
use Lastdino\Matex\Models\SupplierContact;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $q = '';

    public int $perPage = 25;

    // Modal state for create/edit supplier
    public bool $showSupplierModal = false;

    public ?int $editingSupplierId = null;

    /** @var array{name:?string,email:?string,email_cc:?string,phone:?string,address:?string,contact_person_name:?string,is_active:bool,auto_send_po:bool} */
    public array $supplierForm = [
        'name' => null,
        'email' => null,
        'email_cc' => null,
        'phone' => null,
        'address' => null,
        'contact_person_name' => null,
        'is_active' => true,
        'auto_send_po' => false,
    ];

    // Contact management
    public bool $showContactModal = false;

    public ?int $editingContactId = null;

    /** @var array{supplier_id:?int,department:?string,name:?string,email:?string,email_cc:?string,phone:?string,address:?string,is_active:bool,is_primary:bool} */
    public array $contactForm = [
        'supplier_id' => null,
        'department' => null,
        'name' => null,
        'email' => null,
        'email_cc' => null,
        'phone' => null,
        'address' => null,
        'is_active' => true,
        'is_primary' => false,
    ];

    // Detail modal state
    public bool $showSupplierDetailModal = false;

    public ?int $selectedSupplierId = null;

    public ?array $supplierDetail = null;

    // Delete confirmation state
    public bool $showDeleteConfirm = false;

    public ?int $deletingSupplierId = null;

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getSuppliersProperty()
    {
        $q = (string) $this->q;

        return Supplier::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%");
                });
            })
            ->orderBy('name')
            ->paginate($this->perPage);
    }

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function openCreateSupplier(): void
    {
        $this->resetSupplierForm();
        $this->editingSupplierId = null;
        $this->showSupplierModal = true;
    }

    public function openEditSupplier(int $id): void
    {
        /** @var Supplier $s */
        $s = Supplier::query()->findOrFail($id);
        $this->editingSupplierId = $s->id;
        $this->supplierForm = [
            'name' => $s->name,
            'email' => $s->email,
            'email_cc' => $s->email_cc,
            'phone' => $s->phone,
            'address' => $s->address,
            'contact_person_name' => $s->contact_person_name,
            'is_active' => (bool) $s->is_active,
            'auto_send_po' => (bool) $s->auto_send_po,
        ];
        $this->showSupplierModal = true;
    }

    public function closeSupplierModal(): void
    {
        $this->showSupplierModal = false;
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingSupplierId = $id;
        $this->showDeleteConfirm = true;
    }

    public function cancelDelete(): void
    {
        $this->showDeleteConfirm = false;
        $this->deletingSupplierId = null;
    }

    public function openCreateContact(int $supplierId): void
    {
        $this->resetContactForm();
        $this->contactForm['supplier_id'] = $supplierId;
        $this->editingContactId = null;
        $this->showContactModal = true;
    }

    public function openEditContact(int $id): void
    {
        /** @var SupplierContact $c */
        $c = SupplierContact::query()->findOrFail($id);
        $this->editingContactId = $c->id;
        $this->contactForm = [
            'supplier_id' => $c->supplier_id,
            'department' => $c->department,
            'name' => $c->name,
            'email' => $c->email,
            'email_cc' => $c->email_cc,
            'phone' => $c->phone,
            'address' => $c->address,
            'is_active' => (bool) $c->is_active,
            'is_primary' => (bool) $c->is_primary,
        ];
        $this->showContactModal = true;
    }

    public function closeContactModal(): void
    {
        $this->showContactModal = false;
    }

    protected function contactRules(): array
    {
        return [
            'contactForm.supplier_id' => ['required', 'integer'],
            'contactForm.department' => ['nullable', 'string', 'max:255'],
            'contactForm.name' => ['required', 'string', 'max:255'],
            'contactForm.email' => ['nullable', 'email', 'max:255'],
            'contactForm.email_cc' => ['nullable', 'string', 'max:1000'],
            'contactForm.phone' => ['nullable', 'string', 'max:255'],
            'contactForm.address' => ['nullable', 'string'],
            'contactForm.is_active' => ['boolean'],
            'contactForm.is_primary' => ['boolean'],
        ];
    }

    public function saveContact(): void
    {
        $data = $this->validate($this->contactRules());
        $payload = $data['contactForm'];

        if ($payload['is_primary']) {
            SupplierContact::query()
                ->where('supplier_id', $payload['supplier_id'])
                ->update(['is_primary' => false]);
        }

        if ($this->editingContactId) {
            /** @var SupplierContact $c */
            $c = SupplierContact::query()->findOrFail($this->editingContactId);
            $c->update($payload);
        } else {
            SupplierContact::query()->create($payload);
        }

        $this->showContactModal = false;
        if ($this->showSupplierDetailModal) {
            $this->loadSupplierDetail();
        }
        $this->dispatch('toast', type: 'success', message: 'Contact saved');
    }

    public function deleteContact(int $id): void
    {
        /** @var SupplierContact $c */
        $c = SupplierContact::query()->findOrFail($id);
        $c->delete();
        if ($this->showSupplierDetailModal) {
            $this->loadSupplierDetail();
        }
        $this->dispatch('toast', type: 'success', message: 'Contact deleted');
    }

    protected function supplierRules(): array
    {
        return [
            'supplierForm.name' => ['required', 'string', 'max:255'],
            'supplierForm.email' => ['nullable', 'email', 'max:255'],
            'supplierForm.email_cc' => ['nullable', 'string', 'max:1000'],
            'supplierForm.phone' => ['nullable', 'string', 'max:255'],
            'supplierForm.address' => ['nullable', 'string'],
            'supplierForm.contact_person_name' => ['nullable', 'string', 'max:255'],
            'supplierForm.is_active' => ['boolean'],
            'supplierForm.auto_send_po' => ['boolean'],
        ];
    }

    public function saveSupplier(): void
    {
        $data = $this->validate($this->supplierRules());
        $payload = $data['supplierForm'];

        if ($this->editingSupplierId) {
            /** @var Supplier $s */
            $s = Supplier::query()->findOrFail($this->editingSupplierId);
            $s->update($payload);
        } else {
            Supplier::query()->create($payload);
        }

        $this->showSupplierModal = false;
        // refresh list by touching q (or rely on computed getter on next render)
        $this->dispatch('toast', type: 'success', message: 'Supplier saved');
    }

    public function deleteSupplier(): void
    {
        if (! $this->deletingSupplierId) {
            return;
        }

        /** @var Supplier $s */
        $s = Supplier::query()->findOrFail($this->deletingSupplierId);

        // Prevent deletion if related purchase orders exist
        if ($s->purchaseOrders()->exists()) {
            $this->dispatch('toast', type: 'error', message: __('matex::suppliers.delete.has_pos_error'));
            $this->cancelDelete();

            return;
        }

        $s->delete();
        $this->dispatch('toast', type: 'success', message: __('matex::suppliers.delete.deleted'));
        $this->cancelDelete();
    }

    // Detail modal helpers
    public function openSupplierDetail(int $id): void
    {
        $this->selectedSupplierId = $id;
        $this->loadSupplierDetail();
        $this->showSupplierDetailModal = true;
    }

    public function closeSupplierDetail(): void
    {
        $this->showSupplierDetailModal = false;
        $this->selectedSupplierId = null;
        $this->supplierDetail = null;
    }

    public function loadSupplierDetail(): void
    {
        if (! $this->selectedSupplierId) {
            $this->supplierDetail = null;

            return;
        }

        /** @var Supplier $model */
        $model = Supplier::query()->with(['purchaseOrders' => function ($q) {
            $q->latest('id');
        }, 'contacts'])->findOrFail($this->selectedSupplierId);

        $this->supplierDetail = $model->toArray();
    }

    protected function resetContactForm(): void
    {
        $this->contactForm = [
            'supplier_id' => null,
            'department' => null,
            'name' => null,
            'email' => null,
            'email_cc' => null,
            'phone' => null,
            'address' => null,
            'is_active' => true,
            'is_primary' => false,
        ];
    }

    protected function resetSupplierForm(): void
    {
        $this->supplierForm = [
            'name' => null,
            'email' => null,
            'email_cc' => null,
            'phone' => null,
            'address' => null,
            'contact_person_name' => null,
            'is_active' => true,
            'auto_send_po' => false,
        ];
    }
};

?>

<div class="p-6 space-y-6">
    <x-matex::topmenu />
    <h1 class="text-xl font-semibold">{{ __('matex::suppliers.title') }}</h1>

    <div class="flex items-end gap-3">
        <div class="grow max-w-96">
            <flux:input wire:model.live.debounce.300ms="q" placeholder="{{ __('matex::suppliers.search_placeholder') }}" />
        </div>
        <div>
            <flux:button variant="primary" wire:click="openCreateSupplier">{{ __('matex::suppliers.buttons.new_supplier') }}</flux:button>
        </div>
    </div>

    <div class="rounded-lg border overflow-x-auto bg-white dark:bg-neutral-900 mt-4">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('matex::suppliers.table.name') }}</flux:table.column>
                <flux:table.column>{{ __('matex::suppliers.table.contact_person') }}</flux:table.column>
                <flux:table.column>{{ __('matex::suppliers.table.email') }}</flux:column>
                <flux:table.column>{{ __('matex::suppliers.table.phone') }}</flux:table.column>
                <flux:table.column align="end">{{ __('matex::suppliers.table.actions') }}</flux:column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($this->suppliers as $s)
                    <flux:table.row>
                        <flux:table.cell>
                            <flux:link wire:click.prevent="openSupplierDetail({{ $s->id }})">
                                {{ $s->name }}
                            </flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $s->contact_person_name }}</flux:table.cell>
                        <flux:table.cell>{{ $s->email }}</flux:table.cell>
                        <flux:table.cell>{{ $s->phone }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex justify-end gap-2">
                                <flux:button size="xs" variant="outline" wire:click="openEditSupplier({{ $s->id }})">{{ __('matex::suppliers.buttons.edit') }}</flux:button>
                                <flux:button size="xs" variant="danger" wire:click="confirmDelete({{ $s->id }})">{{ __('matex::suppliers.buttons.delete') }}</flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
            <flux:table.row>
                        <flux:table.cell colspan="5" class="py-6 text-center text-neutral-500">{{ __('matex::suppliers.table.empty') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <div class="flex items-center justify-between">
        <div class="text-sm text-neutral-500">
            {{-- results summary --}}
            @php($p = $this->suppliers)
            @if ($p->total() > 0)
                <span>{{ $p->firstItem() }}–{{ $p->lastItem() }} / {{ $p->total() }}</span>
            @endif
        </div>
        <div>
            {{ $this->suppliers->links() }}
        </div>
    </div>

    {{-- Modal for create/edit supplier (Flux UI) --}}
    <flux:modal wire:model.self="showSupplierModal" name="supplier-form" class="w-full md:w-[40rem]">
        <div class="space-y-6">
            <flux:heading size="lg">{{ $editingSupplierId ? __('matex::suppliers.modal.edit_title') : __('matex::suppliers.modal.new_title') }}</flux:heading>

            <div class="space-y-4">
                <flux:input wire:model.live="supplierForm.name" label="{{ __('matex::suppliers.form.name') }}" />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:input type="email" wire:model.live="supplierForm.email" label="{{ __('matex::suppliers.form.email') }}" />
                </div>

                <flux:input wire:model.live="supplierForm.email_cc" label="{{ __('matex::suppliers.form.email_cc') }}" placeholder="{{ __('matex::suppliers.form.email_cc_placeholder') }}" />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:input wire:model.live="supplierForm.contact_person_name" label="{{ __('matex::suppliers.form.contact_person') }}" />
                    <flux:input wire:model.live="supplierForm.phone" label="{{ __('matex::suppliers.form.phone') }}" />
                </div>

                <flux:textarea wire:model.live="supplierForm.address" label="{{ __('matex::suppliers.form.address') }}" rows="3" />

                <div class="flex flex-col gap-2">
                    <flux:checkbox wire:model.live="supplierForm.is_active" label="{{ __('matex::suppliers.form.active') }}" />
                    <flux:checkbox wire:model.live="supplierForm.auto_send_po" label="{{ __('matex::suppliers.form.auto_send_po') }}" />
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" x-on:click="$flux.modal('supplier-form').close()">{{ __('matex::suppliers.buttons.cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="saveSupplier" wire:loading.attr="disabled">
                    <span wire:loading.remove>{{ __('matex::suppliers.buttons.save') }}</span>
                    <span wire:loading>{{ __('matex::suppliers.buttons.saving') }}</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Supplier Detail Modal (Flux UI) --}}
    <flux:modal wire:model.self="showSupplierDetailModal" name="supplier-detail" class="w-full md:w-[64rem]">
        <div class="space-y-6">
            @if($supplierDetail)
                <flux:heading size="lg">{{ __('matex::suppliers.detail.title') }}</flux:heading>

                <flux:card>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <flux:field>
                            <flux:label>{{ __('matex::suppliers.form.name') }}</flux:label>
                            <div class="font-medium">{{ $supplierDetail['name'] ?? '-' }}</div>
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('matex::suppliers.form.email') }}</flux:label>
                            <div class="font-medium">{{ $supplierDetail['email'] ?? '-' }}</div>
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('matex::suppliers.form.email_cc') }}</flux:label>
                            <div class="font-medium">{{ $supplierDetail['email_cc'] ?? '-' }}</div>
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('matex::suppliers.form.auto_send_po') }}</flux:label>
                            <div class="font-medium">
                                <flux:badge :color="!empty($supplierDetail['auto_send_po']) ? 'emerald' : 'zinc'" size="sm">
                                    {{ !empty($supplierDetail['auto_send_po']) ? __('matex::suppliers.form.active_yes') : __('matex::suppliers.form.active_no') }}
                                </flux:badge>
                            </div>
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('matex::suppliers.form.phone') }}</flux:label>
                            <div class="font-medium">{{ $supplierDetail['phone'] ?? '-' }}</div>
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('matex::suppliers.form.contact_person') }}</flux:label>
                            <div class="font-medium">{{ $supplierDetail['contact_person_name'] ?? '-' }}</div>
                        </flux:field>
                        @if(!empty($supplierDetail['address']))
                        <flux:field class="md:col-span-2">
                            <flux:label>{{ __('matex::suppliers.form.address') }}</flux:label>
                            <div class="font-medium">{{ $supplierDetail['address'] }}</div>
                        </flux:field>
                        @endif
                    </div>
                </flux:card>

                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <flux:heading size="md">{{ __('matex::suppliers.contacts.title') }}</flux:heading>
                        <flux:button size="sm" variant="outline" wire:click="openCreateContact({{ $supplierDetail['id'] }})">{{ __('matex::suppliers.contacts.add') }}</flux:button>
                    </div>
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('matex::suppliers.contacts.department') }}</flux:table.column>
                            <flux:table.column>{{ __('matex::suppliers.contacts.name') }}</flux:table.column>
                            <flux:table.column>{{ __('matex::suppliers.contacts.email') }}</flux:table.column>
                            <flux:table.column>{{ __('matex::suppliers.contacts.phone') }}</flux:table.column>
                            <flux:table.column align="end">{{ __('matex::suppliers.contacts.actions') }}</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @forelse(($supplierDetail['contacts'] ?? []) as $contact)
                                <flux:table.row :key="'contact-'.$contact['id']">
                                    <flux:table.cell>
                                        {{ $contact['department'] ?? '-' }}
                                        @if($contact['is_primary'])
                                            <flux:badge size="sm" color="emerald" class="ml-2">{{ __('matex::suppliers.contacts.is_primary') }}</flux:badge>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell>{{ $contact['name'] }}</flux:table.cell>
                                    <flux:table.cell>{{ $contact['email'] ?? '-' }}</flux:table.cell>
                                    <flux:table.cell>{{ $contact['phone'] ?? '-' }}</flux:table.cell>
                                    <flux:table.cell>
                                        <div class="flex justify-end gap-2">
                                            <flux:button size="xs" variant="outline" wire:click="openEditContact({{ $contact['id'] }})">{{ __('matex::suppliers.buttons.edit') }}</flux:button>
                                            <flux:button size="xs" variant="danger" wire:click="deleteContact({{ $contact['id'] }})">{{ __('matex::suppliers.buttons.delete') }}</flux:button>
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="5" class="text-center text-neutral-500 py-6">{{ __('matex::suppliers.contacts.empty') }}</flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </div>

                <div class="space-y-4">
                    <flux:heading size="md">{{ __('matex::suppliers.detail.purchase_orders') }}</flux:heading>
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('matex::po.table.po_number') }}</flux:table.column>
                            <flux:table.column>{{ __('matex::po.table.status') }}</flux:table.column>
                            <flux:table.column>{{ __('matex::po.table.total') }}</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @forelse(($supplierDetail['purchase_orders'] ?? []) as $po)
                                <flux:table.row>
                                    <flux:table.cell>
                                        @php($poShowHref = \Illuminate\Support\Facades\Route::has('matex.purchase-orders.show') ? route('matex.purchase-orders.show', ['po' => $po['id']]) : '#')
                                        <flux:link href="{{ $poShowHref }}"
                                           wire:click.prevent="$dispatch('open-po-from-supplier', { id: {{ $po['id'] }} })">
                                            {{ $po['po_number'] ?? __('matex::po.labels.draft_with_id', ['id' => $po['id']]) }}
                                        </flux:link>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        @php($status = $po['status'] ?? 'draft')
                                        <flux:badge size="sm" inset="top bottom">{{ __('matex::po.status.' . $status) }}</flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell class="tabular-nums">{{ \Lastdino\Matex\Support\Format::moneyTotal($po['total'] ?? 0) }}</flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="3" class="text-center text-neutral-500 py-6">{{ __('matex::suppliers.detail.empty_pos') }}</flux:cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </div>
            @else
                <div class="flex items-center gap-2 text-neutral-500">
                    <flux:icon name="arrow-path" class="animate-spin" />
                    {{ __('matex::suppliers.detail.loading') }}
                </div>
            @endif

            <div class="flex justify-end">
                <flux:button variant="outline" x-on:click="$flux.modal('supplier-detail').close()">{{ __('matex::suppliers.buttons.close') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Delete Confirmation Modal --}}
    <flux:modal wire:model.self="showDeleteConfirm" name="supplier-delete" class="w-full md:w-[] max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('matex::suppliers.delete.confirm_title') }}</flux:heading>
                <flux:subheading>{{ __('matex::suppliers.delete.confirm_text') }}</flux:subheading>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="cancelDelete">{{ __('matex::suppliers.buttons.cancel') }}</flux:button>
                <flux:button variant="danger" wire:click="deleteSupplier" wire:loading.attr="disabled">
                    <span wire:loading.remove>{{ __('matex::suppliers.delete.confirm_button') }}</span>
                    <span wire:loading>{{ __('matex::suppliers.delete.deleting') }}</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Contact Form Modal --}}
    <flux:modal wire:model.self="showContactModal" name="contact-form" class="w-full md:w-[40rem]">
        <div class="space-y-6">
            <flux:heading size="lg">{{ $editingContactId ? __('matex::suppliers.buttons.edit') : __('matex::suppliers.contacts.add') }}</flux:heading>

            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:input wire:model.live="contactForm.department" label="{{ __('matex::suppliers.contacts.department') }}" />
                    <flux:input wire:model.live="contactForm.name" label="{{ __('matex::suppliers.contacts.name') }}" />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:input type="email" wire:model.live="contactForm.email" label="{{ __('matex::suppliers.contacts.email') }}" />
                    <flux:input wire:model.live="contactForm.phone" label="{{ __('matex::suppliers.contacts.phone') }}" />
                </div>

                <flux:input wire:model.live="contactForm.email_cc" label="{{ __('matex::suppliers.form.email_cc') }}" placeholder="{{ __('matex::suppliers.form.email_cc_placeholder') }}" />

                <flux:textarea wire:model.live="contactForm.address" label="{{ __('matex::suppliers.contacts.address') }}" rows="3" />

                <div class="flex flex-col gap-2">
                    <flux:checkbox wire:model.live="contactForm.is_active" label="{{ __('matex::suppliers.form.active') }}" />
                    <flux:checkbox wire:model.live="contactForm.is_primary" label="{{ __('matex::suppliers.contacts.is_primary') }}" />
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" x-on:click="$flux.modal('contact-form').close()">{{ __('matex::suppliers.buttons.cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="saveContact" wire:loading.attr="disabled">
                    <span wire:loading.remove>{{ __('matex::suppliers.buttons.save') }}</span>
                    <span wire:loading>{{ __('matex::suppliers.buttons.saving') }}</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
