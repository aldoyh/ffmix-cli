<?php

namespace Database\Factories;

use App\Models\AnalysisReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalysisReport>
 */
class AnalysisReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => 'bitrate',
            'source_path' => '/tmp/sample.mp4',
            'reference_path' => null,
            'summary' => [
                'avg_bps' => 4500000,
                'max_bps' => 7800000,
                'min_bps' => 2100000,
                'p95_bps' => 7100000,
                'duration_seconds' => 12.0,
            ],
            'series' => [3200000, 4100000, 5600000, 4800000, 7800000, 3900000],
            'raw_output' => null,
        ];
    }
}
