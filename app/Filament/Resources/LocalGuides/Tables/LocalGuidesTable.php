<?php

namespace App\Filament\Resources\LocalGuides\Tables;

use App\Enums\GuideStatus;
use App\Filament\Resources\LocalGuides\GuideActions;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LocalGuidesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('points', 'desc')
            ->columns([
                ImageColumn::make('avatar_url')->label('')->circular()->imageSize(32),
                TextColumn::make('name')->searchable()->sortable()->placeholder('—')->weight('medium'),
                TextColumn::make('contrib_id')->label('Contrib ID')->searchable()->copyable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('level')->numeric()->sortable()->placeholder('—'),
                TextColumn::make('points')->numeric()->sortable()->placeholder('—'),
                TextColumn::make('reviews')->numeric()->sortable()->placeholder('—'),
                TextColumn::make('photos')->numeric()->sortable()->placeholder('—'),
                TextColumn::make('photo_views')->label('Views')->numeric()->sortable()->placeholder('—'),
                TextColumn::make('fail_count')->label('Fails')->numeric()->sortable()
                    ->color(fn (int $state) => $state > 0 ? 'danger' : null),
                TextColumn::make('user.name')->label('Claimed by')->placeholder('—')->toggleable(),
                TextColumn::make('last_synced_at')->label('Synced')->since()->sortable()->placeholder('never'),
                TextColumn::make('last_attempt_at')->label('Attempted')->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(GuideStatus::class)->multiple(),
                SelectFilter::make('level')->options(collect(range(1, 10))->mapWithKeys(fn (int $l) => [$l => "Level {$l}"])->all()),
                TernaryFilter::make('claimed')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('user_id'),
                        false: fn (Builder $q) => $q->whereNull('user_id'),
                    ),
                TernaryFilter::make('stale')
                    ->label('Stale (> 24 h)')
                    ->queries(
                        true: fn (Builder $q) => $q->where(fn ($q) => $q->whereNull('last_synced_at')->orWhere('last_synced_at', '<', now()->subDay())),
                        false: fn (Builder $q) => $q->where('last_synced_at', '>=', now()->subDay()),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    GuideActions::syncNow(),
                    GuideActions::overrideStats(),
                    GuideActions::toggleActive(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    GuideActions::bulkSync(),
                    GuideActions::bulkInactive(),
                ]),
            ]);
    }
}
