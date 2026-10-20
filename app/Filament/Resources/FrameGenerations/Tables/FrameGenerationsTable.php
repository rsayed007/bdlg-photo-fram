<?php

namespace App\Filament\Resources\FrameGenerations\Tables;

use App\Models\FrameGeneration;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class FrameGenerationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('image')
                    ->label('')
                    ->state(fn (FrameGeneration $record) => $record->imageUrl())
                    ->imageHeight(64),
                TextColumn::make('localGuide.name')->label('Guide')->placeholder('—')->searchable(),
                TextColumn::make('frame.title')->label('Frame')->limit(40),
                TextColumn::make('user.email')->label('Member')->placeholder('—')->toggleable(),
                // Admin-curated home page gallery.
                ToggleColumn::make('is_featured')->label('On home page'),
                TextColumn::make('downloads')->numeric()->sortable(),
                TextColumn::make('created_at')->since()->dateTimeTooltip()->sortable(),
                TextColumn::make('sources_purged_at')->label('Originals purged')->since()->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('frame')->relationship('frame', 'title'),
                TernaryFilter::make('is_featured')->label('On home page'),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Share page')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (FrameGeneration $record) => $record->shareUrl(), shouldOpenInNewTab: true),
                ViewAction::make(),
                // Model deleting hook removes the PNG and any originals from storage.
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
