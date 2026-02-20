<?php

declare(strict_types=1);

namespace Lastdino\Matex\Enums;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Receiving = 'receiving';
    case Closed = 'closed';
    case Canceled = 'canceled';
}
