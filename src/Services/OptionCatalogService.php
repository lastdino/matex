<?php

declare(strict_types=1);

namespace Lastdino\Matex\Services;

use Illuminate\Support\Collection;
use Lastdino\Matex\Models\Option;
use Lastdino\Matex\Models\OptionGroup;

class OptionCatalogService
{
    /**
     * @return Collection<int, OptionGroup>
     */
    public function getActiveGroups(): Collection
    {
        return OptionGroup::query()->active()->ordered()->get(['id', 'name']);
    }

    /**
     * @return array<int, array<int, array{id:int,name:string}>> group_id => options[]
     */
    public function getActiveOptionsByGroup(): array
    {
        $options = Option::query()->active()->ordered()->get(['id', 'name', 'group_id']);
        $by = [];
        foreach ($options as $opt) {
            $gid = (int) $opt->getAttribute('group_id');
            $by[$gid][] = [
                'id' => (int) $opt->getKey(),
                'name' => (string) $opt->getAttribute('name'),
            ];
        }

        return $by;
    }
}
