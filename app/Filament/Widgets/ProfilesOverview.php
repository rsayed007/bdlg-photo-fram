<?php

namespace App\Filament\Widgets;

use App\Enums\GuideStatus;
use App\Enums\SyncStatus;
use App\Models\FrameGeneration;
use App\Models\LocalGuide;
use App\Models\SyncLog;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProfilesOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $byStatus = LocalGuide::query()->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');
        $syncs = SyncLog::where('created_at', '>=', now()->subDay())->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');
        $count = fn ($collection, $enum) => (int) ($collection[$enum->value] ?? 0);

        return [
            Stat::make('Active profiles', $count($byStatus, GuideStatus::Active))
                ->description(sprintf('%d pending · %d restricted · %d failed · %d inactive',
                    $count($byStatus, GuideStatus::Pending), $count($byStatus, GuideStatus::Restricted),
                    $count($byStatus, GuideStatus::Failed), $count($byStatus, GuideStatus::Inactive)))
                ->icon('heroicon-o-map-pin')
                ->color('success'),
            Stat::make('Syncs (24 h)', $syncs->sum())
                ->description(sprintf('%d success · %d restricted · %d failed',
                    $count($syncs, SyncStatus::Success), $count($syncs, SyncStatus::Restricted), $count($syncs, SyncStatus::Failed)))
                ->icon('heroicon-o-arrow-path')
                ->color($count($syncs, SyncStatus::Failed) > 0 ? 'warning' : 'success'),
            Stat::make('Frames generated (24 h)', FrameGeneration::where('created_at', '>=', now()->subDay())->count())
                ->description(FrameGeneration::count().' total · '.FrameGeneration::sum('downloads').' downloads')
                ->icon('heroicon-o-photo'),
        ];
    }
}
