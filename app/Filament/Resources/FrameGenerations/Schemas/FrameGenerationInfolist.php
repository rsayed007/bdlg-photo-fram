<?php

namespace App\Filament\Resources\FrameGenerations\Schemas;

use App\Models\FrameGeneration;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FrameGenerationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Image')
                    ->columnSpan(1)
                    ->schema([
                        ImageEntry::make('image')
                            ->hiddenLabel()
                            ->state(fn (FrameGeneration $record) => $record->imageUrl())
                            ->imageHeight(480),
                    ]),
                Section::make('Details')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('id')->label('ULID')->copyable(),
                        TextEntry::make('share')
                            ->state(fn (FrameGeneration $record) => $record->shareUrl())
                            ->url(fn (FrameGeneration $record) => $record->shareUrl(), shouldOpenInNewTab: true),
                        TextEntry::make('frame.title'),
                        TextEntry::make('localGuide.name')->label('Guide')->placeholder('—'),
                        TextEntry::make('user.email')->label('Member')->placeholder('—'),
                        TextEntry::make('downloads'),
                        TextEntry::make('is_featured')->label('On home page')->badge()->formatStateUsing(fn (bool $state) => $state ? 'Yes' : 'No'),
                        TextEntry::make('created_at')->dateTime(),
                        KeyValueEntry::make('stats_snapshot')
                            ->label('Stats used at render time')
                            ->state(fn (FrameGeneration $record) => collect($record->stats_snapshot)->map(fn ($v) => $v === null ? '—' : (string) $v)->all())
                            ->columnSpanFull(),
                        TextEntry::make('source_photo_paths')->label('Original uploads')->listWithLineBreaks()->placeholder('purged / none')->columnSpanFull(),
                    ]),
            ]);
    }
}
