<?php

namespace App\Filament\Resources\LocalGuides\Pages;

use App\Filament\Resources\LocalGuides\GuideActions;
use App\Filament\Resources\LocalGuides\LocalGuideResource;
use Filament\Resources\Pages\ViewRecord;

class ViewLocalGuide extends ViewRecord
{
    protected static string $resource = LocalGuideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            GuideActions::syncNow(),
            GuideActions::overrideStats(),
            GuideActions::toggleActive(),
        ];
    }
}
