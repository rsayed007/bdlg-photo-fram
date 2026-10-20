<?php

namespace App\Enums;

enum GuideStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Restricted = 'restricted';
    case Failed = 'failed';
    /** Admin "Mark inactive": excluded from the daily sync and public lists. */
    case Inactive = 'inactive';

    /** @return list<self> */
    public static function syncable(): array
    {
        return [self::Pending, self::Active, self::Restricted];
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Pending => 'gray',
            self::Restricted => 'warning',
            self::Failed => 'danger',
            self::Inactive => 'gray',
        };
    }
}
