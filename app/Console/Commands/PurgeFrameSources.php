<?php

namespace App\Console\Commands;

use App\Models\FrameGeneration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Privacy housekeeping:
 *  - original uploads attached to a generation are deleted after 30 days
 *    (the final PNG is kept until the generation itself is deleted);
 *  - abandoned temp uploads (never used in a generation) after 24 hours.
 */
class PurgeFrameSources extends Command
{
    protected $signature = 'frames:purge-sources {--days=30} {--tmp-hours=24}';

    protected $description = 'Delete original uploads 30 days after generation and stale temp uploads';

    public function handle(): int
    {
        $cutoff = now()->subDays((int) $this->option('days'));
        $purged = 0;

        FrameGeneration::query()
            ->whereNull('sources_purged_at')
            ->where('created_at', '<', $cutoff)
            ->chunkById(200, function ($generations) use (&$purged) {
                foreach ($generations as $generation) {
                    Storage::delete($generation->source_photo_paths ?? []);
                    $generation->forceFill(['source_photo_paths' => [], 'sources_purged_at' => now()])->save();
                    $purged++;
                }
            });

        $tmpCutoff = now()->subHours((int) $this->option('tmp-hours'))->getTimestamp();
        $tmpDeleted = 0;
        foreach (Storage::allFiles('tmp') as $path) {
            if (Storage::lastModified($path) < $tmpCutoff) {
                Storage::delete($path);
                $tmpDeleted++;
            }
        }

        $this->info("Purged sources of {$purged} generations and {$tmpDeleted} temp uploads.");

        return self::SUCCESS;
    }
}
