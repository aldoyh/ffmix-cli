<?php

namespace Database\Factories;

use App\Models\Preset;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Preset>
 */
class PresetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word().' '.fake()->word();

        return [
            'slug' => Str::slug($name),
            'name' => ucwords($name),
            'category' => 'Custom',
            'description' => fake()->sentence(),
            'is_builtin' => false,
            'config' => [
                'container' => 'mp4',
                'video_codec' => 'h264',
                'audio_codec' => 'aac',
                'hw_accel' => false,
                'resolution' => '1080p',
                'crf' => 20,
                'audio_bitrate' => '192k',
            ],
        ];
    }
}
