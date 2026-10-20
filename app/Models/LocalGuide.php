<?php

namespace App\Models;

use App\Enums\GuideStatus;
use Database\Factories\LocalGuideFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $contrib_id
 * @property string $profile_url
 * @property string|null $name
 * @property string|null $avatar_url
 * @property int|null $level
 * @property int|null $points
 * @property int|null $reviews
 * @property int|null $photos
 * @property int|null $videos
 * @property int|null $photo_views
 * @property int|null $places_added
 * @property int|null $roads_added
 * @property GuideStatus $status
 * @property int $fail_count
 * @property Carbon|null $last_synced_at
 * @property Carbon|null $last_attempt_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class LocalGuide extends Model
{
    /** @use HasFactory<LocalGuideFactory> */
    use HasFactory;

    /** Numeric stat columns scraped from the profile, in display order. */
    public const STAT_FIELDS = [
        'level', 'points', 'reviews', 'photos', 'videos', 'photo_views', 'places_added', 'roads_added',
    ];

    /** Columns stored per day in local_guide_snapshots. */
    public const SNAPSHOT_FIELDS = ['level', 'points', 'reviews', 'photos', 'videos', 'photo_views'];

    protected $fillable = [
        'user_id', 'contrib_id', 'profile_url', 'name', 'avatar_url',
        'level', 'points', 'reviews', 'photos', 'videos', 'photo_views', 'places_added', 'roads_added',
        'status', 'fail_count', 'last_synced_at', 'last_attempt_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => GuideStatus::class,
            'level' => 'integer',
            'points' => 'integer',
            'reviews' => 'integer',
            'photos' => 'integer',
            'videos' => 'integer',
            'photo_views' => 'integer',
            'places_added' => 'integer',
            'roads_added' => 'integer',
            'fail_count' => 'integer',
            'last_synced_at' => 'datetime',
            'last_attempt_at' => 'datetime',
        ];
    }

    public static function canonicalUrl(string $contribId): string
    {
        return "https://www.google.com/maps/contrib/{$contribId}";
    }

    public function getRouteKeyName(): string
    {
        return 'contrib_id';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<LocalGuideSnapshot, $this> */
    public function snapshots(): HasMany
    {
        return $this->hasMany(LocalGuideSnapshot::class);
    }

    /** @return HasMany<SyncLog, $this> */
    public function syncLogs(): HasMany
    {
        return $this->hasMany(SyncLog::class);
    }

    /** @return HasMany<FrameGeneration, $this> */
    public function frameGenerations(): HasMany
    {
        return $this->hasMany(FrameGeneration::class);
    }

    /** @return HasMany<StatOverride, $this> */
    public function statOverrides(): HasMany
    {
        return $this->hasMany(StatOverride::class);
    }

    /**
     * Profiles shown on the public leaderboard.
     *
     * @param  Builder<LocalGuide>  $query
     */
    public function scopeListed(Builder $query): void
    {
        $query->where('status', GuideStatus::Active);
    }

    public function isStale(int $hours = 24): bool
    {
        return $this->last_synced_at === null || $this->last_synced_at->lt(now()->subHours($hours));
    }

    public function viewsPerPhoto(): ?float
    {
        if ($this->photo_views === null || ! $this->photos) {
            return null;
        }

        return round($this->photo_views / $this->photos, 1);
    }

    /**
     * Stats as used by the frame renderer (null = unknown).
     *
     * @return array<string, int|string|null>
     */
    public function statsForFrame(): array
    {
        return [
            'name' => $this->name,
            'level' => $this->level,
            'points' => $this->points,
            'reviews' => $this->reviews,
            'photos' => $this->photos,
            'videos' => $this->videos,
            'photo_views' => $this->photo_views,
            'places_added' => $this->places_added,
            'roads_added' => $this->roads_added,
            'profile_url' => $this->profile_url,
        ];
    }
}
