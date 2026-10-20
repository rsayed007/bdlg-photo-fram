<?php

use App\Rules\ValidFrameConfig;
use App\Support\FrameConfigValidator;
use App\Support\NumberFormat;
use Database\Factories\FrameFactory;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class);

describe('compact number format', function () {
    it('formats like the spec', function (?int $in, string $out) {
        expect(NumberFormat::compact($in))->toBe($out);
    })->with([
        [null, '—'],
        [0, '0'],
        [999, '999'],
        [1000, '1K'],
        [1234, '1.2K'],
        [1250, '1.2K'],
        [20232, '20.2K'],
        [999950, '999.9K'],
        [3_400_000, '3.4M'],
        [8_591_611, '8.5M'],
        [1_100_000_000, '1.1B'],
    ]);
});

describe('frame config validation', function () {
    beforeEach(fn () => $this->v = new FrameConfigValidator);

    it('accepts the sample config from the spec', function () {
        expect($this->v->validate(FrameFactory::sampleConfig(), 1080, 1920))->toBe([]);
    });

    it('rejects slots outside the frame', function () {
        $config = FrameFactory::sampleConfig();
        $config['photo_slots'][0]['y'] = 1700; // 1700 + 330 > 1920

        expect($this->v->validate($config, 1080, 1920))->toHaveKey('photo_slots.0');
    });

    it('rejects unknown text fields, placeholders, fonts and colors', function () {
        $config = FrameFactory::sampleConfig();
        $config['text_slots'][0]['field'] = 'password';
        $config['text_slots'][1]['template'] = 'Level {lvl}';
        $config['text_slots'][2]['font'] = 'Comic Sans';
        $config['text_slots'][3]['color'] = 'red';

        expect($this->v->validate($config, 1080, 1920))->toHaveKeys([
            'text_slots.0.field', 'text_slots.1.template', 'text_slots.2.font', 'text_slots.3.color',
        ]);
    });

    it('rejects unsafe font sources', function () {
        $config = FrameFactory::sampleConfig();
        $config['fonts'][0]['src'] = 'javascript:alert(1)';
        $config['fonts'][1]['src'] = 'http://insecure.example/font.ttf';

        expect($this->v->validate($config, 1080, 1920))->toHaveKeys(['fonts.0.src', 'fonts.1.src']);
    });

    it('requires at least one photo slot and caps the count', function () {
        $config = FrameFactory::sampleConfig();
        $config['photo_slots'] = [];
        expect($this->v->validate($config, 1080, 1920))->toHaveKey('photo_slots');

        $config['photo_slots'] = array_fill(0, 7, ['x' => 0, 'y' => 0, 'w' => 10, 'h' => 10]);
        expect($this->v->validate($config, 1080, 1920))->toHaveKey('photo_slots');
    });

    it('validates the QR block only when enabled', function () {
        $config = FrameFactory::sampleConfig();
        $config['qr'] = ['enabled' => false, 'x' => 5000];
        expect($this->v->validate($config, 1080, 1920))->toBe([]);

        $config['qr'] = ['enabled' => true, 'x' => 1000, 'y' => 1700, 'size' => 140];
        expect($this->v->validate($config, 1080, 1920))->toHaveKey('qr');
    });

    it('needs a template for static text', function () {
        $config = FrameFactory::sampleConfig();
        $config['text_slots'][] = ['field' => 'static', 'x' => 540, 'y' => 100, 'size' => 40];
        expect($this->v->validate($config, 1080, 1920))->toHaveKey('text_slots.6.template');
    });

    it('works as a Laravel validation rule with JSON strings', function () {
        $ok = Validator::make(['c' => json_encode(FrameFactory::sampleConfig())], ['c' => new ValidFrameConfig(1080, 1920)]);
        $bad = Validator::make(['c' => '{not json'], ['c' => new ValidFrameConfig(1080, 1920)]);

        expect($ok->passes())->toBeTrue()
            ->and($bad->passes())->toBeFalse()
            ->and($bad->errors()->first('c'))->toContain('not valid JSON');
    });
});
