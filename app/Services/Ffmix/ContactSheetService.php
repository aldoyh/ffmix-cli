<?php

namespace App\Services\Ffmix;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class ContactSheetService
{
    public function __construct(private MediaProbeService $probe) {}

    /**
     * Build FFmpeg command to generate a thumbnail contact sheet image.
     *
     * @return list<string>
     */
    public function buildCommand(
        string $inputPath,
        string $outputPath,
        int $cols = 4,
        int $rows = 4,
        int $thumbWidth = 320,
        bool $timestamps = true,
        ?float $durationSeconds = null,
    ): array {
        $ffmpegBin = (string) config('ffmix.binaries.ffmpeg', 'ffmpeg');
        $totalFrames = max(1, $cols * $rows);

        if ($durationSeconds === null || $durationSeconds <= 0.0) {
            try {
                $info = $this->probe->probe($inputPath);
                $durationSeconds = max(1.0, $info['duration']);
            } catch (RuntimeException) {
                $durationSeconds = 16.0;
            }
        }

        $interval = max(0.2, $durationSeconds / ($totalFrames + 1));
        $fpsExpr = sprintf('fps=1/%.4f', $interval);

        $filters = [
            $fpsExpr,
            "scale={$thumbWidth}:-2:flags=lanczos",
        ];

        if ($timestamps) {
            $filters[] = "drawtext=text='%{pts\\:hms}':x=8:y=h-24:fontsize=16:fontcolor=white:box=1:boxcolor=black@0.6";
        }

        $filters[] = "tile={$cols}x{$rows}:padding=6:margin=8:color=0x111827";

        return [
            $ffmpegBin,
            '-hide_banner',
            '-y',
            '-i',
            $inputPath,
            '-frames:v',
            '1',
            '-vf',
            implode(',', $filters),
            $outputPath,
        ];
    }

    /**
     * Generate the contact sheet image file.
     *
     * @return array{output: string, cols: int, rows: int, command: string}
     */
    public function generate(
        string $inputPath,
        string $outputPath,
        int $cols = 4,
        int $rows = 4,
        int $thumbWidth = 320,
        bool $timestamps = true,
    ): array {
        $args = $this->buildCommand($inputPath, $outputPath, $cols, $rows, $thumbWidth, $timestamps);
        $result = Process::timeout(180)->run($args);

        if (! $result->successful()) {
            throw new RuntimeException('Contact sheet generation failed: '.trim($result->errorOutput() ?: $result->output()));
        }

        return [
            'output' => $outputPath,
            'cols' => $cols,
            'rows' => $rows,
            'command' => implode(' ', array_map('escapeshellarg', $args)),
        ];
    }
}
