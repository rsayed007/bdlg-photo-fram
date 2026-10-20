<?php

namespace App\Filament\Resources\Frames\Tables;

use App\Models\Frame;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FramesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                ImageColumn::make('thumbnail')
                    ->label('')
                    ->state(fn (Frame $record) => $record->assetUrl('thumbnail'))
                    ->imageHeight(64),
                TextColumn::make('title')->searchable()->weight('medium'),
                TextColumn::make('event.name')->placeholder('—')->searchable(),
                TextColumn::make('size')->state(fn (Frame $record) => "{$record->size_w}×{$record->size_h}"),
                TextColumn::make('photo_slots')->label('Photos')->state(fn (Frame $record) => count($record->config['photo_slots'] ?? [])),
                TextColumn::make('generations_count')->counts('generations')->label('Generated')->sortable(),
                ToggleColumn::make('is_active')->label('Active'),
            ])
            ->filters([
                SelectFilter::make('event')->relationship('event', 'name'),
            ])
            ->recordActions([
                Action::make('editor')
                    ->label('Public editor')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Frame $record) => route('frames.create', $record), shouldOpenInNewTab: true),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
