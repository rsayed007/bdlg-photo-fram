<?php

namespace App\Filament\Resources\LocalGuides\RelationManagers;

use App\Filament\Resources\SyncLogs\SyncLogResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class SyncLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'syncLogs';

    protected static ?string $title = 'Sync logs';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return SyncLogResource::table($table)->modifyQueryUsing(fn ($q) => $q->latest('id'));
    }
}
