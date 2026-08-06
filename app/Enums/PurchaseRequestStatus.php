<?php

namespace App\Enums;

enum PurchaseRequestStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case ConvertedToPO = 'converted_to_po';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::ConvertedToPO => 'Converted to PO',
            self::Cancelled => 'Cancelled',
        };
    }
}
