<?php

namespace Database\Factories;

use App\Models\Frame;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Frame>
 */
class FrameFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->words(3, true);

        return [
            'title' => Str::title($title),
            'slug' => Str::slug($title),
            'size_w' => 1080,
            'size_h' => 1920,
            'background_path' => 'public/frames/test/background.png',
            'overlay_path' => null,
            'thumbnail_path' => null,
            'config' => self::sampleConfig(),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /** @return array<string, mixed> */
    public static function sampleConfig(): array
    {
        return [
            'fonts' => [
                ['family' => 'Hind Siliguri', 'weight' => 700, 'src' => '/fonts/HindSiliguri-Bold.ttf'],
                ['family' => 'Poppins', 'weight' => 700, 'src' => '/fonts/Poppins-Bold.ttf'],
            ],
            'photo_slots' => [
                ['x' => 70, 'y' => 1300, 'w' => 290, 'h' => 330, 'radius' => 12, 'rotate' => -3, 'required' => true],
                ['x' => 395, 'y' => 1290, 'w' => 290, 'h' => 330, 'radius' => 12, 'rotate' => 0, 'required' => false],
                ['x' => 720, 'y' => 1300, 'w' => 290, 'h' => 330, 'radius' => 12, 'rotate' => 3, 'required' => false],
            ],
            'text_slots' => [
                ['field' => 'name', 'x' => 540, 'y' => 1690, 'size' => 44, 'color' => '#0B3D91', 'align' => 'center', 'font' => 'Poppins', 'max_width' => 900],
                ['field' => 'level', 'x' => 540, 'y' => 1745, 'size' => 30, 'color' => '#E8541E', 'align' => 'center', 'template' => 'Level {level} Local Guide', 'optional' => true],
                ['field' => 'points', 'x' => 180, 'y' => 1820, 'size' => 34, 'color' => '#1F7A3A', 'align' => 'center', 'template' => '{points} pts', 'format' => 'compact', 'optional' => true],
                ['field' => 'reviews', 'x' => 420, 'y' => 1820, 'size' => 34, 'color' => '#1F7A3A', 'align' => 'center', 'template' => '{reviews} reviews', 'format' => 'compact', 'optional' => true],
                ['field' => 'photos', 'x' => 660, 'y' => 1820, 'size' => 34, 'color' => '#1F7A3A', 'align' => 'center', 'template' => '{photos} photos', 'format' => 'compact', 'optional' => true],
                ['field' => 'photo_views', 'x' => 900, 'y' => 1820, 'size' => 34, 'color' => '#1F7A3A', 'align' => 'center', 'template' => '{photo_views} views', 'format' => 'compact', 'optional' => true],
            ],
            'qr' => ['enabled' => false, 'x' => 900, 'y' => 1700, 'size' => 140],
        ];
    }
}
