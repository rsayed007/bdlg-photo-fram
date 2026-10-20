<?php

namespace App\Filament\Resources\Frames\Pages;

use App\Filament\Resources\Frames\FrameResource;
use App\Models\Frame;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFrame extends EditRecord
{
    protected static string $resource = FrameResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('editor')
                ->label('Open public editor')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn (Frame $record) => route('frames.create', $record), shouldOpenInNewTab: true),
            DeleteAction::make(),
        ];
    }

    /** Stay on the page after saving so the live preview refreshes. */
    protected function getRedirectUrl(): ?string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
