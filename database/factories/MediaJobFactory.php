<?php

namespace Database\Factories;

use App\Enums\JobStatus;
use App\Models\MediaJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MediaJob>
 */
class MediaJobFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = fake()->lexify('clip_????');

        return [
            'name' => $slug.'.mp4',
            'input_path' => '/tmp/input/'.$slug.'.mov',
            'output_path' => '/tmp/output/'.$slug.'.mp4',
            'preset_slug' => 'desktop',
            'pipeline_config' => [
                'container' => 'mp4',
                'video_codec' => 'h264',
                'audio_codec' => 'aac',
                'hw_accel' => false,
                'resolution' => '1080p',
                'crf' => 20,
            ],
            'command_preview' => 'ffmpeg -y -i /tmp/input/'.$slug.'.mov /tmp/output/'.$slug.'.mp4',
            'status' => JobStatus::Pending,
            'progress_percent' => 0.0,
            'fps' => 0.0,
            'duration_seconds' => 30.0,
            'processed_seconds' => 0.0,
        ];
    }
}
