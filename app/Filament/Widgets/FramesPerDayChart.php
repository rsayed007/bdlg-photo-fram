<?php

namespace App\Filament\Widgets;

use App\Models\FrameGeneration;
use Filament\Widgets\ChartWidget;

class FramesPerDayChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Frames generated per day (30 days)';

    protected ?string $maxHeight = '260px';

    protected function getData(): array
    {
        $from = now('Asia/Dhaka')->subDays(29)->startOfDay();

        $counts = FrameGeneration::where('created_at', '>=', $from->clone()->utc())
            ->get(['created_at'])
            ->groupBy(fn (FrameGeneration $g) => $g->created_at->clone()->setTimezone('Asia/Dhaka')->toDateString())
            ->map->count();

        $labels = [];
        $values = [];
        for ($d = $from->clone(); $d->lte(now('Asia/Dhaka')); $d->addDay()) {
            $labels[] = $d->format('d M');
            $values[] = $counts[$d->toDateString()] ?? 0;
        }

        return [
            'datasets' => [[
                'label' => 'Frames',
                'data' => $values,
                'borderColor' => '#2a78d6',
                'backgroundColor' => 'rgba(42, 120, 214, 0.15)',
                'fill' => true,
                'tension' => 0.3,
                'pointRadius' => 0,
                'borderWidth' => 2,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
