<?php

namespace Database\Factories;

use App\Models\Frame;
use App\Models\FrameGeneration;
use App\Models\LocalGuide;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FrameGeneration>
 */
class FrameGenerationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'frame_id' => Frame::factory(),
            'local_guide_id' => LocalGuide::factory(),
            'user_id' => null,
            'output_path' => 'public/generations/'.fake()->uuid().'.png',
            'stats_snapshot' => ['name' => fake()->name(), 'level' => 7, 'points' => 12345],
            'source_photo_paths' => [],
            'downloads' => 0,
            'is_featured' => false,
        ];
    }

    public function featured(): static
    {
        return $this->state(fn () => ['is_featured' => true]);
    }
}
