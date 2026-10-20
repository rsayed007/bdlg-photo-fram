<?php

namespace App\Support;

/**
 * Validates frames.config (see docs/FRAME_CONFIG.md). Returns a list of
 * human-readable errors keyed by JSON path; empty = valid.
 */
class FrameConfigValidator
{
    /** Stat fields a text slot may print. `static` prints its template as-is. */
    public const FIELDS = [
        'name', 'level', 'points', 'reviews', 'photos', 'videos', 'photo_views',
        'places_added', 'roads_added', 'views_per_photo', 'static',
    ];

    public const FORMATS = ['number', 'compact', 'raw'];

    public const ALIGNS = ['left', 'center', 'right'];

    public const MAX_PHOTO_SLOTS = 6;

    public const MAX_TEXT_SLOTS = 20;

    /** @var array<string, string> */
    private array $errors = [];

    /**
     * @return array<string, string>
     */
    public function validate(mixed $config, int $width, int $height): array
    {
        $this->errors = [];

        if (! is_array($config)) {
            return ['config' => 'Config must be a JSON object.'];
        }

        $unknown = array_diff(array_keys($config), ['fonts', 'photo_slots', 'text_slots', 'qr']);
        if ($unknown !== []) {
            $this->errors['config'] = 'Unknown keys: '.implode(', ', $unknown);
        }

        $families = $this->fonts($config['fonts'] ?? []);
        $this->photoSlots($config['photo_slots'] ?? null, $width, $height);
        $this->textSlots($config['text_slots'] ?? [], $width, $height, $families);
        $this->qr($config['qr'] ?? null, $width, $height);

        return $this->errors;
    }

    /** @return list<string> font families declared */
    private function fonts(mixed $fonts): array
    {
        if (! is_array($fonts) || ! array_is_list($fonts)) {
            $this->errors['fonts'] = 'fonts must be an array.';

            return [];
        }
        if (count($fonts) > 6) {
            $this->errors['fonts'] = 'At most 6 fonts.';
        }

        $families = [];
        foreach ($fonts as $i => $font) {
            $p = "fonts.{$i}";
            if (! is_array($font) || ! is_string($font['family'] ?? null) || trim($font['family']) === '') {
                $this->errors["{$p}.family"] = 'family is required.';

                continue;
            }
            if (! preg_match('/^[\pL\pN \-]{1,60}$/u', $font['family'])) {
                $this->errors["{$p}.family"] = 'family may only contain letters, numbers, spaces and dashes.';
            }
            if (isset($font['weight']) && (! is_int($font['weight']) || $font['weight'] < 100 || $font['weight'] > 900)) {
                $this->errors["{$p}.weight"] = 'weight must be an integer 100–900.';
            }
            $src = $font['src'] ?? null;
            if (! is_string($src) || ! preg_match('#^(/fonts/[A-Za-z0-9._-]+\.(ttf|otf|woff2?)|https://[^\s"\'()]+)$#', $src)) {
                $this->errors["{$p}.src"] = 'src must be /fonts/<file>.(ttf|otf|woff|woff2) or an https URL.';
            }
            $families[] = $font['family'];
        }

        return $families;
    }

    private function photoSlots(mixed $slots, int $w, int $h): void
    {
        if (! is_array($slots) || ! array_is_list($slots)) {
            $this->errors['photo_slots'] = 'photo_slots must be an array.';

            return;
        }
        if (count($slots) < 1 || count($slots) > self::MAX_PHOTO_SLOTS) {
            $this->errors['photo_slots'] = 'Between 1 and '.self::MAX_PHOTO_SLOTS.' photo slots.';
        }

        foreach ($slots as $i => $slot) {
            $p = "photo_slots.{$i}";
            if (! is_array($slot)) {
                $this->errors[$p] = 'Slot must be an object.';

                continue;
            }
            foreach (['x', 'y', 'w', 'h'] as $k) {
                if (! $this->isNumber($slot[$k] ?? null)) {
                    $this->errors["{$p}.{$k}"] = "{$k} is required and must be a number.";
                }
            }
            if (isset($this->errors["{$p}.x"]) || isset($this->errors["{$p}.y"]) || isset($this->errors["{$p}.w"]) || isset($this->errors["{$p}.h"])) {
                continue;
            }
            if ($slot['w'] <= 0 || $slot['h'] <= 0) {
                $this->errors["{$p}.w"] = 'w and h must be positive.';
            }
            if ($slot['x'] < 0 || $slot['y'] < 0 || $w < $slot['x'] + $slot['w'] || $h < $slot['y'] + $slot['h']) {
                $this->errors[$p] = "Slot must lie inside the {$w}×{$h} frame.";
            }
            if (isset($slot['radius']) && (! $this->isNumber($slot['radius']) || $slot['radius'] < 0 || $slot['radius'] > min($slot['w'], $slot['h']) / 2)) {
                $this->errors["{$p}.radius"] = 'radius must be between 0 and half the shorter side.';
            }
            if (isset($slot['rotate']) && (! $this->isNumber($slot['rotate']) || abs($slot['rotate']) > 45)) {
                $this->errors["{$p}.rotate"] = 'rotate must be between -45 and 45 degrees.';
            }
            if (isset($slot['required']) && ! is_bool($slot['required'])) {
                $this->errors["{$p}.required"] = 'required must be true or false.';
            }
        }
    }

    /** @param list<string> $families */
    private function textSlots(mixed $slots, int $w, int $h, array $families): void
    {
        if (! is_array($slots) || ! array_is_list($slots)) {
            $this->errors['text_slots'] = 'text_slots must be an array.';

            return;
        }
        if (count($slots) > self::MAX_TEXT_SLOTS) {
            $this->errors['text_slots'] = 'At most '.self::MAX_TEXT_SLOTS.' text slots.';
        }

        foreach ($slots as $i => $slot) {
            $p = "text_slots.{$i}";
            if (! is_array($slot)) {
                $this->errors[$p] = 'Slot must be an object.';

                continue;
            }
            $field = $slot['field'] ?? null;
            if (! in_array($field, self::FIELDS, true)) {
                $this->errors["{$p}.field"] = 'field must be one of: '.implode(', ', self::FIELDS).'.';
            }
            foreach (['x', 'y', 'size'] as $k) {
                if (! $this->isNumber($slot[$k] ?? null)) {
                    $this->errors["{$p}.{$k}"] = "{$k} is required and must be a number.";
                }
            }
            if ($this->isNumber($slot['x'] ?? null) && ($slot['x'] < 0 || $slot['x'] > $w)) {
                $this->errors["{$p}.x"] = "x must be within 0–{$w}.";
            }
            if ($this->isNumber($slot['y'] ?? null) && ($slot['y'] < 0 || $slot['y'] > $h)) {
                $this->errors["{$p}.y"] = "y must be within 0–{$h}.";
            }
            if ($this->isNumber($slot['size'] ?? null) && ($slot['size'] < 8 || $slot['size'] > 300)) {
                $this->errors["{$p}.size"] = 'size must be 8–300 px.';
            }
            if (isset($slot['color']) && (! is_string($slot['color']) || ! preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $slot['color']))) {
                $this->errors["{$p}.color"] = 'color must be a hex color like #0B3D91.';
            }
            if (isset($slot['align']) && ! in_array($slot['align'], self::ALIGNS, true)) {
                $this->errors["{$p}.align"] = 'align must be left, center or right.';
            }
            if (isset($slot['font']) && ! in_array($slot['font'], $families, true)) {
                $this->errors["{$p}.font"] = 'font must be one of the declared font families.';
            }
            if (isset($slot['weight']) && (! is_int($slot['weight']) || $slot['weight'] < 100 || $slot['weight'] > 900)) {
                $this->errors["{$p}.weight"] = 'weight must be an integer 100–900.';
            }
            if (isset($slot['format']) && ! in_array($slot['format'], self::FORMATS, true)) {
                $this->errors["{$p}.format"] = 'format must be number, compact or raw.';
            }
            if (isset($slot['optional']) && ! is_bool($slot['optional'])) {
                $this->errors["{$p}.optional"] = 'optional must be true or false.';
            }
            if (isset($slot['max_width']) && (! $this->isNumber($slot['max_width']) || $slot['max_width'] <= 0 || $slot['max_width'] > $w)) {
                $this->errors["{$p}.max_width"] = "max_width must be 1–{$w}.";
            }
            if (isset($slot['template'])) {
                if (! is_string($slot['template']) || mb_strlen($slot['template']) > 200) {
                    $this->errors["{$p}.template"] = 'template must be a string of at most 200 characters.';
                } else {
                    preg_match_all('/\{([a-z_]+)\}/', $slot['template'], $m);
                    $bad = array_diff($m[1], array_diff(self::FIELDS, ['static']));
                    if ($bad !== []) {
                        $this->errors["{$p}.template"] = 'Unknown placeholder(s): '.implode(', ', array_map(fn ($b) => '{'.$b.'}', $bad));
                    }
                }
            } elseif ($field === 'static') {
                $this->errors["{$p}.template"] = 'A static text slot needs a template.';
            }
        }
    }

    private function qr(mixed $qr, int $w, int $h): void
    {
        if ($qr === null) {
            return;
        }
        if (! is_array($qr)) {
            $this->errors['qr'] = 'qr must be an object.';

            return;
        }
        if (isset($qr['enabled']) && ! is_bool($qr['enabled'])) {
            $this->errors['qr.enabled'] = 'enabled must be true or false.';
        }
        if (($qr['enabled'] ?? false) === true) {
            foreach (['x', 'y', 'size'] as $k) {
                if (! $this->isNumber($qr[$k] ?? null)) {
                    $this->errors["qr.{$k}"] = "{$k} is required when the QR code is enabled.";
                }
            }
            if ($this->isNumber($qr['x'] ?? null) && $this->isNumber($qr['y'] ?? null) && $this->isNumber($qr['size'] ?? null)
                && ($qr['size'] < 40 || $qr['x'] < 0 || $qr['y'] < 0 || $w < $qr['x'] + $qr['size'] || $h < $qr['y'] + $qr['size'])) {
                $this->errors['qr'] = "QR code (min 40 px) must lie inside the {$w}×{$h} frame.";
            }
        }
    }

    private function isNumber(mixed $v): bool
    {
        return is_int($v) || is_float($v);
    }
}
