<?php

namespace Database\Factories;

use App\Models\NormalizedAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NormalizedAsset>
 */
class NormalizedAssetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = fake()->unique()->lexify('asset_????');

        return [
            'show_preset_id' => null,
            'source_path' => "/tmp/ingest/{$slug}.mov",
            'normalized_path' => "/tmp/normalized/{$slug}.mp4",
            'asset_type' => 'video',
            'source_mtime' => 1700000000,
            'source_size' => 1024000,
            'status' => 'ready',
            'duration_seconds' => 10.0,
            'normalized_at' => now(),
        ];
    }
}
