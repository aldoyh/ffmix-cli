<?php

namespace App\Services\Ffmix;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class MediaProbeService
{
    /**
     * Probe media file streams, format, duration, resolution, codec, and colorspace.
     *
     * @return array{
     *   path: string,
     *   format_name: string,
     *   duration: float,
     *   size_bytes: int,
     *   bitrate_bps: int,
     *   video: ?array{
     *     codec: string,
     *     width: int,
     *     height: int,
     *     pix_fmt: string,
     *     fps: float,
     *     color_space: string,
     *     bitrate_bps: int
     *   },
     *   audio: ?array{
     *     codec: string,
     *     sample_rate: int,
     *     channels: int,
     *     channel_layout: string,
     *     bitrate_bps: int
     *   },
     *   subtitles_count: int,
     *   streams: list<array<string, mixed>>
     * }
     */
    public function probe(string $path): array
    {
        $ffprobeBin = (string) config('ffmix.binaries.ffprobe', 'ffprobe');

        $result = Process::run([
            $ffprobeBin,
            '-v',
            'quiet',
            '-print_format',
            'json',
            '-show_format',
            '-show_streams',
            $path,
        ]);

        if (! $result->successful()) {
            throw new RuntimeException('ffprobe failed: '.trim($result->errorOutput() ?: $result->output()));
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($result->output(), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Unable to parse ffprobe JSON output.');
        }

        /** @var array<string, mixed> $format */
        $format = is_array($decoded['format'] ?? null) ? $decoded['format'] : [];
        /** @var list<array<string, mixed>> $streams */
        $streams = is_array($decoded['streams'] ?? null) ? array_values($decoded['streams']) : [];

        $video = null;
        $audio = null;
        $subtitlesCount = 0;

        foreach ($streams as $stream) {
            $type = (string) ($stream['codec_type'] ?? '');
            if ($type === 'video' && $video === null) {
                $video = [
                    'codec' => (string) ($stream['codec_name'] ?? 'unknown'),
                    'width' => (int) ($stream['width'] ?? 0),
                    'height' => (int) ($stream['height'] ?? 0),
                    'pix_fmt' => (string) ($stream['pix_fmt'] ?? 'unknown'),
                    'fps' => $this->parseFraction((string) ($stream['avg_frame_rate'] ?? $stream['r_frame_rate'] ?? '0')),
                    'color_space' => (string) ($stream['color_space'] ?? 'unknown'),
                    'bitrate_bps' => (int) ($stream['bit_rate'] ?? 0),
                ];
            } elseif ($type === 'audio' && $audio === null) {
                $audio = [
                    'codec' => (string) ($stream['codec_name'] ?? 'unknown'),
                    'sample_rate' => (int) ($stream['sample_rate'] ?? 0),
                    'channels' => (int) ($stream['channels'] ?? 0),
                    'channel_layout' => (string) ($stream['channel_layout'] ?? 'unknown'),
                    'bitrate_bps' => (int) ($stream['bit_rate'] ?? 0),
                ];
            } elseif ($type === 'subtitle') {
                $subtitlesCount++;
            }
        }

        return [
            'path' => $path,
            'format_name' => (string) ($format['format_name'] ?? 'unknown'),
            'duration' => (float) ($format['duration'] ?? 0.0),
            'size_bytes' => (int) ($format['size'] ?? 0),
            'bitrate_bps' => (int) ($format['bit_rate'] ?? 0),
            'video' => $video,
            'audio' => $audio,
            'subtitles_count' => $subtitlesCount,
            'streams' => $streams,
        ];
    }

    private function parseFraction(string $fraction): float
    {
        if (str_contains($fraction, '/')) {
            [$num, $den] = explode('/', $fraction, 2);
            $denominator = (float) $den;

            return $denominator > 0.0 ? round(((float) $num) / $denominator, 3) : 0.0;
        }

        return round((float) $fraction, 3);
    }
}
