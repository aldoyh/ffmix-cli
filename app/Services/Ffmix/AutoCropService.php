<?php

namespace App\Services\Ffmix;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class AutoCropService
{
    /**
     * Detect black borders / active picture rectangle using FFmpeg cropdetect.
     *
     * @return array{width: int, height: int, x: int, y: int, filter: string}
     */
    public function detect(string $inputPath, int $limit = 24, int $round = 2, float $sampleSeconds = 2.0): array
    {
        $ffmpegBin = (string) config('ffmix.binaries.ffmpeg', 'ffmpeg');

        $result = Process::run([
            $ffmpegBin,
            '-hide_banner',
            '-t',
            (string) $sampleSeconds,
            '-i',
            $inputPath,
            '-vf',
            "cropdetect={$limit}:{$round}:0",
            '-f',
            'null',
            '-',
        ]);

        $output = $result->errorOutput()."\n".$result->output();

        $matches = [];
        preg_match_all('/crop=(\d+):(\d+):(\d+):(\d+)/', $output, $matches, PREG_SET_ORDER);

        if ($matches === []) {
            throw new RuntimeException('Could not detect crop geometry from input media.');
        }

        // Choose the most frequent crop rectangle across sampled frames
        $counts = [];
        $parsed = [];
        foreach ($matches as $match) {
            $key = "{$match[1]}:{$match[2]}:{$match[3]}:{$match[4]}";
            $counts[$key] = ($counts[$key] ?? 0) + 1;
            $parsed[$key] = [
                'width' => (int) $match[1],
                'height' => (int) $match[2],
                'x' => (int) $match[3],
                'y' => (int) $match[4],
                'filter' => "crop={$key}",
            ];
        }

        arsort($counts);
        $bestKey = (string) array_key_first($counts);

        return $parsed[$bestKey];
    }
}
