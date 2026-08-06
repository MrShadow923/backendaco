<?php

namespace App\Enums;

enum StockReleaseStatus: string
{
    case Draft = 'draft';
    case Released = 'released';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Released => 'Released',
        };
    }
}
