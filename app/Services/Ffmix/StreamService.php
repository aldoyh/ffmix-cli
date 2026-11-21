<?php

namespace App\Services\Ffmix;

use App\Models\MediaJob;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

/**
 * Core live-stream orchestration service.
 *
 * Manages stream sessions (HLS input → overlay composition → output/recording),
 * applies DSK overlays during recording, tracks job state, and provides
 * command compilation for both CLI and TUI consumers.
 */
class StreamService
{
    public function __construct(
        private HlsStreamService $hls,
        private DownstreamKeyService $dsk,
    ) {}

    /**
     * Create a new media job record for tracking a live stream session.
     *
     * @param  array<string, mixed>  $config
     * @return MediaJob
     */
    public function createJob(string $sessionKey, string $sourceUrl, array $config): MediaJob
    {
        return MediaJob::create([
            'name' => $sessionKey,
            'session_key' => $sessionKey,
            'input_path' => $sourceUrl,
            'source_url' => $sourceUrl,
            'output_path' => (string) ($config['output_path'] ?? ''),
            'stream_type' => $config['stream_type'] ?? 'hls',
            'pipeline_config' => $config,
            'status' => 'pending',
            'has_overlay' => ! empty($config['dsk']),
            'started_at' => null,
            'completed_at' => null,
            'error_message' => null,
        ]);
    }

    public function markJobStarted(MediaJob $job): MediaJob
    {
        $job->update(['status' => 'running', 'started_at' => now()]);

        return $job->fresh() ?? $job;
    }

    public function markJobFinished(MediaJob $job, string $outputPath): MediaJob
    {
        $job->update([
            'status' => 'completed',
            'completed_at' => now(),
            'output_path' => $outputPath,
        ]);

        return $job->fresh() ?? $job;
    }

    public function markJobFailed(MediaJob $job, string $error): MediaJob
    {
        $job->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_message' => $error,
        ]);

        return $job->fresh() ?? $job;
    }

    public function getJob(string $sessionKey): ?MediaJob
    {
        return MediaJob::query()->where('session_key', $sessionKey)->first();
    }

    /**
     * Build FFmpeg arguments to record an HLS stream to MP4 (direct remux).
     *
     * @param  int|null  $resolutionIndex  1-based index into qualities ladder (null = best/master URL)
     * @return list<string>
     */
    public function buildRecordStreamArgs(
        string $streamUrl,
        string $outputPath,
        int $recordSeconds = 60,
        ?int $resolutionIndex = null,
    ): array {
        $hlsArgs = $this->hls->buildRecordCommand($streamUrl, $outputPath, $resolutionIndex, $recordSeconds);

        // Extract input URL from built args
        $inputUrl = $streamUrl;
        if ($resolutionIndex !== null && $resolutionIndex > 0) {
            $playlist = $this->hls->probePlaylist($streamUrl, 15);
            $best = $playlist['qualities'][$resolutionIndex - 1] ?? null;
            if ($best !== null) {
                $inputUrl = $best['url'];
            }
        }

        return [
            (string) config('ffmix.binaries.ffmpeg', 'ffmpeg'),
            '-hide_banner', '-y',
            '-fflags', '+genpts',
            '-rw_timeout', '10000000',
            '-timeout', '15000000',
            '-re',
            '-i', $inputUrl,
            '-c:v', 'copy',
            '-c:a', 'copy',
            '-movflags', '+faststart',
            '-t', (string) $recordSeconds,
            $outputPath,
        ];
    }

    /**
     * Render DSK alpha overlay from HTML template and composite onto recorded video.
     *
     * @param  array<string, mixed>  $dskConfig  keys: template_path, title, subtitle, accent, width, height, fps, duration, start
     * @return array{overlay_path: string, output_path: string, dsk_frames: int}
     */
    public function applyDskToStream(
        string $videoPath,
        string $outputPath,
        array $dskConfig = [],
    ): array {
        $template = $dskConfig['template_path'] ?? null;
        $startAt = (float) ($dskConfig['start'] ?? 1.0);
        $duration = (float) ($dskConfig['duration'] ?? 6.0);
        $title = (string) ($dskConfig['title'] ?? 'ffMix Live Broadcast');
        $subtitle = (string) ($dskConfig['subtitle'] ?? 'Normalized 1080p30 Feed');
        $accent = (string) ($dskConfig['accent'] ?? '#06b6d4');
        $width = (int) ($dskConfig['width'] ?? 1920);
        $height = (int) ($dskConfig['height'] ?? 1080);
        $fps = (int) ($dskConfig['fps'] ?? 30);

        if ($template === null || ! is_file($template)) {
            throw new RuntimeException('DSK template path is required for overlay composition.');
        }

        $overlayMov = sys_get_temp_dir().'/ffmix_dsk_stream_'.uniqid('', true).'.mov';
        $dskRender = $this->dsk->renderHtmlToAlphaMov(
            htmlPath: $template,
            outputMovPath: $overlayMov,
            title: $title,
            subtitle: $subtitle,
            accent: $accent,
            duration: $duration,
            fps: $fps,
            width: $width,
            height: $height,
        );

        $this->dsk->applyOverlay($videoPath, $overlayMov, $outputPath, $startAt, $duration);

        @unlink($overlayMov);

        return [
            'overlay_path' => $overlayMov,
            'output_path' => $outputPath,
            'dsk_frames' => $dskRender['frames'],
        ];
    }

    /**
     * Build FFmpeg command for live HLS output from a source file with optional overlay.
     *
     * @param  array<string, mixed>|null  $overlay  keys: path, x, y, opacity, start, end
     * @return list<string>
     */
    public function buildHlsLiveArgs(string $sourcePath, string $outputDir, ?array $overlay = null, int $listSize = 10): array
    {
        return $this->hls->buildHlsLiveCommand($sourcePath, $outputDir, $overlay, '4', $listSize);
    }

    /**
     * Run a full HLS record + DSK overlay pipeline.
     *
     * @param  array<string, mixed>  $config  keys: stream_url, output_path, record_seconds, resolution_index, dsk (array)
     * @return array{output_path: string, duration: int, dsk_frames?: int}
     */
    public function runStreamRecord(array $config): array
    {
        $streamUrl = (string) ($config['stream_url'] ?? '');
        if ($streamUrl === '') {
            throw new RuntimeException('stream_url is required.');
        }

        $outputPath = (string) ($config['output_path'] ?? sys_get_temp_dir().'/ffmix_record_'.uniqid('', true).'.mp4');
        $recordSeconds = (int) ($config['record_seconds'] ?? 60);
        $resolutionIndex = $config['resolution_index'] ?? null;
        $dskConfig = $config['dsk'] ?? [];

        $outDir = dirname($outputPath);
        if (! is_dir($outDir)) {
            @mkdir($outDir, 0775, true);
        }

        $tmpVideo = $outputPath.'.raw.mp4';

        // Record raw HLS stream
        $args = $this->buildRecordStreamArgs($streamUrl, $tmpVideo, $recordSeconds, $resolutionIndex);
        $result = Process::timeout($recordSeconds + 60)->run($args);

        if (! $result->successful()) {
            throw new RuntimeException('HLS stream recording failed: '.trim($result->errorOutput() ?: $result->output()));
        }

        // Apply DSK overlay if configured
        if (! empty($dskConfig) && isset($dskConfig['template_path'])) {
            $final = $this->applyDskToStream($tmpVideo, $outputPath, $dskConfig);
            @unlink($tmpVideo);

            return [
                'output_path' => $final['output_path'],
                'duration' => $recordSeconds,
                'dsk_frames' => $final['dsk_frames'] ?? 0,
            ];
        }

        @rename($tmpVideo, $outputPath);

        return [
            'output_path' => $outputPath,
            'duration' => $recordSeconds,
        ];
    }
}