<?php

namespace App\Services\Ffmix;

use App\Models\NormalizedAsset;
use App\Models\ShowPreset;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class FolderWatchdogService
{
    public function __construct(private MediaProbeService $probe) {}

    /**
     * Scan the active Show's input folder and normalize any new/updated videos, images, or slates to uniform MP4s.
     *
     * @return list<NormalizedAsset>
     */
    public function scanAndNormalize(?ShowPreset $show = null, bool $dryRun = false): array
    {
        $show ??= ShowPreset::activeOrDefault();
        $cfg = $show->config;

        /** @var array<string, mixed> $watchdogCfg */
        $watchdogCfg = is_array($cfg['watchdog'] ?? null) ? $cfg['watchdog'] : [];
        /** @var array<string, mixed> $normCfg */
        $normCfg = is_array($cfg['normalize'] ?? null) ? $cfg['normalize'] : [];

        $inputDir = $this->resolvePath((string) ($watchdogCfg['input_dir'] ?? 'storage/app/ffmix/ingest'));
        $outputDir = $this->resolvePath((string) ($watchdogCfg['normalized_dir'] ?? 'storage/app/ffmix/normalized'));

        if (! is_dir($inputDir)) {
            @mkdir($inputDir, 0775, true);
        }
        if (! is_dir($outputDir)) {
            @mkdir($outputDir, 0775, true);
        }

        /** @var list<string> $videoExts */
        $videoExts = config('ffmix.watchdog.video_extensions', ['mp4', 'mov', 'mkv', 'avi', 'mxf', 'ts', 'webm']);
        /** @var list<string> $imageExts */
        $imageExts = config('ffmix.watchdog.image_extensions', ['png', 'jpg', 'jpeg', 'bmp', 'tiff', 'tif', 'webp', 'tga']);

        $entries = scandir($inputDir) ?: [];
        $processed = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $sourcePath = rtrim($inputDir, '/').'/'.$entry;
            if (! is_file($sourcePath)) {
                continue;
            }

            $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            $isVideo = in_array($ext, $videoExts, true);
            $isImage = in_array($ext, $imageExts, true);

            if (! $isVideo && ! $isImage) {
                continue;
            }

            $assetType = $isImage
                ? (str_contains(strtolower($entry), 'slate') ? 'slate' : 'image')
                : 'video';

            $mtime = (int) (@filemtime($sourcePath) ?: 0);
            $size = (int) (@filesize($sourcePath) ?: 0);
            $baseName = pathinfo($entry, PATHINFO_FILENAME);
            $normalizedPath = rtrim($outputDir, '/').'/'.$baseName.'_norm.mp4';

            $existing = NormalizedAsset::query()->where('source_path', $sourcePath)->first();
            if (
                $existing !== null
                && $existing->status === 'ready'
                && $existing->source_mtime === $mtime
                && $existing->source_size === $size
                && is_file($existing->normalized_path)
            ) {
                continue;
            }

            $asset = NormalizedAsset::query()->updateOrCreate(
                ['source_path' => $sourcePath],
                [
                    'show_preset_id' => $show->id,
                    'normalized_path' => $normalizedPath,
                    'asset_type' => $assetType,
                    'source_mtime' => $mtime,
                    'source_size' => $size,
                    'status' => $dryRun ? 'pending' : 'normalizing',
                    'error_message' => null,
                ],
            );

            if ($dryRun) {
                $processed[] = $asset;

                continue;
            }

            try {
                $hasAudio = $isVideo ? $this->hasAudioStream($sourcePath) : false;
                $args = $this->buildNormalizeCommand(
                    sourcePath: $sourcePath,
                    outputPath: $normalizedPath,
                    isImage: $isImage,
                    hasAudioStream: $hasAudio,
                    width: (int) ($normCfg['width'] ?? 1920),
                    height: (int) ($normCfg['height'] ?? 1080),
                    fps: (int) ($normCfg['fps'] ?? 30),
                    slateDuration: (float) ($normCfg['slate_duration'] ?? 5.0),
                    hwAccel: (bool) ($normCfg['hw_accel'] ?? false),
                    audioBitrate: (string) ($normCfg['audio_bitrate'] ?? '192k'),
                );

                $result = Process::timeout(600)->run($args);
                if (! $result->successful()) {
                    throw new RuntimeException(trim($result->errorOutput() ?: $result->output()));
                }

                $duration = $isImage ? (float) ($normCfg['slate_duration'] ?? 5.0) : $this->safeProbeDuration($normalizedPath);

                $asset->update([
                    'status' => 'ready',
                    'duration_seconds' => $duration,
                    'normalized_at' => now(),
                ]);
            } catch (\Throwable $e) {
                $asset->update([
                    'status' => 'failed',
                    'error_message' => mb_substr($e->getMessage(), 0, 1000),
                ]);
            }

            $processed[] = $asset->fresh() ?? $asset;
        }

        return $processed;
    }

    /**
     * Build a deterministic FFmpeg command that normalizes any video or image/slate into a uniform H.264/AAC MP4.
     *
     * @return list<string>
     */
    public function buildNormalizeCommand(
        string $sourcePath,
        string $outputPath,
        bool $isImage,
        bool $hasAudioStream,
        int $width = 1920,
        int $height = 1080,
        int $fps = 30,
        float $slateDuration = 5.0,
        bool $hwAccel = false,
        string $audioBitrate = '192k',
    ): array {
        $ffmpegBin = (string) config('ffmix.binaries.ffmpeg', 'ffmpeg');
        $args = [$ffmpegBin, '-hide_banner', '-y'];

        $vf = "scale={$width}:{$height}:force_original_aspect_ratio=decrease,pad={$width}:{$height}:(ow-iw)/2:(oh-ih)/2,setsar=1,fps={$fps},format=yuv420p";

        if ($isImage) {
            $args[] = '-loop';
            $args[] = '1';
            $args[] = '-t';
            $args[] = (string) $slateDuration;
            $args[] = '-i';
            $args[] = $sourcePath;

            // Inject silent stereo AAC track so slates/images concatenate & xfade identically to videos
            $args[] = '-f';
            $args[] = 'lavfi';
            $args[] = '-t';
            $args[] = (string) $slateDuration;
            $args[] = '-i';
            $args[] = 'anullsrc=channel_layout=stereo:sample_rate=48000';

            $args[] = '-vf';
            $args[] = $vf;
            $args[] = '-map';
            $args[] = '0:v:0';
            $args[] = '-map';
            $args[] = '1:a:0';
            $args[] = '-shortest';
        } elseif (! $hasAudioStream) {
            $args[] = '-i';
            $args[] = $sourcePath;
            $args[] = '-f';
            $args[] = 'lavfi';
            $args[] = '-i';
            $args[] = 'anullsrc=channel_layout=stereo:sample_rate=48000';
            $args[] = '-vf';
            $args[] = $vf;
            $args[] = '-map';
            $args[] = '0:v:0';
            $args[] = '-map';
            $args[] = '1:a:0';
            $args[] = '-shortest';
        } else {
            $args[] = '-i';
            $args[] = $sourcePath;
            $args[] = '-vf';
            $args[] = $vf;
            $args[] = '-af';
            $args[] = 'aresample=48000,aformat=sample_rates=48000:channel_layouts=stereo';
        }

        $videoEncoder = $hwAccel ? 'h264_videotoolbox' : 'libx264';
        $args[] = '-c:v';
        $args[] = $videoEncoder;

        if ($hwAccel) {
            $args[] = '-b:v';
            $args[] = '8M';
        } else {
            $args[] = '-crf';
            $args[] = '19';
            $args[] = '-preset';
            $args[] = 'fast';
        }

        $args[] = '-c:a';
        $args[] = 'aac';
        $args[] = '-b:a';
        $args[] = $audioBitrate;
        $args[] = '-ar';
        $args[] = '48000';
        $args[] = '-ac';
        $args[] = '2';
        $args[] = '-movflags';
        $args[] = '+faststart';
        $args[] = $outputPath;

        return $args;
    }

    /**
     * Launch the background watchdog daemon (non-blocking) and record its PID.
     */
    public function startBackgroundDaemon(int $intervalSeconds = 3): int
    {
        $status = $this->daemonStatus();
        if ($status['running'] && $status['pid'] !== null) {
            return $status['pid'];
        }

        $pidFile = $this->pidFilePath();
        $logFile = storage_path('logs/ffmix-watchdog.log');
        $dir = dirname($pidFile);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $artisan = base_path('artisan');
        $cmd = sprintf(
            'nohup %s %s ffmix:watchdog run --interval=%d > %s 2>&1 & echo $!',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($artisan),
            max(1, $intervalSeconds),
            escapeshellarg($logFile),
        );

        $pidOut = trim((string) shell_exec($cmd));
        $pid = (int) $pidOut;
        if ($pid > 0) {
            file_put_contents($pidFile, (string) $pid);
        }

        return $pid;
    }

    public function stopBackgroundDaemon(): bool
    {
        $status = $this->daemonStatus();
        $pidFile = $this->pidFilePath();

        if ($status['running'] && $status['pid'] !== null) {
            @posix_kill($status['pid'], 15);
            @unlink($pidFile);

            return true;
        }

        if (is_file($pidFile)) {
            @unlink($pidFile);
        }

        return false;
    }

    /**
     * @return array{running: bool, pid: ?int}
     */
    public function daemonStatus(): array
    {
        $pidFile = $this->pidFilePath();
        if (! is_file($pidFile)) {
            return ['running' => false, 'pid' => null];
        }

        $pid = (int) trim((string) file_get_contents($pidFile));
        if ($pid <= 0) {
            return ['running' => false, 'pid' => null];
        }

        $alive = function_exists('posix_kill') ? @posix_kill($pid, 0) : true;
        if (! $alive) {
            @unlink($pidFile);

            return ['running' => false, 'pid' => null];
        }

        return ['running' => true, 'pid' => $pid];
    }

    public function resolvePath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return base_path($path);
    }

    private function pidFilePath(): string
    {
        return storage_path('app/ffmix/watchdog.pid');
    }

    private function hasAudioStream(string $path): bool
    {
        try {
            $info = $this->probe->probe($path);

            return $info['audio'] !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    private function safeProbeDuration(string $path): float
    {
        try {
            return $this->probe->probe($path)['duration'];
        } catch (\Throwable) {
            return 0.0;
        }
    }
}
