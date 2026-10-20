<?php

namespace Tests\Fakes;

use App\Contracts\ProfileFetcher;
use App\Services\GoogleMaps\FetchedPage;
use RuntimeException;
use Throwable;

/**
 * Test double for the Chromium fetcher: returns fixture text or throws.
 */
class FakeProfileFetcher implements ProfileFetcher
{
    /** @var list<FetchedPage|Throwable> */
    private array $responses = [];

    /** @var list<string> */
    public array $fetched = [];

    public static function fixture(string $name): FetchedPage
    {
        $raw = (string) file_get_contents(base_path("tests/Fixtures/profiles/{$name}.txt"));
        [$header, $text] = explode("\n---\n", str_replace("\r\n", "\n", $raw), 2);

        return new FetchedPage(trim(substr($header, strlen('TITLE:'))), $text, 'https://lh3.googleusercontent.com/a-/avatar=s72');
    }

    public function push(FetchedPage|Throwable $response): self
    {
        $this->responses[] = $response;

        return $this;
    }

    public function fetch(string $contribId): FetchedPage
    {
        $this->fetched[] = $contribId;
        $next = array_shift($this->responses) ?? new RuntimeException('No fake response queued');

        if ($next instanceof Throwable) {
            throw $next;
        }

        return $next;
    }
}
