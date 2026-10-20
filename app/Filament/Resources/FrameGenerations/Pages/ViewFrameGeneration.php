<?php

namespace App\Filament\Resources\FrameGenerations\Pages;

use App\Filament\Resources\FrameGenerations\FrameGenerationResource;
use App\Models\FrameGeneration;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;

class ViewFrameGeneration extends ViewRecord
{
    protected static string $resource = FrameGenerationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggleFeatured')
                ->label(fn (FrameGeneration $record) => $record->is_featured ? 'Remove from home page' : 'Feature on home page')
                ->icon('heroicon-o-star')
                ->color(fn (FrameGeneration $record) => $record->is_featured ? 'gray' : 'warning')
                ->action(fn (FrameGeneration $record) => $record->update(['is_featured' => ! $record->is_featured])),
            Action::make('open')
                ->label('Share page')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (FrameGeneration $record) => $record->shareUrl(), shouldOpenInNewTab: true),
            DeleteAction::make(),
        ];
    }
}
