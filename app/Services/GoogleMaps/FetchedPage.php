<?php

namespace App\Services\GoogleMaps;

final readonly class FetchedPage
{
    public function __construct(
        public string $title,
        public string $text,
        public ?string $avatarUrl = null,
    ) {}
}
