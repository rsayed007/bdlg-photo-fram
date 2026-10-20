<?php

namespace App\Models;

use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property bool $is_active
 */
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description', 'starts_at', 'ends_at', 'is_active'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<Frame, $this> */
    public function frames(): HasMany
    {
        return $this->hasMany(Frame::class);
    }
}
