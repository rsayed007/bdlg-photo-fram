<?php

namespace App\Models;

use Database\Factories\FrameGenerationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property string $id
 * @property int $frame_id
 * @property int|null $local_guide_id
 * @property int|null $user_id
 * @property string $output_path
 * @property array<string, mixed> $stats_snapshot
 * @property list<string>|null $source_photo_paths
 * @property int $downloads
 * @property bool $is_featured
 * @property Carbon|null $sources_purged_at
 * @property Carbon $created_at
 * @property-read Frame $frame
 * @property-read LocalGuide|null $localGuide
 */
class FrameGeneration extends Model
{
    /** @use HasFactory<FrameGenerationFactory> */
    use HasFactory, HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'frame_id', 'local_guide_id', 'user_id', 'output_path', 'stats_snapshot',
        'source_photo_paths', 'downloads', 'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'stats_snapshot' => 'array',
            'source_photo_paths' => 'array',
            'downloads' => 'integer',
            'is_featured' => 'boolean',
            'sources_purged_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Frame, $this> */
    public function frame(): BelongsTo
    {
        return $this->belongsTo(Frame::class);
    }

    /** @return BelongsTo<LocalGuide, $this> */
    public function localGuide(): BelongsTo
    {
        return $this->belongsTo(LocalGuide::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Absolute public (CDN) URL of the final PNG — used for og:image. */
    public function imageUrl(): string
    {
        $url = Storage::url($this->output_path);

        return str_starts_with($url, 'http') ? $url : url($url);
    }

    public function shareUrl(): string
    {
        return route('share.show', $this);
    }

    /** Remove the final image and any remaining source photos from storage. */
    public function deleteFiles(): void
    {
        $disk = Storage::disk();
        $disk->delete(array_filter([$this->output_path, ...($this->source_photo_paths ?? [])]));
    }

    protected static function booted(): void
    {
        static::deleting(fn (FrameGeneration $generation) => $generation->deleteFiles());
    }
}
