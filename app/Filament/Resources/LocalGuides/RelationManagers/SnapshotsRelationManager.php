<?php

namespace App\Filament\Resources\LocalGuides\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SnapshotsRelationManager extends RelationManager
{
    protected static string $relationship = 'snapshots';

    protected static ?string $title = 'Daily snapshots';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('snap_date')
            ->defaultSort('snap_date', 'desc')
            ->columns([
                TextColumn::make('snap_date')->date()->sortable(),
                TextColumn::make('level')->placeholder('—'),
                TextColumn::make('points')->numeric()->placeholder('—'),
                TextColumn::make('reviews')->numeric()->placeholder('—'),
                TextColumn::make('photos')->numeric()->placeholder('—'),
                TextColumn::make('videos')->numeric()->placeholder('—'),
                TextColumn::make('photo_views')->numeric()->placeholder('—'),
            ])
            ->paginated([10, 30, 90]);
    }
}
