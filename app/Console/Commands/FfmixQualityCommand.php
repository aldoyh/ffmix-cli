<?php

namespace App\Console\Commands;

use App\Services\Ffmix\QualityMetricsService;
use Illuminate\Console\Command;

class FfmixQualityCommand extends Command
{
    protected $signature = 'ffmix:quality
        {reference : Reference (original) video path}
        {distorted : Encoded (distorted) video path}
        {--no-vmaf : Skip VMAF}
        {--no-psnr : Skip PSNR}
        {--no-ssim : Skip SSIM}
        {--no-cambi : Skip CAMBI}
        {--json : Output JSON metrics}';

    protected $description = 'Measure perceived video quality with VMAF, PSNR, SSIM, and CAMBI';

    public function handle(QualityMetricsService $service): int
    {
        try {
            $result = $service->evaluate(
                referencePath: (string) $this->argument('reference'),
                distortedPath: (string) $this->argument('distorted'),
                vmaf: ! (bool) $this->option('no-vmaf'),
                psnr: ! (bool) $this->option('no-psnr'),
                ssim: ! (bool) $this->option('no-ssim'),
                cambi: ! (bool) $this->option('no-cambi'),
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Video Quality Metrics Report (#'.$result['report_id'].')');
        $this->table(
            ['Metric', 'Score'],
            [
                ['VMAF', $result['vmaf'] !== null ? (string) $result['vmaf'] : 'N/A'],
                ['PSNR (Y / Avg)', ($result['psnr_y'] ?? 'N/A').' / '.($result['psnr_avg'] ?? 'N/A').' dB'],
                ['SSIM', $result['ssim'] !== null ? (string) $result['ssim'] : 'N/A'],
                ['CAMBI (Banding)', $result['cambi'] !== null ? (string) $result['cambi'] : 'N/A'],
                ['Quality Grade', $result['grade']],
            ],
        );

        return self::SUCCESS;
    }
}
