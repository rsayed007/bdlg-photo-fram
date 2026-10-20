<?php

namespace App\Services\GoogleMaps;

final readonly class ParsedProfile
{
    public function __construct(
        public ?string $name,
        public ?int $level,
        public ?int $points,
        public ?int $reviews,
        public ?int $photos,
        public ?int $videos,
        public ?int $photoViews,
        public ?int $placesAdded,
        public ?int $roadsAdded,
    ) {}

    /** A private ("Restricted profile") page shows neither level nor points. */
    public function isRestricted(): bool
    {
        return $this->level === null && $this->points === null;
    }

    /**
     * Stats keyed by DB column; null values are dropped so a partial parse never
     * overwrites a previously known value with "unknown".
     *
     * @return array<string, int>
     */
    public function knownStats(): array
    {
        return array_filter([
            'level' => $this->level,
            'points' => $this->points,
            'reviews' => $this->reviews,
            'photos' => $this->photos,
            'videos' => $this->videos,
            'photo_views' => $this->photoViews,
            'places_added' => $this->placesAdded,
            'roads_added' => $this->roadsAdded,
        ], fn ($v) => $v !== null);
    }
}
