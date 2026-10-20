<?php

namespace App\Services\GoogleMaps;

use App\Models\LocalGuide;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Turns whatever a member pastes (profile URL, maps.app.goo.gl short link, or
 * the full share text from the Maps app) into a numeric contributor ID.
 *
 * Short links are followed manually, one hop at a time, and every hop's host
 * is checked against the allowlist before it is requested (SSRF protection).
 */
class ContribIdResolver
{
    private const CONTRIB_PATH = '#^/maps/contrib/(\d{5,32})(?:[/?]|$)#';

    public function resolve(string $input): ResolvedContrib
    {
        $input = trim($input);
        $url = $this->extractUrl($input);
        $levelHint = $this->extractLevelHint($input);

        $contribId = $this->contribIdFromUrl($url) ?? $this->followShortLink($url);

        return new ResolvedContrib($contribId, LocalGuide::canonicalUrl($contribId), $levelHint);
    }

    /** Find the first URL inside free text (e.g. share text from the app). */
    private function extractUrl(string $input): string
    {
        if (preg_match('#https?://[^\s<>"\']+#i', $input, $m)) {
            return rtrim($m[0], '.,);]');
        }

        // Bare host without scheme, e.g. "maps.app.goo.gl/abc".
        if (preg_match('#(?:^|\s)((?:www\.)?(?:google\.com|maps\.google\.com|maps\.app\.goo\.gl)/[^\s]+)#i', $input, $m)) {
            return 'https://'.rtrim($m[1], '.,);]');
        }

        throw InvalidProfileUrlException::noUrl();
    }

    private function extractLevelHint(string $input): ?int
    {
        return preg_match('/Level\s+(\d{1,2})\s+Local\s+Guide/i', $input, $m) ? (int) $m[1] : null;
    }

    /**
     * Returns the contributor ID for a direct /maps/contrib/ URL, null for an
     * allowed short link, and throws for anything else.
     */
    private function contribIdFromUrl(string $url): ?string
    {
        $parts = $this->parse($url);

        if (in_array($parts['host'], config('localguides.allowed_hosts.contrib'), true)) {
            if (preg_match(self::CONTRIB_PATH, $parts['path'], $m)) {
                return $m[1];
            }

            throw InvalidProfileUrlException::notContribUrl();
        }

        if (in_array($parts['host'], config('localguides.allowed_hosts.short'), true)) {
            return null;
        }

        throw InvalidProfileUrlException::disallowedHost();
    }

    private function followShortLink(string $url): string
    {
        $maxRedirects = (int) config('localguides.resolver.max_redirects', 5);
        $allowed = array_merge(
            config('localguides.allowed_hosts.contrib'),
            config('localguides.allowed_hosts.short'),
            config('localguides.allowed_hosts.intermediate'),
        );

        $current = $url;
        for ($hop = 0; $hop <= $maxRedirects; $hop++) {
            $parts = $this->parse($current);
            if (! in_array($parts['host'], $allowed, true)) {
                throw InvalidProfileUrlException::disallowedHost();
            }

            // Reached a contributor URL: done (never request it).
            if (in_array($parts['host'], config('localguides.allowed_hosts.contrib'), true)
                && preg_match(self::CONTRIB_PATH, $parts['path'], $m)) {
                return $m[1];
            }

            // consent.google.com wraps the real target in ?continue=
            if ($parts['host'] === 'consent.google.com' && $parts['continue'] !== null) {
                $current = $parts['continue'];

                continue;
            }

            if ($hop === $maxRedirects) {
                break;
            }

            $location = $this->nextLocation($current);
            if ($location === null) {
                throw InvalidProfileUrlException::notContribUrl();
            }

            $current = $this->absolutize($location, $current);
        }

        throw InvalidProfileUrlException::tooManyRedirects();
    }

    /** HEAD first; some short-link endpoints only redirect on GET. */
    private function nextLocation(string $url): ?string
    {
        foreach (['head', 'get'] as $method) {
            try {
                /** @var Response $response */
                $response = Http::withOptions(['allow_redirects' => false])
                    ->timeout((int) config('localguides.resolver.timeout', 10))
                    ->withHeaders(['Accept-Language' => 'en-US'])
                    ->{$method}($url);
            } catch (ConnectionException) {
                throw InvalidProfileUrlException::unreachable();
            }

            if ($response->redirect() && $response->header('Location') !== '') {
                return $response->header('Location');
            }

            // A 200 page may still carry the target in a meta refresh / canonical.
            if ($method === 'get' && $response->successful()
                && preg_match('#https://(?:www\.)?google\.com/maps/contrib/\d+[^"\'\s<]*#', $response->body(), $m)) {
                return html_entity_decode($m[0]);
            }
        }

        return null;
    }

    private function absolutize(string $location, string $base): string
    {
        if (str_starts_with($location, '//')) {
            return 'https:'.$location;
        }
        if (str_starts_with($location, '/')) {
            $b = $this->parse($base);

            return "https://{$b['host']}{$location}";
        }

        return $location;
    }

    /**
     * @return array{host: string, path: string, continue: string|null}
     */
    private function parse(string $url): array
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            throw InvalidProfileUrlException::noUrl();
        }

        if (strtolower($parts['scheme'] ?? '') !== 'https') {
            throw InvalidProfileUrlException::insecure();
        }

        // No credentials, custom ports or IP literals.
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            throw InvalidProfileUrlException::disallowedHost();
        }

        $host = strtolower(rtrim($parts['host'], '.'));
        if (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false) {
            throw InvalidProfileUrlException::disallowedHost();
        }

        $continue = null;
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
            $continue = is_string($query['continue'] ?? null) ? $query['continue'] : null;
        }

        return ['host' => $host, 'path' => $parts['path'] ?? '/', 'continue' => $continue];
    }
}
