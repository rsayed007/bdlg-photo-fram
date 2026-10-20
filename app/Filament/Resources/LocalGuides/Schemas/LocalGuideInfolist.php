<?php

namespace App\Filament\Resources\LocalGuides\Schemas;

use App\Models\LocalGuide;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LocalGuideInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Profile')
                    ->columnSpan(1)
                    ->schema([
                        ImageEntry::make('avatar_url')->hiddenLabel()->circular()->imageSize(72)->placeholder('No avatar'),
                        TextEntry::make('name')->placeholder('—')->weight('bold'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('contrib_id')->label('Contributor ID')->copyable(),
                        TextEntry::make('profile_url')
                            ->label('Google Maps')
                            ->url(fn (LocalGuide $record) => $record->profile_url, shouldOpenInNewTab: true)
                            ->formatStateUsing(fn () => 'Open profile ↗'),
                        TextEntry::make('public')
                            ->label('Public page')
                            ->state(fn (LocalGuide $record) => route('guides.show', $record))
                            ->url(fn (LocalGuide $record) => route('guides.show', $record), shouldOpenInNewTab: true),
                        TextEntry::make('user.name')->label('Claimed by')->placeholder('—'),
                    ]),
                Section::make('Stats')
                    ->columnSpan(2)
                    ->schema([
                        Grid::make(4)->schema(array_map(
                            fn (string $f) => TextEntry::make($f)->numeric()->placeholder('—'),
                            LocalGuide::STAT_FIELDS,
                        )),
                        Grid::make(3)->schema([
                            TextEntry::make('last_synced_at')->label('Last successful sync')->since()->dateTimeTooltip()->placeholder('never'),
                            TextEntry::make('last_attempt_at')->label('Last attempt')->since()->dateTimeTooltip()->placeholder('never'),
                            TextEntry::make('fail_count')->label('Consecutive failures'),
                        ]),
                    ]),
            ]);
    }
}
