<?php

namespace App\Services\Ffmix;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class DownstreamKeyService
{
    public function ensureSampleTemplate(?string $folder = null): string
    {
        $dir = $this->resolveFolder($folder);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $target = rtrim($dir, '/').'/sample-lower-third.html';
        $source = resource_path('dsk/sample-lower-third.html');

        if (! is_file($target) && is_file($source)) {
            copy($source, $target);
        }

        return $target;
    }

    /**
     * Discover animated HTML files in the Downstream Key folder.
     *
     * @return list<array{filename: string, path: string, title: string, description: string}>
     */
    public function listTemplates(?string $folder = null): array
    {
        $dir = $this->resolveFolder($folder);
        $this->ensureSampleTemplate($dir);

        $files = glob(rtrim($dir, '/').'/*.{html,htm}', GLOB_BRACE) ?: [];
        sort($files);

        $templates = [];
        foreach ($files as $path) {
            if (! is_file($path)) {
                continue;
            }
            $content = (string) file_get_contents($path);
            $title = pathinfo($path, PATHINFO_FILENAME);
            if (preg_match('/<title>(.*?)<\/title>/is', $content, $m) === 1) {
                $title = trim($m[1]);
            }
            $desc = 'Animated HTML Downstream Key';
            if (preg_match('/<meta[^>]+name=["\']dsk-description["\'][^>]+content=["\']([^"\']+)["\']/is', $content, $m) === 1) {
                $desc = trim($m[1]);
            }

            $templates[] = [
                'filename' => basename($path),
                'path' => $path,
                'title' => $title,
                'description' => $desc,
            ];
        }

        return $templates;
    }

    /**
     * Render an animated HTML lower third into a transparent ProRes 4444 alpha-channel .mov overlay.
     *
     * @return array{overlay_path: string, frames: int, duration: float, fps: int}
     */
    public function renderHtmlToAlphaMov(
        string $htmlPath,
        string $outputMovPath,
        string $title = 'ffMix Live Broadcast',
        string $subtitle = 'Normalized 1080p30 Feed',
        string $accent = '#06b6d4',
        float $duration = 6.0,
        int $fps = 30,
        int $width = 1920,
        int $height = 1080,
    ): array {
        if (! is_file($htmlPath)) {
            throw new RuntimeException("DSK HTML template not found: {$htmlPath}");
        }

        $script = resource_path('scripts/render-dsk.mjs');
        if (! is_file($script)) {
            throw new RuntimeException("DSK renderer script missing: {$script}");
        }

        $framesDir = sys_get_temp_dir().'/ffmix_dsk_frames_'.uniqid('', true);
        mkdir($framesDir, 0775, true);

        try {
            $renderResult = Process::timeout(180)->run([
                'node',
                $script,
                '--html='.$htmlPath,
                '--outDir='.$framesDir,
                '--duration='.(string) $duration,
                '--fps='.(string) $fps,
                '--width='.(string) $width,
                '--height='.(string) $height,
                '--title='.$title,
                '--subtitle='.$subtitle,
                '--accent='.$accent,
            ]);

            if (! $renderResult->successful()) {
                throw new RuntimeException('HTML DSK render failed: '.trim($renderResult->errorOutput() ?: $renderResult->output()));
            }

            $outDir = dirname($outputMovPath);
            if (! is_dir($outDir)) {
                @mkdir($outDir, 0775, true);
            }

            $ffmpegBin = (string) config('ffmix.binaries.ffmpeg', 'ffmpeg');
            $encodeResult = Process::timeout(180)->run([
                $ffmpegBin,
                '-hide_banner',
                '-y',
                '-framerate',
                (string) $fps,
                '-i',
                $framesDir.'/frame_%04d.png',
                '-c:v',
                'prores_ks',
                '-profile:v',
                '4444',
                '-pix_fmt',
                'yuva444p10le',
                $outputMovPath,
            ]);

            if (! $encodeResult->successful()) {
                throw new RuntimeException('Alpha MOV encode failed: '.trim($encodeResult->errorOutput() ?: $encodeResult->output()));
            }

            $frameFiles = glob($framesDir.'/frame_*.png') ?: [];

            return [
                'overlay_path' => $outputMovPath,
                'frames' => count($frameFiles),
                'duration' => $duration,
                'fps' => $fps,
            ];
        } finally {
            array_map('unlink', glob($framesDir.'/*.png') ?: []);
            @rmdir($framesDir);
        }
    }

    /**
     * Build FFmpeg command to composite an alpha-channel DSK lower-third overlay onto a video at startAt.
     *
     * @return list<string>
     */
    public function buildApplyOverlayCommand(
        string $videoPath,
        string $overlayMovPath,
        string $outputPath,
        float $startAt = 1.0,
        float $duration = 6.0,
    ): array {
        $ffmpegBin = (string) config('ffmix.binaries.ffmpeg', 'ffmpeg');
        $endAt = $startAt + max(0.5, $duration);

        return [
            $ffmpegBin,
            '-hide_banner',
            '-y',
            '-i',
            $videoPath,
            '-itsoffset',
            (string) $startAt,
            '-i',
            $overlayMovPath,
            '-filter_complex',
            sprintf("[0:v][1:v]overlay=0:0:eof_action=pass:enable='between(t,%.2f,%.2f)'[vout]", $startAt, $endAt),
            '-map',
            '[vout]',
            '-map',
            '0:a?',
            '-c:v',
            'libx264',
            '-pix_fmt',
            'yuv420p',
            '-crf',
            '19',
            '-c:a',
            'copy',
            '-movflags',
            '+faststart',
            $outputPath,
        ];
    }

    public function applyOverlay(
        string $videoPath,
        string $overlayMovPath,
        string $outputPath,
        float $startAt = 1.0,
        float $duration = 6.0,
    ): void {
        $args = $this->buildApplyOverlayCommand($videoPath, $overlayMovPath, $outputPath, $startAt, $duration);
        $result = Process::timeout(600)->run($args);

        if (! $result->successful()) {
            throw new RuntimeException('DSK composite failed: '.trim($result->errorOutput() ?: $result->output()));
        }
    }

    public function resolveFolder(?string $folder = null): string
    {
        $path = $folder !== null && $folder !== '' ? $folder : 'storage/app/ffmix/dsk';
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return base_path($path);
    }
}
