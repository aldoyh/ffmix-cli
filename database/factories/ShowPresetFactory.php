<?php

namespace Database\Factories;

use App\Models\ShowPreset;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ShowPreset>
 */
class ShowPresetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucwords(fake()->unique()->word().' '.fake()->word()).' Show';

        return [
            'slug' => Str::slug($name),
            'name' => $name,
            'description' => fake()->sentence(),
            'is_active' => false,
            'config' => [
                'show_name' => $name,
                'normalize' => [
                    'width' => 1920,
                    'height' => 1080,
                    'fps' => 30,
                    'video_codec' => 'h264',
                    'audio_codec' => 'aac',
                    'audio_bitrate' => '192k',
                    'hw_accel' => false,
                    'slate_duration' => 5.0,
                ],
                'watchdog' => [
                    'enabled' => false,
                    'input_dir' => 'storage/app/ffmix/ingest',
                    'normalized_dir' => 'storage/app/ffmix/normalized',
                    'poll_seconds' => 3,
                ],
                'dsk' => [
                    'folder' => 'storage/app/ffmix/dsk',
                    'active_template' => 'sample-lower-third.html',
                    'title' => $name,
                    'subtitle' => 'Live Broadcast Feed',
                    'accent' => '#06b6d4',
                    'duration' => 6.0,
                    'start_at' => 1.0,
                ],
                'transition' => [
                    'type' => 'logo_sting',
                    'duration' => 1.0,
                    'sting_asset' => null,
                ],
                'pipeline' => [
                    'container' => 'mp4',
                    'video_codec' => 'h264',
                    'audio_codec' => 'aac',
                    'hw_accel' => false,
                    'resolution' => '1080p',
                    'crf' => 20,
                    'audio_bitrate' => '192k',
                ],
            ],
        ];
    }
}
