<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->sentence(3);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->paragraph(),
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->addDays(30),
            'is_active' => true,
        ];
    }
}
