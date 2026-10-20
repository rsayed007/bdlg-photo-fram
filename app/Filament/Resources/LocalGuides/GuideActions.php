<?php

namespace App\Filament\Resources\LocalGuides;

use App\Enums\GuideStatus;
use App\Jobs\SyncLocalGuide;
use App\Models\LocalGuide;
use App\Models\StatOverride;
use App\Services\GoogleMaps\GuideRegistrar;
use App\Services\GoogleMaps\InvalidProfileUrlException;
use App\Support\LeaderboardCache;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Admin actions shared by the list and view pages.
 */
class GuideActions
{
    public static function syncNow(): Action
    {
        return Action::make('syncNow')
            ->label('Sync now')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('primary')
            ->visible(fn (LocalGuide $record) => $record->status !== GuideStatus::Inactive)
            ->action(function (LocalGuide $record) {
                SyncLocalGuide::dispatch($record, true);
                Notification::make()->title('Queued on sync-priority')->success()->send();
            });
    }

    public static function toggleActive(): Action
    {
        return Action::make('toggleActive')
            ->label(fn (LocalGuide $record) => $record->status === GuideStatus::Inactive ? 'Reactivate' : 'Mark inactive')
            ->icon(fn (LocalGuide $record) => $record->status === GuideStatus::Inactive ? Heroicon::OutlinedPlayCircle : Heroicon::OutlinedPauseCircle)
            ->color(fn (LocalGuide $record) => $record->status === GuideStatus::Inactive ? 'success' : 'gray')
            ->requiresConfirmation()
            ->modalDescription(fn (LocalGuide $record) => $record->status === GuideStatus::Inactive
                ? 'The profile will be synced again and shown on the leaderboard after its next successful sync.'
                : 'Inactive profiles are hidden from public pages and skipped by the daily sync.')
            ->action(function (LocalGuide $record) {
                if ($record->status === GuideStatus::Inactive) {
                    $record->update(['status' => GuideStatus::Pending, 'fail_count' => 0]);
                    SyncLocalGuide::dispatch($record, true);
                } else {
                    $record->update(['status' => GuideStatus::Inactive]);
                }
                LeaderboardCache::bust();
            });
    }

    /** Manual stat override with an audit note (stored in stat_overrides). */
    public static function overrideStats(): Action
    {
        $fields = ['name', ...LocalGuide::STAT_FIELDS];

        return Action::make('overrideStats')
            ->label('Override stats')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('warning')
            ->fillForm(fn (LocalGuide $record) => $record->only($fields))
            ->schema([
                TextInput::make('name')->maxLength(200),
                Grid::make(4)->schema(array_map(
                    fn (string $f) => TextInput::make($f)->numeric()->minValue(0)->placeholder('unknown')
                        ->helperText($f === 'level' ? '1–10' : null)
                        ->maxValue($f === 'level' ? 10 : null),
                    LocalGuide::STAT_FIELDS,
                )),
                Textarea::make('note')
                    ->label('Audit note')
                    ->helperText('Why is this being changed? Stored with your name. The next sync may overwrite these values.')
                    ->required()
                    ->maxLength(500),
            ])
            ->action(function (LocalGuide $record, array $data) use ($fields) {
                $changes = [];
                foreach ($fields as $f) {
                    $new = ($data[$f] ?? '') === '' ? null : ($f === 'name' ? $data[$f] : (int) $data[$f]);
                    if ($new !== $record->{$f}) {
                        $changes[$f] = ['from' => $record->{$f}, 'to' => $new];
                        $record->{$f} = $new;
                    }
                }

                if ($changes === []) {
                    Notification::make()->title('Nothing changed')->send();

                    return;
                }

                DB::transaction(function () use ($record, $changes, $data) {
                    $record->save();
                    StatOverride::create([
                        'local_guide_id' => $record->id,
                        'user_id' => auth()->id(),
                        'changes' => $changes,
                        'note' => $data['note'],
                    ]);
                });
                LeaderboardCache::bust();

                Notification::make()->title('Stats overridden ('.count($changes).' fields)')->success()->send();
            });
    }

    public static function addByUrl(): Action
    {
        return Action::make('addByUrl')
            ->label('Add by URL')
            ->icon(Heroicon::OutlinedPlus)
            ->schema([
                Textarea::make('profile_url')
                    ->label('Profile link, short link or share text')
                    ->required()
                    ->rows(3),
            ])
            ->action(function (array $data, GuideRegistrar $registrar, Action $action) {
                try {
                    $result = $registrar->register($data['profile_url']);
                } catch (InvalidProfileUrlException $e) {
                    Notification::make()->title($e->userMessage())->danger()->send();
                    $action->halt();

                    return;
                }

                Notification::make()
                    ->title($result['created'] ? 'Profile added and queued' : 'Profile already exists')
                    ->body($result['guide']->contrib_id)
                    ->success()
                    ->send();
            });
    }

    public static function bulkSync(): BulkAction
    {
        return BulkAction::make('bulkSync')
            ->label('Sync selected')
            ->icon(Heroicon::OutlinedArrowPath)
            ->requiresConfirmation()
            ->modalDescription('Each profile is queued on sync-priority (still rate limited to a few per minute).')
            ->action(function (Collection $records) {
                foreach ($records as $guide) {
                    if ($guide instanceof LocalGuide && $guide->status !== GuideStatus::Inactive) {
                        SyncLocalGuide::dispatch($guide, true);
                    }
                }
                Notification::make()->title("Queued {$records->count()} profiles")->success()->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function bulkInactive(): BulkAction
    {
        return BulkAction::make('bulkInactive')
            ->label('Mark inactive')
            ->icon(Heroicon::OutlinedPauseCircle)
            ->color('gray')
            ->requiresConfirmation()
            ->action(function (Collection $records) {
                LocalGuide::whereKey($records->modelKeys())->update(['status' => GuideStatus::Inactive]);
                LeaderboardCache::bust();
            })
            ->deselectRecordsAfterCompletion();
    }
}
