<?php

namespace App\Services\Ffmix;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * HLS (HTTP Live Streaming) stream handling: playlist parsing, stream copy
 * remux, HLS output generation, and quality ladder management.
 */
class HlsStreamService
{
    /** Parse an HLS master playlist and return available quality levels. */
    public function probePlaylist(string $playlistUrl, int $timeoutSeconds = 15): array
    {
        $result = Process::timeout($timeoutSeconds)->run([
            'curl', '-sSL', '--max-time', (string) $timeoutSeconds, '-o', '-', $playlistUrl,
        ]);

        if (! $result->successful()) {
            throw new RuntimeException('Failed to fetch HLS playlist: '.$playlistUrl);
        }

        $content = (string) $result->output();
        if (! str_starts_with(trim($content), '#EXTM3U')) {
            throw new RuntimeException('Invalid HLS playlist returned for: '.$playlistUrl);
        }

        $parsedUrl = parse_url($playlistUrl);
        $scheme = $parsedUrl['scheme'] ?? 'https';
        $host = $parsedUrl['host'] ?? '';
        $port = $parsedUrl['port'] ?? null;
        // Base path is the DIRECTORY of the playlist, not including the playlist filename
        $basePath = (string) ($parsedUrl['path'] ?? '');
        $base = ltrim(dirname($basePath) === '.' ? '' : dirname($basePath).'/', '/');

        $qualities = [];

        preg_match_all(
            '/#EXT-X-STREAM-INF:([^\r\n]*)\r?\n([^\r\n]+)/',
            $content,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $streamInf = $match[1];
            $variant = trim($match[2]);

            $bandwidth = 0;
            if (preg_match('/BANDWIDTH=(\d+)/', $streamInf, $b) === 1) {
                $bandwidth = (int) $b[1];
            }

            $resolution = null;
            if (preg_match('/RESOLUTION=(\d+)x(\d+)/', $streamInf, $r) === 1) {
                $resolution = $r[1].'x'.$r[2];
            }

            $codec = null;
            if (preg_match('/CODECS="([^"]+)"/', $streamInf, $c) === 1) {
                $codec = $c[1];
            }

            $variantUrl = $this->resolveVariantUrl($scheme, $host, $port, $base, $variant);

            $qualities[] = [
                'bandwidth' => $bandwidth,
                'resolution' => $resolution,
                'codec' => $codec,
                'url' => $variantUrl,
            ];
        }

        if ($qualities === []) {
            $qualities[] = [
                'bandwidth' => 0,
                'resolution' => null,
                'codec' => null,
                'url' => $playlistUrl,
            ];
        }

        // Sort by bandwidth descending (best first)
        usort($qualities, static fn (array $a, array $b): int => $b['bandwidth'] <=> $a['bandwidth']);

        return [
            'stream_url' => $playlistUrl,
            'qualities' => $qualities,
        ];
    }

    /** Probe a media stream / playlist for codec, resolution, duration, fps. */
    public function probeMediaStream(string $streamUrl, int $timeoutSeconds = 15): array
    {
        $ffprobeBin = (string) config('ffmix.binaries.ffprobe', 'ffprobe');
        $result = Process::timeout($timeoutSeconds)->run([
            $ffprobeBin,
            '-v', 'error',
            '-show_streams',
            '-show_format',
            '-of', 'json',
            '-rw_timeout', '5000000',
            '-timeout', (string) $timeoutSeconds,
            '-i', $streamUrl,
        ]);

        if (! $result->successful()) {
            throw new RuntimeException('FFprobe failed on HLS stream: '.$streamUrl);
        }

        $json = json_decode((string) $result->output(), true);
        $streams = $json['streams'] ?? [];

        $video = null;
        $audio = null;
        foreach ($streams as $stream) {
            if (($stream['codec_type'] ?? null) === 'video') {
                $video = $stream;
            } elseif (($stream['codec_type'] ?? null) === 'audio') {
                $audio = $stream;
            }
        }

        $duration = null;
        if ($video !== null && isset($video['duration'])) {
            $duration = (float) $video['duration'];
        }

        $fps = 25.0;
        if ($video !== null && isset($video['r_frame_rate']) && $video['r_frame_rate'] !== '0/0') {
            $parts = explode('/', $video['r_frame_rate']);
            if ($parts[1] ?? null !== null && $parts[1] !== '0') {
                $fps = (float) $parts[0] / (float) $parts[1];
            }
        }

        return [
            'codec' => $video['codec_name'] ?? null,
            'width' => $video['width'] ?? null,
            'height' => $video['height'] ?? null,
            'duration' => $duration,
            'frame_rate' => $fps,
            'audio_codec' => $audio['codec_name'] ?? null,
        ];
    }

    /** Build FFmpeg arguments to remux an HLS stream into a local MP4 file. */
    public function buildRecordCommand(string $streamUrl, string $outputPath, ?int $resolutionIndex = null, int $recordSeconds = 60): array
    {
        $ffmpegBin = (string) config('ffmix.binaries.ffmpeg', 'ffmpeg');

        $inputUrl = $streamUrl;
        if ($resolutionIndex !== null && $resolutionIndex > 0) {
            $playlist = $this->probePlaylist($streamUrl, 15);
            $best = $playlist['qualities'][$resolutionIndex - 1] ?? null;
            if ($best !== null) {
                $inputUrl = $best['url'];
            }
        }

        return [
            $ffmpegBin,
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

    /** Build FFmpeg arguments to generate HLS output from an input file. */
    public function buildHlsOutputCommand(string $inputPath, string $outputDir, string $segmentName = 'segment', int $segmentDuration = 4, int $listDuration = 0): array
    {
        $ffmpegBin = (string) config('ffmix.binaries.ffmpeg', 'ffmpeg');

        return [
            $ffmpegBin,
            '-hide_banner', '-y',
            '-i', $inputPath,
            '-c', 'copy',
            '-f', 'hls',
            '-hls_segment_duration', (string) $segmentDuration,
            '-hls_list_size', (string) $listDuration,
            $outputDir.'/'.$segmentName.'.m3u8',
        ];
    }

    /** Build FFmpeg arguments to generate an HLS stream from a live source with optional overlay. */
    public function buildHlsLiveCommand(
        string $sourcePath,
        string $outputDir,
        ?array $overlay = null,
        string $segmentDuration = '4',
        int $listSize = 10,
    ): array {
        $ffmpegBin = (string) config('ffmix.binaries.ffmpeg', 'ffmpeg');

        $args = [$ffmpegBin, '-hide_banner', '-y'];

        $args[] = '-i';
        $args[] = $sourcePath;

        if ($overlay !== null && is_file($overlay['path'])) {
            $args[] = '-i';
            $args[] = $overlay['path'];

            $x = $overlay['x'] ?? '(W-w)/2';
            $y = $overlay['y'] ?? '(H-h)/2';
            $opacity = max(0.0, min(1.0, $overlay['opacity'] ?? 1.0));
            $start = $overlay['start'] ?? null;
            $end = $overlay['end'] ?? null;

            $vf = sprintf(
                '[0:v][1:v]overlay=x=%s:y=%s%s',
                $x,
                $y,
                ($start !== null && $end !== null)
                    ? sprintf(':enable=\'between(t,%.2f,%.2f)\'', $start, $end)
                    : ''
            );

            $args[] = '-filter_complex';
            $args[] = $vf;
            $args[] = '-map';
            $args[] = '[vout]';
            $args[] = '-map';
            $args[] = '0:a?';
        }

        $args[] = '-c:v';
        $args[] = 'libx264';
        $args[] = '-preset';
        $args[] = 'fast';
        $args[] = '-crf';
        $args[] = '20';
        $args[] = '-c:a';
        $args[] = 'aac';
        $args[] = '-b:a';
        $args[] = '128k';
        $args[] = '-f';
        $args[] = 'hls';
        $args[] = '-hls_segment_duration';
        $args[] = $segmentDuration;
        $args[] = '-hls_list_size';
        $args[] = (string) $listSize;
        $args[] = '-hls_flags';
        $args[] = 'delete_segments';
        $args[] = $outputDir.'/stream.m3u8';

        return $args;
    }

    /** Resolve a variant URL from an HLS playlist. */
    private function resolveVariantUrl(string $scheme, string $host, ?int $port, string $base, string $variant): string
    {
        if (str_starts_with($variant, 'http://') || str_starts_with($variant, 'https://')) {
            return $variant;
        }

        $authority = $host;
        if ($port !== null) {
            $authority .= ':'.$port;
        }

        $variantPath = ltrim($variant, '/');
        $basePath = rtrim($base, '/');

        $prefix = $basePath !== '' ? $basePath.'/' : '';

        return sprintf('%s://%s/%s%s', $scheme, $authority, $prefix, $variantPath);
    }
}
