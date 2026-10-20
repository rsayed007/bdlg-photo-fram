<?php

namespace App\Services\GoogleMaps;

final readonly class ResolvedContrib
{
    public function __construct(
        public string $contribId,
        public string $canonicalUrl,
        public ?int $levelHint = null,
    ) {}
}
