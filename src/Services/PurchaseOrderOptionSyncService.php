<?php

declare(strict_types=1);

namespace Lastdino\Matex\Services;

use Lastdino\Matex\Models\Option;
use Lastdino\Matex\Models\PurchaseOrderItem;
use Lastdino\Matex\Models\PurchaseOrderItemOptionValue;

class PurchaseOrderOptionSyncService
{
    /**
     * @param  array<int,int|string|null>  $selectedOptions  [group_id => option_id|custom_value]
     */
    public function syncItemOptions(PurchaseOrderItem $item, array $selectedOptions): void
    {
        foreach ($selectedOptions as $groupId => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $gid = (int) $groupId;

            $group = \Lastdino\Matex\Models\OptionGroup::find($gid);
            if (! $group || ! $group->is_active) {
                continue;
            }

            $inputType = (string) ($group->input_type ?? 'select');
            $oid = null;
            $customValue = null;

            if ($inputType === 'input') {
                $customValue = (string) $value;
            } else {
                $oid = (int) $value;
                $exists = Option::query()
                    ->active()
                    ->where('group_id', $gid)
                    ->whereKey($oid)
                    ->exists();

                if (! $exists) {
                    continue; // skip invalid
                }
            }

            PurchaseOrderItemOptionValue::query()->updateOrCreate(
                [
                    'purchase_order_item_id' => (int) $item->getKey(),
                    'group_id' => $gid,
                ],
                [
                    'option_id' => $oid,
                    'custom_value' => $customValue,
                ]
            );
        }
    }
}
