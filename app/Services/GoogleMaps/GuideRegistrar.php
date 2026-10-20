<?php

namespace App\Services\GoogleMaps;

use App\Enums\GuideStatus;
use App\Jobs\SyncLocalGuide;
use App\Models\LocalGuide;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Throwable;

/**
 * Module A entry point: resolve pasted input → find/create the guide → queue a
 * priority sync when the stored stats are missing or stale.
 */
class GuideRegistrar
{
    public function __construct(private readonly ContribIdResolver $resolver) {}

    /**
     * @return array{guide: LocalGuide, created: bool, queued: bool, position: int|null}
     *
     * @throws InvalidProfileUrlException
     */
    public function register(string $input, ?User $user = null): array
    {
        $resolved = $this->resolver->resolve($input);

        $guide = LocalGuide::firstOrNew(['contrib_id' => $resolved->contribId]);
        $created = ! $guide->exists;

        if ($created) {
            $guide->profile_url = $resolved->canonicalUrl;
            $guide->status = GuideStatus::Pending;
            // The share-text "Level N" hint is shown until the first sync.
            $guide->level = $resolved->levelHint;
        }

        // First signed-in member to add a profile claims it.
        if ($user !== null && $guide->user_id === null) {
            $guide->user_id = $user->id;
        }

        // A failed/inactive profile re-added by a member gets another chance.
        if (in_array($guide->status, [GuideStatus::Failed, GuideStatus::Inactive], true)) {
            $guide->status = GuideStatus::Pending;
            $guide->fail_count = 0;
        }

        $guide->save();

        $queued = false;
        if ($created || $guide->status === GuideStatus::Pending || $guide->isStale((int) config('localguides.sync.stale_after_hours', 24))) {
            SyncLocalGuide::dispatch($guide, true);
            $queued = true;
        }

        return [
            'guide' => $guide,
            'created' => $created,
            'queued' => $queued,
            'position' => $queued ? $this->queuePosition() : null,
        ];
    }

    public function queuePosition(): ?int
    {
        try {
            return Queue::size(SyncLocalGuide::QUEUE_PRIORITY);
        } catch (Throwable) {
            return null;
        }
    }
}
