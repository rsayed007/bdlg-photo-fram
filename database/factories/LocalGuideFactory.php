<?php

namespace Database\Factories;

use App\Enums\GuideStatus;
use App\Models\LocalGuide;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LocalGuide>
 */
class LocalGuideFactory extends Factory
{
    public function definition(): array
    {
        $contribId = (string) fake()->unique()->numerify('1#####################');
        $photos = fake()->numberBetween(50, 20000);

        return [
            'contrib_id' => $contribId,
            'profile_url' => LocalGuide::canonicalUrl($contribId),
            'name' => fake()->name(),
            'level' => fake()->numberBetween(3, 10),
            'points' => fake()->numberBetween(500, 400000),
            'reviews' => fake()->numberBetween(10, 3000),
            'photos' => $photos,
            'videos' => fake()->numberBetween(0, 500),
            'photo_views' => $photos * fake()->numberBetween(50, 5000),
            'places_added' => fake()->numberBetween(0, 200),
            'roads_added' => fake()->numberBetween(0, 50),
            'status' => GuideStatus::Active,
            'fail_count' => 0,
            'last_synced_at' => now()->subHours(fake()->numberBetween(1, 30)),
            'last_attempt_at' => now()->subHours(1),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'name' => null, 'level' => null, 'points' => null, 'reviews' => null, 'photos' => null,
            'videos' => null, 'photo_views' => null, 'places_added' => null, 'roads_added' => null,
            'status' => GuideStatus::Pending, 'last_synced_at' => null, 'last_attempt_at' => null,
        ]);
    }

    public function restricted(): static
    {
        return $this->state(fn () => ['status' => GuideStatus::Restricted]);
    }
}
