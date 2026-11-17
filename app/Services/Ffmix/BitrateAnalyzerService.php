<?php

namespace App\Services\Ffmix;

use App\Models\AnalysisReport;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class BitrateAnalyzerService
{
    /**
     * Examine bitrate variation per second across a video file using ffprobe packet telemetry.
     *
     * @return array{
     *   path: string,
     *   duration_seconds: float,
     *   avg_bps: int,
     *   min_bps: int,
     *   max_bps: int,
     *   p95_bps: int,
     *   keyframes: int,
     *   buckets: list<int>,
     *   sparkline: string,
     *   report_id: int
     * }
     */
    public function analyze(string $inputPath): array
    {
        $ffprobeBin = (string) config('ffmix.binaries.ffprobe', 'ffprobe');

        $result = Process::timeout(120)->run([
            $ffprobeBin,
            '-v',
            'quiet',
            '-select_streams',
            'v:0',
            '-show_entries',
            'packet=pts_time,dts_time,size,flags',
            '-of',
            'json',
            $inputPath,
        ]);

        if (! $result->successful()) {
            throw new RuntimeException('Bitrate analysis failed: '.trim($result->errorOutput() ?: $result->output()));
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($result->output(), true);
        if (! is_array($decoded) || ! is_array($decoded['packets'] ?? null)) {
            throw new RuntimeException('Unable to parse ffprobe packet stream.');
        }

        /** @var list<array<string, mixed>> $packets */
        $packets = $decoded['packets'];
        $stats = $this->aggregatePackets($packets);
        $sparkline = $this->renderSparkline($stats['buckets']);

        $report = AnalysisReport::query()->create([
            'kind' => 'bitrate',
            'source_path' => $inputPath,
            'summary' => [
                'duration_seconds' => $stats['duration_seconds'],
                'avg_bps' => $stats['avg_bps'],
                'min_bps' => $stats['min_bps'],
                'max_bps' => $stats['max_bps'],
                'p95_bps' => $stats['p95_bps'],
                'keyframes' => $stats['keyframes'],
            ],
            'series' => $stats['buckets'],
        ]);

        return [
            'path' => $inputPath,
            'duration_seconds' => $stats['duration_seconds'],
            'avg_bps' => $stats['avg_bps'],
            'min_bps' => $stats['min_bps'],
            'max_bps' => $stats['max_bps'],
            'p95_bps' => $stats['p95_bps'],
            'keyframes' => $stats['keyframes'],
            'buckets' => $stats['buckets'],
            'sparkline' => $sparkline,
            'report_id' => $report->id,
        ];
    }

    /**
     * Aggregate packet sizes into 1-second bits-per-second buckets.
     *
     * @param  list<array<string, mixed>>  $packets
     * @return array{duration_seconds: float, avg_bps: int, min_bps: int, max_bps: int, p95_bps: int, keyframes: int, buckets: list<int>}
     */
    public function aggregatePackets(array $packets): array
    {
        if ($packets === []) {
            return [
                'duration_seconds' => 0.0,
                'avg_bps' => 0,
                'min_bps' => 0,
                'max_bps' => 0,
                'p95_bps' => 0,
                'keyframes' => 0,
                'buckets' => [],
            ];
        }

        $perSecondBytes = [];
        $maxTime = 0.0;
        $keyframes = 0;

        foreach ($packets as $pkt) {
            $time = (float) ($pkt['pts_time'] ?? $pkt['dts_time'] ?? 0.0);
            $size = (int) ($pkt['size'] ?? 0);
            $flags = (string) ($pkt['flags'] ?? '');

            if (str_contains($flags, 'K')) {
                $keyframes++;
            }

            if ($time < 0.0) {
                $time = 0.0;
            }
            if ($time > $maxTime) {
                $maxTime = $time;
            }

            $sec = (int) floor($time);
            $perSecondBytes[$sec] = ($perSecondBytes[$sec] ?? 0) + $size;
        }

        ksort($perSecondBytes);
        $lastSec = (int) array_key_last($perSecondBytes);
        $buckets = [];
        for ($i = 0; $i <= $lastSec; $i++) {
            $buckets[] = ($perSecondBytes[$i] ?? 0) * 8;
        }

        $sorted = $buckets;
        sort($sorted);
        $count = count($sorted);
        $p95Index = max(0, min($count - 1, (int) floor($count * 0.95)));
        $avg = $count > 0 ? (int) round(array_sum($buckets) / $count) : 0;

        return [
            'duration_seconds' => round(max($maxTime, (float) $count), 2),
            'avg_bps' => $avg,
            'min_bps' => $sorted[0] ?? 0,
            'max_bps' => $sorted[$count - 1] ?? 0,
            'p95_bps' => $sorted[$p95Index] ?? 0,
            'keyframes' => $keyframes,
            'buckets' => $buckets,
        ];
    }

    /**
     * Render a Unicode block sparkline for terminal UI display.
     *
     * @param  list<int>  $buckets
     */
    public function renderSparkline(array $buckets, int $maxWidth = 48): string
    {
        if ($buckets === []) {
            return '';
        }

        // Resample down to maxWidth if longer
        $samples = $buckets;
        if (count($buckets) > $maxWidth) {
            $samples = [];
            $step = count($buckets) / $maxWidth;
            for ($i = 0; $i < $maxWidth; $i++) {
                $idx = min(count($buckets) - 1, (int) floor($i * $step));
                $samples[] = $buckets[$idx];
            }
        }

        if ($samples === []) {
            return '';
        }

        $bars = ['▁', '▂', '▃', '▄', '▅', '▆', '▇', '█'];
        $min = min($samples);
        $max = max($samples);
        $range = max(1, $max - $min);

        $out = '';
        foreach ($samples as $val) {
            $norm = ($val - $min) / $range;
            $barIdx = min(7, max(0, (int) round($norm * 7)));
            $out .= $bars[$barIdx];
        }

        return $out;
    }

    public function formatBps(int $bps): string
    {
        if ($bps >= 1_000_000) {
            return sprintf('%.2f Mbps', $bps / 1_000_000);
        }
        if ($bps >= 1_000) {
            return sprintf('%.1f kbps', $bps / 1_000);
        }

        return $bps.' bps';
    }
}
