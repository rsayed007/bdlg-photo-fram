<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\LocalGuides\LocalGuideResource;
use App\Models\LocalGuide;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class TopGuides extends TableWidget
{
    protected static ?int $sort = 3;

    protected static ?string $heading = 'Top 10 by points';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => LocalGuide::listed()->orderByDesc('points')->limit(10))
            ->paginated(false)
            ->recordUrl(fn (LocalGuide $record) => LocalGuideResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('rank')->label('#')->rowIndex(),
                ImageColumn::make('avatar_url')->label('')->circular()->imageSize(28),
                TextColumn::make('name')->placeholder('—'),
                TextColumn::make('level'),
                TextColumn::make('points')->numeric(),
                TextColumn::make('reviews')->numeric(),
                TextColumn::make('photo_views')->label('Views')->numeric(),
            ]);
    }
}
