<?php

namespace App\Models;

use Database\Factories\LocalGuideSnapshotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $local_guide_id
 * @property Carbon $snap_date
 * @property int|null $level
 * @property int|null $points
 * @property int|null $reviews
 * @property int|null $photos
 * @property int|null $videos
 * @property int|null $photo_views
 */
class LocalGuideSnapshot extends Model
{
    /** @use HasFactory<LocalGuideSnapshotFactory> */
    use HasFactory;

    public $timestamps = false;

    public $incrementing = false;

    /** Composite key (local_guide_id, snap_date); rows are written via upsert. */
    protected $primaryKey = 'local_guide_id';

    protected $fillable = ['local_guide_id', 'snap_date', 'level', 'points', 'reviews', 'photos', 'videos', 'photo_views'];

    protected function casts(): array
    {
        return [
            'snap_date' => 'date',
            'level' => 'integer',
            'points' => 'integer',
            'reviews' => 'integer',
            'photos' => 'integer',
            'videos' => 'integer',
            'photo_views' => 'integer',
        ];
    }

    /** @return BelongsTo<LocalGuide, $this> */
    public function localGuide(): BelongsTo
    {
        return $this->belongsTo(LocalGuide::class);
    }
}
