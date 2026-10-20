<?php

namespace App\Filament\Resources\Frames;

use App\Filament\Resources\Frames\Pages\CreateFrame;
use App\Filament\Resources\Frames\Pages\EditFrame;
use App\Filament\Resources\Frames\Pages\ListFrames;
use App\Filament\Resources\Frames\Schemas\FrameForm;
use App\Filament\Resources\Frames\Tables\FramesTable;
use App\Models\Frame;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class FrameResource extends Resource
{
    protected static ?string $model = Frame::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Frames';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return FrameForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FramesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFrames::route('/'),
            'create' => CreateFrame::route('/create'),
            'edit' => EditFrame::route('/{record}/edit'),
        ];
    }
}
