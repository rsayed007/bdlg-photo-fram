<?php

namespace App\Filament\Resources\SyncLogs\Schemas;

use App\Filament\Resources\LocalGuides\LocalGuideResource;
use App\Models\SyncLog;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SyncLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(4)
            ->components([
                TextEntry::make('localGuide.name')
                    ->label('Guide')
                    ->url(fn (SyncLog $record) => LocalGuideResource::getUrl('view', ['record' => $record->local_guide_id])),
                TextEntry::make('status')->badge(),
                TextEntry::make('duration_ms')->suffix(' ms'),
                TextEntry::make('created_at')->dateTime(),
                TextEntry::make('error')->placeholder('—')->columnSpanFull(),
                Section::make('Rendered text (first 2,000 chars)')
                    ->description('What the parser saw. Use it to adjust config/localguides.php — see docs/PARSER.md.')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('raw_excerpt')
                            ->hiddenLabel()
                            ->placeholder('—')
                            ->fontFamily('mono')
                            ->extraAttributes(['style' => 'white-space: pre-wrap; font-size: 12px']),
                    ]),
            ]);
    }
}
