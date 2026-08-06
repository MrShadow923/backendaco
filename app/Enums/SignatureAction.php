<?php

namespace App\Enums;

enum SignatureAction: string
{
    case Signed = 'signed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Signed => 'Signed',
            self::Rejected => 'Rejected',
        };
    }
}
