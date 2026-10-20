<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Small key/value store for admin-editable site content (home page hero, links).
 *
 * @property string $key
 * @property mixed $value
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::rememberForever("settings:{$key}", fn () => static::where('key', $key)->first()?->value);

        return $value ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("settings:{$key}");
    }
}
