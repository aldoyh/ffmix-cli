<?php

namespace App\Services\Ffmix;

use App\Models\AnalysisReport;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class QualityMetricsService
{
    /**
     * Compute VMAF, PSNR, SSIM, and CAMBI video quality metrics comparing distorted vs reference.
     *
     * @return array{
     *   vmaf: ?float,
     *   psnr_y: ?float,
     *   psnr_avg: ?float,
     *   ssim: ?float,
     *   cambi: ?float,
     *   grade: string,
     *   command: string,
     *   report_id: int
     * }
     */
    public function evaluate(
        string $referencePath,
        string $distortedPath,
        bool $vmaf = true,
        bool $psnr = true,
        bool $ssim = true,
        bool $cambi = true,
    ): array {
        $ffmpegBin = (string) config('ffmix.binaries.ffmpeg', 'ffmpeg');
        $logFile = sys_get_temp_dir().'/ffmix_vmaf_'.uniqid('', true).'.json';

        $args = $this->buildCommand($ffmpegBin, $referencePath, $distortedPath, $logFile, $vmaf, $psnr, $ssim, $cambi);
        $result = Process::timeout(600)->run($args);

        $rawOutput = $result->errorOutput()."\n".$result->output();
        $jsonContents = is_file($logFile) ? (string) file_get_contents($logFile) : '';
        if (is_file($logFile)) {
            @unlink($logFile);
        }

        if (! $result->successful()) {
            throw new RuntimeException('Quality metrics failed: '.trim($rawOutput));
        }

        $metrics = $this->parseOutput($rawOutput, $jsonContents);
        $grade = $this->classifyGrade($metrics['vmaf'], $metrics['ssim'], $metrics['psnr_avg']);

        $report = AnalysisReport::query()->create([
            'kind' => 'quality',
            'source_path' => $distortedPath,
            'reference_path' => $referencePath,
            'summary' => [
                'vmaf' => $metrics['vmaf'],
                'psnr_y' => $metrics['psnr_y'],
                'psnr_avg' => $metrics['psnr_avg'],
                'ssim' => $metrics['ssim'],
                'cambi' => $metrics['cambi'],
                'grade' => $grade,
            ],
            'series' => $metrics['frame_vmaf'],
            'raw_output' => mb_substr($rawOutput, -4000),
        ]);

        return [
            'vmaf' => $metrics['vmaf'],
            'psnr_y' => $metrics['psnr_y'],
            'psnr_avg' => $metrics['psnr_avg'],
            'ssim' => $metrics['ssim'],
            'cambi' => $metrics['cambi'],
            'grade' => $grade,
            'command' => implode(' ', array_map('escapeshellarg', $args)),
            'report_id' => $report->id,
        ];
    }

    /**
     * @return list<string>
     */
    public function buildCommand(
        string $ffmpegBin,
        string $referencePath,
        string $distortedPath,
        string $logFile,
        bool $vmaf,
        bool $psnr,
        bool $ssim,
        bool $cambi,
    ): array {
        $features = [];
        if ($psnr) {
            $features[] = 'name=psnr';
        }
        if ($ssim) {
            $features[] = 'name=float_ssim';
        }
        if ($cambi) {
            $features[] = 'name=cambi';
        }

        $filterParts = [];
        if ($vmaf || $cambi) {
            $vmafOpts = [
                'log_fmt=json',
                'log_path='.str_replace(':', '\\:', $logFile),
            ];
            if ($features !== []) {
                $vmafOpts[] = 'feature='.implode('|', $features);
            }
            $filterParts[] = '[0:v][1:v]libvmaf='.implode(':', $vmafOpts);
        } else {
            if ($psnr) {
                $filterParts[] = '[0:v][1:v]psnr';
            }
            if ($ssim) {
                $filterParts[] = '[0:v][1:v]ssim';
            }
        }

        // FFmpeg expects [distorted][reference] for libvmaf, psnr, ssim
        return [
            $ffmpegBin,
            '-hide_banner',
            '-i',
            $distortedPath,
            '-i',
            $referencePath,
            '-lavfi',
            implode(';', $filterParts),
            '-f',
            'null',
            '-',
        ];
    }

    /**
     * @return array{vmaf: ?float, psnr_y: ?float, psnr_avg: ?float, ssim: ?float, cambi: ?float, frame_vmaf: list<float>}
     */
    public function parseOutput(string $stderr, string $vmafJson = ''): array
    {
        $vmaf = null;
        $psnrY = null;
        $psnrAvg = null;
        $ssim = null;
        $cambi = null;
        $frameVmaf = [];

        if ($vmafJson !== '') {
            /** @var array<string, mixed>|null $decoded */
            $decoded = json_decode($vmafJson, true);
            if (is_array($decoded)) {
                /** @var array<string, mixed> $pooled */
                $pooled = is_array($decoded['pooled_metrics'] ?? null) ? $decoded['pooled_metrics'] : [];
                if (isset($pooled['vmaf']['mean'])) {
                    $vmaf = round((float) $pooled['vmaf']['mean'], 2);
                }
                if (isset($pooled['psnr_y']['mean'])) {
                    $psnrY = round((float) $pooled['psnr_y']['mean'], 2);
                    $psnrAvg = $psnrY;
                }
                if (isset($pooled['float_ssim']['mean'])) {
                    $ssim = round((float) $pooled['float_ssim']['mean'], 4);
                }
                if (isset($pooled['cambi']['mean'])) {
                    $cambi = round((float) $pooled['cambi']['mean'], 3);
                }

                if (is_array($decoded['frames'] ?? null)) {
                    foreach ($decoded['frames'] as $frame) {
                        if (is_array($frame) && isset($frame['metrics']['vmaf'])) {
                            $frameVmaf[] = round((float) $frame['metrics']['vmaf'], 2);
                        }
                    }
                }
            }
        }

        if ($vmaf === null && preg_match('/VMAF score:\s*([0-9.]+)/i', $stderr, $m) === 1) {
            $vmaf = round((float) $m[1], 2);
        }

        if ($psnrAvg === null && preg_match('/PSNR\s+y:([0-9.]+).*?average:([0-9.]+)/i', $stderr, $m) === 1) {
            $psnrY = round((float) $m[1], 2);
            $psnrAvg = round((float) $m[2], 2);
        }

        if ($ssim === null && preg_match('/SSIM\s+Y:[0-9.]+\s+.*?All:([0-9.]+)/i', $stderr, $m) === 1) {
            $ssim = round((float) $m[1], 4);
        }

        return [
            'vmaf' => $vmaf,
            'psnr_y' => $psnrY,
            'psnr_avg' => $psnrAvg,
            'ssim' => $ssim,
            'cambi' => $cambi,
            'frame_vmaf' => $frameVmaf,
        ];
    }

    private function classifyGrade(?float $vmaf, ?float $ssim, ?float $psnr): string
    {
        if ($vmaf !== null) {
            return match (true) {
                $vmaf >= 93.0 => 'Transparent / Mastering',
                $vmaf >= 85.0 => 'High Quality',
                $vmaf >= 75.0 => 'Good Streaming',
                default => 'Visible Artifacts',
            };
        }

        if ($ssim !== null) {
            return match (true) {
                $ssim >= 0.98 => 'Transparent / Mastering',
                $ssim >= 0.95 => 'High Quality',
                $ssim >= 0.90 => 'Good Streaming',
                default => 'Visible Artifacts',
            };
        }

        if ($psnr !== null) {
            return $psnr >= 40.0 ? 'High Quality' : 'Standard';
        }

        return 'Measured';
    }
}
