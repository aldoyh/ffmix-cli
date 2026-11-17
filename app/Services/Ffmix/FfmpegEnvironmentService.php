<?php

namespace App\Services\Ffmix;

use Illuminate\Support\Facades\Process;

class FfmpegEnvironmentService
{
    /**
     * @return array{
     *   available: bool,
     *   version: string,
     *   ffmpeg_bin: string,
     *   ffprobe_bin: string,
     *   videotoolbox: bool,
     *   libvmaf: bool,
     *   encoders: list<string>,
     * }
     */
    public function inspect(): array
    {
        $ffmpegBin = (string) config('ffmix.binaries.ffmpeg', 'ffmpeg');
        $ffprobeBin = (string) config('ffmix.binaries.ffprobe', 'ffprobe');

        $versionResult = Process::run([$ffmpegBin, '-version']);
        if (! $versionResult->successful()) {
            return [
                'available' => false,
                'version' => 'Not installed',
                'ffmpeg_bin' => $ffmpegBin,
                'ffprobe_bin' => $ffprobeBin,
                'videotoolbox' => false,
                'libvmaf' => false,
                'encoders' => [],
            ];
        }

        $output = $versionResult->output();
        $firstLine = strtok($output, "\n") ?: 'ffmpeg';
        $version = $firstLine;
        if (preg_match('/ffmpeg version ([^\s]+)/', $firstLine, $matches) === 1) {
            $version = $matches[1];
        }

        $videotoolbox = str_contains($output, '--enable-videotoolbox');
        $libvmaf = str_contains($output, '--enable-libvmaf');

        $encoders = [];
        foreach (['libx264', 'libx265', 'libsvtav1', 'h264_videotoolbox', 'hevc_videotoolbox', 'prores_videotoolbox'] as $enc) {
            $encCheck = Process::run([$ffmpegBin, '-hide_banner', '-h', "encoder={$enc}"]);
            if ($encCheck->successful() && ! str_contains($encCheck->output().$encCheck->errorOutput(), 'is not recognized')) {
                $encoders[] = $enc;
            }
        }

        return [
            'available' => true,
            'version' => $version,
            'ffmpeg_bin' => $ffmpegBin,
            'ffprobe_bin' => $ffprobeBin,
            'videotoolbox' => $videotoolbox,
            'libvmaf' => $libvmaf,
            'encoders' => $encoders,
        ];
    }
}
