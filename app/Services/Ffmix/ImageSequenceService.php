<?php

namespace App\Services\Ffmix;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class ImageSequenceService
{
    /**
     * Build FFmpeg command to export a movie into individual images (.png, .bmp, .jpg, .tiff, .dpx, .sgi, .tga).
     *
     * @return list<string>
     */
    public function buildExportImagesCommand(
        string $inputPath,
        string $outputPattern,
        string $format = 'png',
        ?float $fps = null,
    ): array {
        $supported = config('ffmix.image_formats', []);
        $format = mb_strtolower($format);
        if (! in_array($format, $supported, true)) {
            throw new RuntimeException("Unsupported image format '{$format}'. Supported: ".implode(', ', $supported));
        }

        $ffmpegBin = (string) config('ffmix.binaries.ffmpeg', 'ffmpeg');
        $args = [$ffmpegBin, '-hide_banner', '-y', '-i', $inputPath];

        if ($fps !== null && $fps > 0.0) {
            $args[] = '-vf';
            $args[] = "fps={$fps}";
        }

        if ($format === 'jpg') {
            $args[] = '-qscale:v';
            $args[] = '2';
        }

        $args[] = $outputPattern;

        return $args;
    }

    /**
     * Build FFmpeg command to assemble an image sequence into a movie.
     *
     * @return list<string>
     */
    public function buildImagesToMovieCommand(
        string $inputPattern,
        string $outputPath,
        float $fps = 24.0,
        string $videoCodec = 'libx264',
        int $startNumber = 1,
    ): array {
        $ffmpegBin = (string) config('ffmix.binaries.ffmpeg', 'ffmpeg');

        return [
            $ffmpegBin,
            '-hide_banner',
            '-y',
            '-framerate',
            (string) $fps,
            '-start_number',
            (string) $startNumber,
            '-i',
            $inputPattern,
            '-c:v',
            $videoCodec,
            '-pix_fmt',
            'yuv420p',
            '-crf',
            '18',
            $outputPath,
        ];
    }

    /**
     * @param  list<string>  $args
     */
    public function execute(array $args): string
    {
        $result = Process::timeout(300)->run($args);

        if (! $result->successful()) {
            throw new RuntimeException('Image sequence command failed: '.trim($result->errorOutput() ?: $result->output()));
        }

        return $result->output();
    }
}
