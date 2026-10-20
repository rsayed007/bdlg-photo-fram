<?php

namespace App\Filament\Resources\SyncLogs;

use App\Enums\SyncStatus;
use App\Filament\Resources\SyncLogs\Pages\ListSyncLogs;
use App\Filament\Resources\SyncLogs\Pages\ViewSyncLog;
use App\Filament\Resources\SyncLogs\Schemas\SyncLogInfolist;
use App\Models\SyncLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Read-only. raw_excerpt (first 2,000 chars of page text) helps debug the parser. */
class SyncLogResource extends Resource
{
    protected static ?string $model = SyncLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 10;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return SyncLogInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('localGuide.name')->label('Guide')->placeholder('—')->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('duration_ms')->label('Duration')->numeric()->suffix(' ms')->sortable(),
                TextColumn::make('error')->limit(80)->tooltip(fn ($record) => $record->error)->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->options(SyncStatus::class),
            ])
            ->recordActions([
                ViewAction::make()->url(fn (SyncLog $record) => self::getUrl('view', ['record' => $record])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSyncLogs::route('/'),
            'view' => ViewSyncLog::route('/{record}'),
        ];
    }
}
