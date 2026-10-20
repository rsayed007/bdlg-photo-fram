<?php

use App\Services\GoogleMaps\ProfileTextParser;
use Tests\TestCase;

uses(TestCase::class);

/** @return array{0: string, 1: string} [title, text] */
function profileFixture(string $name): array
{
    $raw = file_get_contents(base_path("tests/Fixtures/profiles/{$name}.txt"));
    [$header, $text] = explode("\n---\n", str_replace("\r\n", "\n", $raw), 2);

    return [trim(substr($header, strlen('TITLE:'))), $text];
}

beforeEach(fn () => $this->parser = new ProfileTextParser);

it('parses the real rendered profile with the stats panel', function () {
    [$title, $text] = profileFixture('107121503923715912205');
    $p = $this->parser->parse($text, $title);

    expect($p->name)->toBe('Roman Syed')
        ->and($p->level)->toBe(8)
        ->and($p->points)->toBe(20232) // not 50,000 (next-level target)
        ->and($p->reviews)->toBe(282)
        ->and($p->photos)->toBe(1473)
        ->and($p->videos)->toBe(140)
        ->and($p->photoViews)->toBe(8591611)
        ->and($p->placesAdded)->toBe(53)
        ->and($p->roadsAdded)->toBe(0) // a real zero, distinct from unknown
        ->and($p->isRestricted())->toBeFalse();
});

it('falls back to the main page when the stats panel did not open', function () {
    [$title, $text] = profileFixture('main-page-only');
    $p = $this->parser->parse($text, $title);

    expect($p->name)->toBe('Roman Syed')
        ->and($p->level)->toBe(8)
        ->and($p->points)->toBe(20232)
        ->and($p->photos)->toBe(1436)
        ->and($p->photoViews)->toBe(8591611)
        // Not shown on the main page → unknown, never 0.
        ->and($p->reviews)->toBeNull()
        ->and($p->videos)->toBeNull()
        ->and($p->placesAdded)->toBeNull()
        ->and($p->roadsAdded)->toBeNull();

    expect($p->knownStats())->not->toHaveKeys(['reviews', 'videos', 'places_added', 'roads_added']);
});

it('parses the inline layout from the spec patterns', function () {
    [$title, $text] = profileFixture('inline-legacy');
    $p = $this->parser->parse($text, $title);

    expect($p->name)->toBe('Ayesha Rahman')
        ->and($p->level)->toBe(10)
        ->and($p->points)->toBe(1204551)
        ->and($p->reviews)->toBe(3120)
        ->and($p->photos)->toBe(15402)
        ->and($p->videos)->toBe(212)
        ->and($p->photoViews)->toBe(54021334)
        ->and($p->placesAdded)->toBe(118)
        ->and($p->roadsAdded)->toBe(7);
});

it('handles level 10 profiles without a next-level target', function () {
    [$title, $text] = profileFixture('top-level-no-target');
    $p = $this->parser->parse($text, $title);

    expect($p->name)->toBe('Shafiqul Islam')
        ->and($p->level)->toBe(10)
        ->and($p->points)->toBe(1050312)
        ->and($p->photos)->toBe(22904) // panel value wins over header
        ->and($p->videos)->toBe(0)
        ->and($p->photoViews)->toBe(91204550);
});

it('detects a restricted profile and keeps every stat null', function () {
    [$title, $text] = profileFixture('restricted');
    $p = $this->parser->parse($text, $title);

    expect($p->isRestricted())->toBeTrue()
        ->and($p->knownStats())->toBe([])
        ->and($p->points)->toBeNull()
        ->and($p->level)->toBeNull();
});

it('flags consent / captcha pages as blocked', function () {
    [, $text] = profileFixture('consent');

    expect($this->parser->isBlockedPage($text))->toBeTrue();

    [, $real] = profileFixture('107121503923715912205');
    expect($this->parser->isBlockedPage($real))->toBeFalse();
});

it('takes the name from the page title when the body has none', function () {
    expect($this->parser->name('', 'Karim Uddin - Google Maps'))->toBe('Karim Uddin')
        ->and($this->parser->name('', 'Google Maps'))->toBeNull();
});

it('reads patterns from config so they can be changed without code', function () {
    config(['localguides.patterns.points' => '/Punkte:\s*([\d.]+)/']);

    expect($this->parser->number('points', 'Punkte: 12.345'))->toBe(12345)
        ->and($this->parser->number('points', '20,232 / 50,000 points'))->toBeNull();
});

it('never turns a missing number into zero', function () {
    expect($this->parser->number('reviews', 'nothing here'))->toBeNull()
        ->and($this->parser->number('nonexistent_field', '123 reviews'))->toBeNull();
});
