<?php

namespace Database\Factories;

use App\Models\DropZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DropZone>
 */
class DropZoneFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->word().' '.fake()->word().' DropZone',
            'watch_path' => '/tmp/ffmix-watch-'.fake()->unique()->lexify('????'),
            'output_path' => '/tmp/ffmix-out-'.fake()->lexify('????'),
            'preset_slug' => 'desktop',
            'extensions' => ['mp4', 'mov', 'mkv', 'mxf', 'avi'],
            'post_action' => 'keep',
            'is_active' => true,
            'processed_count' => 0,
        ];
    }
}
