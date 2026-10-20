<?php

namespace App\Filament\Resources\LocalGuides\RelationManagers;

use App\Models\StatOverride;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Audit trail of manual stat overrides. */
class StatOverridesRelationManager extends RelationManager
{
    protected static string $relationship = 'statOverrides';

    protected static ?string $title = 'Manual overrides';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('user.name')->label('By')->placeholder('—'),
                TextColumn::make('changes')
                    ->formatStateUsing(fn ($state, StatOverride $record) => collect($record->changes)
                        ->map(fn ($c, $f) => "{$f}: ".($c['from'] ?? '∅').' → '.($c['to'] ?? '∅'))
                        ->implode(', '))
                    ->wrap(),
                TextColumn::make('note')->wrap(),
            ]);
    }
}
