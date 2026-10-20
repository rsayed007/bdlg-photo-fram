<?php

namespace App\Contracts;

use App\Services\GoogleMaps\FetchedPage;

interface ProfileFetcher
{
    /**
     * Render the public contributor page and return its title + innerText.
     *
     * @throws \Throwable on timeouts / browser failures
     */
    public function fetch(string $contribId): FetchedPage;
}
