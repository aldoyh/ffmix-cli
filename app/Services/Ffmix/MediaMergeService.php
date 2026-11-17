<?php

namespace App\Services\Ffmix;

use App\Enums\MergeMode;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class MediaMergeService
{
    /**
     * Build the FFmpeg merge command for unlimited input files (passthru or complex filter).
     *
     * @param  list<string>  $inputs
     * @return array{args: list<string>, manifest_path: ?string}
     */
    public function buildCommand(
        array $inputs,
        string $outputPath,
        MergeMode $mode = MergeMode::Passthru,
        int $width = 1920,
        int $height = 1080,
        bool $createManifest = true,
    ): array {
        if (count($inputs) < 2) {
            throw new RuntimeException('At least 2 input media files are required to merge.');
        }

        $ffmpegBin = (string) config('ffmix.binaries.ffmpeg', 'ffmpeg');

        if ($mode === MergeMode::Passthru) {
            $manifestPath = sys_get_temp_dir().'/ffmix_concat_'.uniqid('', true).'.txt';
            if ($createManifest) {
                $lines = array_map(
                    static fn (string $path): string => "file '".str_replace("'", "'\\''", $path)."'",
                    $inputs,
                );
                file_put_contents($manifestPath, implode("\n", $lines)."\n");
            }

            return [
                'args' => [
                    $ffmpegBin,
                    '-hide_banner',
                    '-y',
                    '-f',
                    'concat',
                    '-safe',
                    '0',
                    '-i',
                    $manifestPath,
                    '-c',
                    'copy',
                    $outputPath,
                ],
                'manifest_path' => $manifestPath,
            ];
        }

        // Complex filter mode: normalize dimensions & SAR across arbitrary sources before concat
        $args = [$ffmpegBin, '-hide_banner', '-y'];
        $filterParts = [];
        $concatInputs = '';

        foreach ($inputs as $idx => $path) {
            $args[] = '-i';
            $args[] = $path;
            $filterParts[] = "[{$idx}:v]scale={$width}:{$height}:force_original_aspect_ratio=decrease,pad={$width}:{$height}:(ow-iw)/2:(oh-ih)/2,setsar=1,format=yuv420p[v{$idx}]";
            $filterParts[] = "[{$idx}:a]aformat=sample_rates=48000:channel_layouts=stereo[a{$idx}]";
            $concatInputs .= "[v{$idx}][a{$idx}]";
        }

        $n = count($inputs);
        $filterParts[] = "{$concatInputs}concat=n={$n}:v=1:a=1[vout][aout]";

        $args[] = '-filter_complex';
        $args[] = implode(';', $filterParts);
        $args[] = '-map';
        $args[] = '[vout]';
        $args[] = '-map';
        $args[] = '[aout]';
        $args[] = '-c:v';
        $args[] = 'libx264';
        $args[] = '-crf';
        $args[] = '20';
        $args[] = '-c:a';
        $args[] = 'aac';
        $args[] = '-b:a';
        $args[] = '192k';
        $args[] = $outputPath;

        return [
            'args' => $args,
            'manifest_path' => null,
        ];
    }

    /**
     * @param  list<string>  $inputs
     * @return array{output: string, count: int, mode: string, command: string}
     */
    public function merge(array $inputs, string $outputPath, MergeMode $mode = MergeMode::Passthru): array
    {
        $built = $this->buildCommand($inputs, $outputPath, $mode);

        try {
            $result = Process::timeout(600)->run($built['args']);
            if (! $result->successful()) {
                throw new RuntimeException('Media merge failed: '.trim($result->errorOutput() ?: $result->output()));
            }
        } finally {
            if ($built['manifest_path'] !== null && is_file($built['manifest_path'])) {
                @unlink($built['manifest_path']);
            }
        }

        return [
            'output' => $outputPath,
            'count' => count($inputs),
            'mode' => $mode->value,
            'command' => implode(' ', array_map('escapeshellarg', $built['args'])),
        ];
    }
}
