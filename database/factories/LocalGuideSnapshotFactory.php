<?php

namespace Database\Factories;

use App\Models\LocalGuide;
use App\Models\LocalGuideSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LocalGuideSnapshot>
 */
class LocalGuideSnapshotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'local_guide_id' => LocalGuide::factory(),
            'snap_date' => now()->toDateString(),
            'level' => 7,
            'points' => fake()->numberBetween(1000, 50000),
            'reviews' => fake()->numberBetween(10, 500),
            'photos' => fake()->numberBetween(10, 5000),
            'videos' => fake()->numberBetween(0, 50),
            'photo_views' => fake()->numberBetween(1000, 5000000),
        ];
    }
}
