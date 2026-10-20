<?php

namespace App\Filament\Resources\LocalGuides;

use App\Filament\Resources\LocalGuides\Pages\ListLocalGuides;
use App\Filament\Resources\LocalGuides\Pages\ViewLocalGuide;
use App\Filament\Resources\LocalGuides\RelationManagers\SnapshotsRelationManager;
use App\Filament\Resources\LocalGuides\RelationManagers\StatOverridesRelationManager;
use App\Filament\Resources\LocalGuides\RelationManagers\SyncLogsRelationManager;
use App\Filament\Resources\LocalGuides\Schemas\LocalGuideInfolist;
use App\Filament\Resources\LocalGuides\Tables\LocalGuidesTable;
use App\Models\LocalGuide;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Profiles are added by URL (Module A) and updated by the sync engine, so
 * there is no create/edit form: admins use the actions instead.
 */
class LocalGuideResource extends Resource
{
    protected static ?string $model = LocalGuide::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'Community';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function infolist(Schema $schema): Schema
    {
        return LocalGuideInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LocalGuidesTable::configure($table);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'contrib_id'];
    }

    public static function getRelations(): array
    {
        return [
            SyncLogsRelationManager::class,
            SnapshotsRelationManager::class,
            StatOverridesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLocalGuides::route('/'),
            'view' => ViewLocalGuide::route('/{record}'),
        ];
    }
}
