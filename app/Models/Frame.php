<?php

namespace App\Models;

use Database\Factories\FrameFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $event_id
 * @property string $title
 * @property string $slug
 * @property int $size_w
 * @property int $size_h
 * @property string $background_path
 * @property string|null $overlay_path
 * @property string|null $thumbnail_path
 * @property array<string, mixed> $config
 * @property bool $is_active
 * @property int $sort_order
 * @property-read Event|null $event
 */
class Frame extends Model
{
    /** @use HasFactory<FrameFactory> */
    use HasFactory;

    protected $fillable = [
        'event_id', 'title', 'slug', 'size_w', 'size_h', 'background_path', 'overlay_path',
        'thumbnail_path', 'config', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_active' => 'boolean',
            'size_w' => 'integer',
            'size_h' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return HasMany<FrameGeneration, $this> */
    public function generations(): HasMany
    {
        return $this->hasMany(FrameGeneration::class);
    }

    /** @param Builder<Frame> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Same-origin URL for a frame asset (background/overlay/thumbnail). Served
     * through the app so the Konva canvas is never tainted by cross-origin images.
     */
    public function assetUrl(string $kind): ?string
    {
        $path = match ($kind) {
            'background' => $this->background_path,
            'overlay' => $this->overlay_path,
            'thumbnail' => $this->thumbnail_path ?? $this->background_path,
            default => null,
        };

        if ($path === null) {
            return null;
        }

        return route('frames.asset', ['frame' => $this, 'kind' => $kind, 'v' => substr(md5($path), 0, 8)]);
    }
}
