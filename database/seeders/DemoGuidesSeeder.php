<?php

namespace Database\Seeders;

use App\Models\LocalGuide;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ~50 fake guides with 90 days of monotonically growing snapshots, so the
 * leaderboard, profile charts and compare page have data in development.
 */
class DemoGuidesSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $guides = LocalGuide::factory()->count(48)->create();
        LocalGuide::factory()->restricted()->create(['name' => 'Restricted Demo Guide', 'level' => null, 'points' => null]);
        LocalGuide::factory()->pending()->create();

        $days = 90;
        foreach ($guides as $guide) {
            $rows = [];
            // Walk backwards from today's values, shrinking a little each day.
            $points = (int) $guide->points;
            $reviews = (int) $guide->reviews;
            $photos = (int) $guide->photos;
            $videos = (int) $guide->videos;
            $views = (int) $guide->photo_views;

            for ($d = 0; $d < $days; $d++) {
                $rows[] = [
                    'local_guide_id' => $guide->id,
                    'snap_date' => now()->subDays($d)->toDateString(),
                    'level' => $guide->level,
                    'points' => $points,
                    'reviews' => $reviews,
                    'photos' => $photos,
                    'videos' => $videos,
                    'photo_views' => $views,
                ];
                $points = max(0, $points - random_int(0, 120));
                $reviews = max(0, $reviews - random_int(0, 2));
                $photos = max(0, $photos - random_int(0, 15));
                $videos = max(0, $videos - random_int(0, 1));
                $views = max(0, $views - random_int(0, max(1, intdiv($views, 200))));
            }

            DB::table('local_guide_snapshots')->insert($rows);
        }
    }
}
