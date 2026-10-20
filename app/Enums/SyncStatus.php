<?php

namespace App\Enums;

enum SyncStatus: string
{
    case Success = 'success';
    case Restricted = 'restricted';
    case Failed = 'failed';

    public function color(): string
    {
        return match ($this) {
            self::Success => 'success',
            self::Restricted => 'warning',
            self::Failed => 'danger',
        };
    }
}
