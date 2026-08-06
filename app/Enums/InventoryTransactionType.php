<?php

namespace App\Enums;

enum InventoryTransactionType: string
{
    case Received = 'received';
    case Issued = 'issued';
    case Adjustment = 'adjustment';
}
