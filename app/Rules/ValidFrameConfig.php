<?php

namespace App\Rules;

use App\Support\FrameConfigValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Accepts the config as an array or a JSON string (Filament code editor).
 */
class ValidFrameConfig implements ValidationRule
{
    public function __construct(private readonly int $width, private readonly int $height) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $fail('The frame config is not valid JSON: '.json_last_error_msg());

                return;
            }
        }

        foreach ((new FrameConfigValidator)->validate($value, $this->width, $this->height) as $path => $message) {
            $fail("{$path}: {$message}");
        }
    }
}
