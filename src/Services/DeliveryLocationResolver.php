<?php

declare(strict_types=1);

namespace Lastdino\Matex\Services;

use Lastdino\Matex\Support\Settings;

final class DeliveryLocationResolver
{
    public function resolve(?string $input): string
    {
        $value = (string) ($input ?? '');
        if ($value !== '') {
            return $value;
        }

        return (string) (Settings::pdf()['delivery_location'] ?? '');
    }
}
