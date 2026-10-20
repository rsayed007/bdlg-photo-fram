<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit trail for manual stat overrides made by admins.
 *
 * @property int $id
 * @property int $local_guide_id
 * @property int|null $user_id
 * @property array<string, array{from: mixed, to: mixed}> $changes
 * @property string $note
 */
class StatOverride extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['local_guide_id', 'user_id', 'changes', 'note'];

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
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
}
