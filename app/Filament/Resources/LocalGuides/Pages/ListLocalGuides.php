<?php

namespace App\Filament\Resources\LocalGuides\Pages;

use App\Enums\GuideStatus;
use App\Filament\Resources\LocalGuides\GuideActions;
use App\Filament\Resources\LocalGuides\LocalGuideResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListLocalGuides extends ListRecords
{
    protected static string $resource = LocalGuideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            GuideActions::addByUrl(),
        ];
    }

    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make('All')];
        foreach (GuideStatus::cases() as $status) {
            $tabs[$status->value] = Tab::make($status->label())
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', $status));
        }

        return $tabs;
    }
}
