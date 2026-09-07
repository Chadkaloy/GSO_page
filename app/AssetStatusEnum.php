<?php

namespace App;

enum AssetStatusEnum: string
{
    case Deployed = 'Deployed';
    case InStorage = 'In Storage';
    case Maintenance = 'Maintenance';
    case Retired = 'Retired';
    case Broken = 'Broken';

    /**
     * Get a displayable version of the status name.
     */
    public function label(): string
    {
        return match ($this) {
            self::Deployed => 'Deployed',
            self::InStorage => 'In Storage',
            self::Maintenance => 'Under Maintenance',
            self::Retired => 'Retired',
            self::Broken => 'Broken',
        };
    }
}
