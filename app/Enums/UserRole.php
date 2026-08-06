<?php

namespace App\Enums;

enum UserRole: string
{
    case AssetControlOfficer = 'asset_control_officer';
    case Purchaser = 'purchaser';
    case FinanceOfficer = 'finance_officer';
    case GeneralManager = 'general_manager';

    public function label(): string
    {
        return match ($this) {
            self::AssetControlOfficer => 'Asset Control Officer',
            self::Purchaser => 'Purchaser',
            self::FinanceOfficer => 'Finance Officer',
            self::GeneralManager => 'General Manager',
        };
    }
}
