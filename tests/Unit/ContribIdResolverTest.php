<?php

use App\Services\GoogleMaps\ContribIdResolver;
use App\Services\GoogleMaps\InvalidProfileUrlException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

const TEST_ID = '107121503923715912205';

beforeEach(function () {
    Http::preventStrayRequests();
    $this->resolver = new ContribIdResolver;
});

it('resolves a direct contributor URL', function (string $url) {
    $result = $this->resolver->resolve($url);

    expect($result->contribId)->toBe(TEST_ID)
        ->and($result->canonicalUrl)->toBe('https://www.google.com/maps/contrib/'.TEST_ID)
        ->and($result->levelHint)->toBeNull();
})->with([
    'plain' => 'https://www.google.com/maps/contrib/'.TEST_ID,
    'contribute suffix' => 'https://www.google.com/maps/contrib/'.TEST_ID.'/contribute/',
    'query string' => 'https://www.google.com/maps/contrib/'.TEST_ID.'/reviews?hl=en&entry=ttu',
    'bare google.com' => 'https://google.com/maps/contrib/'.TEST_ID,
    'maps.google.com' => 'https://maps.google.com/maps/contrib/'.TEST_ID.'/photos',
    'surrounding whitespace' => '  https://www.google.com/maps/contrib/'.TEST_ID."  \n",
]);

it('follows a maps.app.goo.gl short link', function () {
    Http::fake([
        'maps.app.goo.gl/*' => Http::response('', 302, [
            'Location' => 'https://www.google.com/maps/contrib/'.TEST_ID.'?g_st=ic',
        ]),
    ]);

    $result = $this->resolver->resolve('https://maps.app.goo.gl/4KNXhprYBX5dsAdWA');

    expect($result->contribId)->toBe(TEST_ID);
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $r) => $r->method() === 'HEAD');
});

it('falls back to GET when HEAD does not redirect', function () {
    Http::fake(function (Request $request) {
        return $request->method() === 'HEAD'
            ? Http::response('', 405)
            : Http::response('', 301, ['Location' => 'https://www.google.com/maps/contrib/'.TEST_ID]);
    });

    expect($this->resolver->resolve('https://maps.app.goo.gl/abc')->contribId)->toBe(TEST_ID);
    Http::assertSentCount(2);
});

it('parses the share text pasted from the Maps app', function () {
    $text = "See what I've posted to Google Maps\nLevel 8 Local Guide\nhttps://maps.app.goo.gl/4KNXhprYBX5dsAdWA";
    Http::fake(['maps.app.goo.gl/*' => Http::response('', 302, ['Location' => 'https://www.google.com/maps/contrib/'.TEST_ID])]);

    $result = $this->resolver->resolve($text);

    expect($result->contribId)->toBe(TEST_ID)->and($result->levelHint)->toBe(8);
});

it('rejects malicious or unsupported input', function (string $input, string $key) {
    expect(fn () => $this->resolver->resolve($input))
        ->toThrow(InvalidProfileUrlException::class, $key);
    Http::assertNothingSent();
})->with([
    'other host' => ['https://evil.example.com/maps/contrib/'.TEST_ID, 'resolver.disallowed_host'],
    'lookalike host' => ['https://www.google.com.evil.io/maps/contrib/'.TEST_ID, 'resolver.disallowed_host'],
    'ip literal' => ['https://142.250.183.4/maps/contrib/'.TEST_ID, 'resolver.disallowed_host'],
    'http scheme' => ['http://www.google.com/maps/contrib/'.TEST_ID, 'resolver.insecure'],
    'credentials' => ['https://user:pass@www.google.com/maps/contrib/'.TEST_ID, 'resolver.disallowed_host'],
    'custom port' => ['https://www.google.com:8443/maps/contrib/'.TEST_ID, 'resolver.disallowed_host'],
    'non-contrib google path' => ['https://www.google.com/maps/place/Dhaka', 'resolver.not_contrib_url'],
    'javascript url' => ['javascript:alert(1)', 'resolver.no_url'],
    'no url' => ['hello world', 'resolver.no_url'],
]);

it('rejects a short link that redirects to a disallowed host', function () {
    Http::fake([
        'maps.app.goo.gl/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data']),
    ]);

    expect(fn () => $this->resolver->resolve('https://maps.app.goo.gl/bad'))
        ->toThrow(InvalidProfileUrlException::class);

    Http::assertSentCount(1);
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '169.254'));
});

it('rejects a redirect hop to an external https host', function () {
    Http::fake([
        'maps.app.goo.gl/*' => Http::response('', 302, ['Location' => 'https://attacker.example/redirect']),
    ]);

    expect(fn () => $this->resolver->resolve('https://maps.app.goo.gl/bad'))
        ->toThrow(InvalidProfileUrlException::class, 'resolver.disallowed_host');
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'attacker'));
});

it('gives up after too many redirects', function () {
    Http::fake([
        'maps.app.goo.gl/*' => Http::response('', 302, ['Location' => 'https://maps.app.goo.gl/loop']),
    ]);

    expect(fn () => $this->resolver->resolve('https://maps.app.goo.gl/loop'))
        ->toThrow(InvalidProfileUrlException::class, 'resolver.too_many_redirects');
});

it('unwraps a consent.google.com interstitial', function () {
    $continue = urlencode('https://www.google.com/maps/contrib/'.TEST_ID);
    Http::fake([
        'maps.app.goo.gl/*' => Http::response('', 302, ['Location' => "https://consent.google.com/m?continue={$continue}&gl=BD"]),
    ]);

    expect($this->resolver->resolve('https://maps.app.goo.gl/x')->contribId)->toBe(TEST_ID);
});
