<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Livewire\Procurement\Settings\Tokens;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Validation\Rule;
use Lastdino\ProcurementFlow\Models\Material;
use Lastdino\ProcurementFlow\Models\OrderingToken;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public ?int $materialId = null;

    public ?bool $enabled = null;

    public bool $showForm = false;

    public ?int $editingId = null;

    /**
     * @var array{token:string,material_id:int|null,unit_purchase:string|null,default_qty:float|int|string|null,enabled:bool,expires_at:string|null}
     */
    public array $form = [
        'token' => '',
        'material_id' => null,
        'unit_purchase' => null,
        'default_qty' => null,
        'enabled' => true,
        'expires_at' => null,
    ];

    protected function rules(): array
    {
        $unique = Rule::unique((new OrderingToken)->getTable(), 'token');
        if ($this->editingId) {
            $unique = $unique->ignore($this->editingId);
        }

        return [
            'form.token' => ['required', 'string', 'max:191', $unique],
            'form.material_id' => ['required', 'integer', 'exists:'.(new Material)->getTable().',id'],
            'form.unit_purchase' => ['nullable', 'string', 'max:64'],
            'form.default_qty' => ['nullable', 'numeric', 'gt:0'],
            'form.enabled' => ['required', 'boolean'],
            'form.expires_at' => ['nullable', 'date'],
        ];
    }

    public function creating(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $ot = OrderingToken::findOrFail($id);
        $this->editingId = $ot->id;
        $this->form = [
            'token' => (string) $ot->token,
            'material_id' => (int) $ot->material_id,
            'unit_purchase' => $ot->unit_purchase,
            'default_qty' => $ot->default_qty,
            'enabled' => (bool) $ot->enabled,
            'expires_at' => optional($ot->expires_at)->format('Y-m-d\TH:i'),
        ];
        $this->showForm = true;
    }

    public function save(): void
    {
        $validated = $this->validate();
        $payload = $validated['form'];

        // Normalize expires_at
        if (! empty($payload['expires_at'])) {
            $payload['expires_at'] = \Carbon\Carbon::parse((string) $payload['expires_at']);
        }

        if ($this->editingId) {
            $ot = OrderingToken::findOrFail($this->editingId);
            $ot->update($payload);
        } else {
            OrderingToken::create($payload);
        }
        $this->showForm = false;
        $this->dispatch('saved');
    }

    public function toggle(int $id): void
    {
        $ot = OrderingToken::findOrFail($id);
        $ot->enabled = ! (bool) $ot->enabled;
        $ot->save();
    }

    public function delete(int $id): void
    {
        OrderingToken::whereKey($id)->delete();
    }

    public function resetForm(): void
    {
        $this->form = [
            'token' => '',
            'material_id' => null,
            'unit_purchase' => null,
            'default_qty' => null,
            'enabled' => true,
            'expires_at' => null,
        ];
    }

    public function getRowsProperty(): LengthAwarePaginator
    {
        $q = OrderingToken::query()->with(['material']);
        if ($this->search !== '') {
            $s = $this->search;
            $q->where(function ($qq) use ($s) {
                $qq->where('token', 'like', "%{$s}%")
                    ->orWhereHas('material', function ($mq) use ($s) {
                        $mq->where('name', 'like', "%{$s}%")
                            ->orWhere('sku', 'like', "%{$s}%");
                    });
            });
        }
        if (! is_null($this->materialId)) {
            $q->where('material_id', $this->materialId);
        }
        if (! is_null($this->enabled)) {
            $q->where('enabled', $this->enabled);
        }

        return $q->orderByDesc('id')->paginate(15);
    }

    public function render(): ViewContract
    {
        $materials = Material::query()->orderBy('name')->limit(200)->get(['id', 'name', 'sku']);

        return view('procflow::livewire.procurement.settings.tokens.index', [
            'materials' => $materials,
            'rows' => $this->rows,
        ]);
    }
}
