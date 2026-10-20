<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SyncStatus: string implements HasColor, HasLabel
{
    case Success = 'success';
    case Restricted = 'restricted';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    public function getColor(): string
    {
        return $this->color();
    }

    public function color(): string
    {
        return match ($this) {
            self::Success => 'success',
            self::Restricted => 'warning',
            self::Failed => 'danger',
        };
    }
}
