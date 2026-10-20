<?php

namespace App\Models;

use App\Enums\SyncStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $local_guide_id
 * @property SyncStatus $status
 * @property int|null $duration_ms
 * @property string|null $error
 * @property string|null $raw_excerpt
 * @property Carbon $created_at
 */
class SyncLog extends Model
{
    use Prunable;

    public const UPDATED_AT = null;

    protected $fillable = ['local_guide_id', 'status', 'duration_ms', 'error', 'raw_excerpt'];

    protected function casts(): array
    {
        return [
            'status' => SyncStatus::class,
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<LocalGuide, $this> */
    public function localGuide(): BelongsTo
    {
        return $this->belongsTo(LocalGuide::class);
    }

    /** @return Builder<SyncLog> */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(30));
    }
}
