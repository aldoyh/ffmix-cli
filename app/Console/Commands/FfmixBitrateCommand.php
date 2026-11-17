<?php

namespace App\Console\Commands;

use App\Services\Ffmix\BitrateAnalyzerService;
use Illuminate\Console\Command;

class FfmixBitrateCommand extends Command
{
    protected $signature = 'ffmix:bitrate
        {input : Path to video file}
        {--json : Output JSON bitrate telemetry}';

    protected $description = 'Analyze video bitrate variations per second and render an ASCII sparkline';

    public function handle(BitrateAnalyzerService $analyzer): int
    {
        $input = (string) $this->argument('input');

        try {
            $result = $analyzer->analyze($input);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info("Bitrate Analysis: {$result['path']}");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Duration', sprintf('%.2f s', $result['duration_seconds'])],
                ['Average Bitrate', $analyzer->formatBps($result['avg_bps'])],
                ['Min Bitrate', $analyzer->formatBps($result['min_bps'])],
                ['Max Bitrate', $analyzer->formatBps($result['max_bps'])],
                ['P95 Peak', $analyzer->formatBps($result['p95_bps'])],
                ['Keyframes', (string) $result['keyframes']],
                ['Report ID', '#'.$result['report_id']],
            ],
        );

        $this->line('Timeline: '.$result['sparkline']);

        return self::SUCCESS;
    }
}
