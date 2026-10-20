<?php

namespace App\Services\GoogleMaps;

/**
 * Extracts stats from the rendered profile's innerText using the named regex
 * patterns in config/localguides.php (adjustable without code changes).
 */
class ProfileTextParser
{
    public function parse(string $text, string $title = ''): ParsedProfile
    {
        return new ParsedProfile(
            name: $this->name($text, $title),
            level: $this->number('level', $text),
            points: $this->number('points', $text),
            reviews: $this->number('reviews', $text),
            photos: $this->number('photos', $text),
            videos: $this->number('videos', $text),
            photoViews: $this->number('photo_views', $text),
            placesAdded: $this->number('places_added', $text),
            roadsAdded: $this->number('roads_added', $text),
        );
    }

    public function isBlockedPage(string $text): bool
    {
        foreach (config('localguides.blocked_markers', []) as $marker) {
            if (stripos($text, $marker) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Name from the stats panel heading / line above the level badge, falling
     * back to the page title (minus " - Google Maps").
     */
    public function name(string $text, string $title = ''): ?string
    {
        foreach (config('localguides.name_patterns', []) as $pattern) {
            if (is_string($pattern) && preg_match($pattern, $text, $m) && ($candidate = $this->cleanName($m[1] ?? '')) !== null) {
                return $candidate;
            }
        }

        $name = trim($title);
        foreach (config('localguides.title_suffixes', []) as $suffix) {
            if (str_ends_with($name, $suffix)) {
                $name = trim(substr($name, 0, -strlen($suffix)));
            }
        }

        return $this->cleanName($name);
    }

    private function cleanName(string $name): ?string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');

        // A bare "Google Maps" title means the profile name wasn't rendered.
        if ($name === '' || strcasecmp($name, 'Google Maps') === 0) {
            return null;
        }

        return mb_substr($name, 0, 200);
    }

    public function number(string $field, string $text): ?int
    {
        $pattern = config("localguides.patterns.{$field}");
        if (! is_string($pattern) || ! preg_match($pattern, $text, $m)) {
            return null;
        }

        // Patterns may offer alternatives; use the first group that matched.
        $captured = collect(array_slice($m, 1))->first(fn ($g) => $g !== '' && $g !== null) ?? '';
        $digits = preg_replace('/\D+/', '', $captured);

        return $digits === '' || $digits === null ? null : (int) $digits;
    }
}
