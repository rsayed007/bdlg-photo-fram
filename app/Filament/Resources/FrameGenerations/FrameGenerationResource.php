<?php

namespace App\Filament\Resources\FrameGenerations;

use App\Filament\Resources\FrameGenerations\Pages\ListFrameGenerations;
use App\Filament\Resources\FrameGenerations\Pages\ViewFrameGeneration;
use App\Filament\Resources\FrameGenerations\Schemas\FrameGenerationInfolist;
use App\Filament\Resources\FrameGenerations\Tables\FrameGenerationsTable;
use App\Models\FrameGeneration;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class FrameGenerationResource extends Resource
{
    protected static ?string $model = FrameGeneration::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'Frames';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Generated frames';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return FrameGenerationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FrameGenerationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFrameGenerations::route('/'),
            'view' => ViewFrameGeneration::route('/{record}'),
        ];
    }
}
