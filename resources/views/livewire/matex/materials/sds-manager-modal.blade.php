<?php

use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Lastdino\Matex\Models\Material;

new class extends Component
{
    use WithFileUploads;

    public bool $show = false;
    public ?int $materialId = null;

    #[Validate('file|mimes:pdf|max:10240')]
    public $sdsUpload;

    #[On('matex:open-sds')]
    public function open(int $materialId): void
    {
        $this->materialId = $materialId;
        $this->sdsUpload = null;
        $this->show = true;
    }

    public function uploadSds(): void
    {
        $this->validate();
        $m = Material::query()->findOrFail($this->materialId);
        if (method_exists($m, 'addMedia')) {
            $m->addMedia($this->sdsUpload->getRealPath())
              ->usingFileName($this->sdsUpload->getClientOriginalName())
              ->toMediaCollection('sds');
        }
        $this->show = false;
        $this->dispatch('matex:sds-updated');
        $this->dispatch('toast', type: 'success', message: 'SDS uploaded successfully');
    }

    public function deleteSds(): void
    {
        $m = Material::query()->findOrFail($this->materialId);
        if (method_exists($m, 'clearMediaCollection')) {
            $m->clearMediaCollection('sds');
        }
        $this->show = false;
        $this->dispatch('matex:sds-updated');
        $this->dispatch('toast', type: 'success', message: 'SDS deleted');
    }
};

?>

<div>
    <flux:modal wire:model="show" name="sds-form" class="w-full md:w-xl max-w-full">
        <h3 class="text-lg font-semibold mb-3">{{ __('matex::materials.sds.title') }}</h3>
        <div class="space-y-4">
            @if($materialId)
                @php($m = \Lastdino\Matex\Models\Material::find($materialId))
                @php($current = $m ? $m->getFirstMedia('sds') : null)
                @if($current)
                    <div class="flex items-center justify-between p-3 rounded bg-neutral-100 dark:bg-neutral-800 gap-2">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-600 text-white text-sm">PDF</span>
                            <div>
                                <div class="font-medium">{{ $current->file_name }}</div>
                                <div class="text-xs text-neutral-500">{{ number_format($current->size / 1024, 1) }} KB</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:button
                                variant="primary"
                                href="{{ \Illuminate\Support\Facades\URL::signedRoute('matex.materials.sds.download', ['material' => $materialId]) }}"
                                target="_blank" rel="noopener">ダウンロード
                            </flux:button>
                            <flux:button variant="danger" wire:click="deleteSds">削除</flux:button>
                        </div>
                    </div>
                @else
                    <div class="text-neutral-500 text-sm">{{ __('matex::materials.sds.empty') }}</div>
                @endif
            @endif

            <div>
                <label class="block text-sm text-neutral-600 mb-1">{{ __('matex::materials.sds.upload_label') }}</label>
                <input type="file" wire:model="sdsUpload" accept="application/pdf" class="w-full border rounded p-2 bg-white dark:bg-neutral-900" />
                @error('sdsUpload') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
                <div class="mt-3 flex items-center gap-2">
                    <flux:button wire:click="uploadSds" :disabled="!$sdsUpload" variant="primary">{{ __('matex::materials.buttons.save') }}</flux:button>
                    <flux:button variant="ghost" @click="$flux.modal('sds-form').close()">{{ __('matex::materials.buttons.cancel') }}</flux:button>
                </div>
                <div wire:loading wire:target="sdsUpload" class="text-sm text-neutral-500 mt-1">{{ __('matex::materials.buttons.processing') }}</div>
            </div>
        </div>
    </flux:modal>
</div>
