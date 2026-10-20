<?php

namespace App\Console\Commands;

use App\Contracts\ProfileFetcher;
use App\Services\GoogleMaps\ProfileTextParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Debug tool for parser maintenance (see docs/PARSER.md): renders a profile,
 * prints the parsed stats and optionally saves the innerText as a fixture.
 * Does not touch the database.
 */
class FetchGuideProfile extends Command
{
    protected $signature = 'guides:fetch {contrib_id} {--save= : Save innerText to tests/Fixtures/profiles/<name>.txt} {--text : Print the full innerText}';

    protected $description = 'Render a contributor profile with Chromium and show what the parser extracts';

    public function handle(ProfileFetcher $fetcher, ProfileTextParser $parser): int
    {
        $id = (string) $this->argument('contrib_id');
        if (! preg_match('/^\d{5,32}$/', $id)) {
            $this->error('contrib_id must be numeric');

            return self::FAILURE;
        }

        $started = microtime(true);
        $page = $fetcher->fetch($id);
        $this->info(sprintf('Rendered in %.1fs — title: "%s", %d chars of text', microtime(true) - $started, $page->title, mb_strlen($page->text)));

        if ($parser->isBlockedPage($page->text)) {
            $this->warn('Google served a consent/captcha page.');
        }

        $parsed = $parser->parse($page->text, $page->title);
        $this->table(['field', 'value'], collect([
            'name' => $parsed->name,
            'level' => $parsed->level,
            'points' => $parsed->points,
            'reviews' => $parsed->reviews,
            'photos' => $parsed->photos,
            'videos' => $parsed->videos,
            'photo_views' => $parsed->photoViews,
            'places_added' => $parsed->placesAdded,
            'roads_added' => $parsed->roadsAdded,
            'avatar_url' => $page->avatarUrl,
            'restricted?' => $parsed->isRestricted() ? 'yes' : 'no',
        ])->map(fn ($v, $k) => [$k, $v ?? 'null'])->values()->all());

        if ($this->option('text')) {
            $this->line($page->text);
        }

        if ($name = $this->option('save')) {
            $name = preg_replace('/[^a-z0-9_-]/i', '', (string) $name);
            $path = base_path("tests/Fixtures/profiles/{$name}.txt");
            File::ensureDirectoryExists(dirname($path));
            File::put($path, "TITLE: {$page->title}\n---\n{$page->text}");
            $this->info("Saved fixture to {$path}");
        }

        return self::SUCCESS;
    }
}
