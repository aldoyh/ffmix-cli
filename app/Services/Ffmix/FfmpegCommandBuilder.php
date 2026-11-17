<?php

namespace App\Services\Ffmix;

use App\Enums\SubtitleMode;

class FfmpegCommandBuilder
{
    public function __construct(private CatalogService $catalog) {}

    /**
     * Build the full FFmpeg command argument list.
     *
     * @return list<string>
     */
    public function build(
        string $inputPath,
        string $outputPath,
        PipelineConfig $config,
        bool $withProgress = true,
    ): array {
        $ffmpegBin = (string) config('ffmix.binaries.ffmpeg', 'ffmpeg');
        $args = [$ffmpegBin, '-hide_banner', '-y'];

        if ($withProgress) {
            $args[] = '-progress';
            $args[] = 'pipe:1';
            $args[] = '-nostats';
        }

        if ($config->hwAccel && $config->videoCodec !== 'copy') {
            $args[] = '-hwaccel';
            $args[] = 'videotoolbox';
        }

        if ($config->trimStart !== null && $config->trimStart !== '') {
            $args[] = '-ss';
            $args[] = $config->trimStart;
        }

        if ($config->trimEnd !== null && $config->trimEnd !== '') {
            $args[] = '-to';
            $args[] = $config->trimEnd;
        }

        $args[] = '-i';
        $args[] = $inputPath;

        // Additional inputs for unlimited overlays (images or PiP videos)
        foreach ($config->overlays as $overlay) {
            $args[] = '-i';
            $args[] = $overlay['path'];
        }

        // Soft external subtitle input
        $hasSoftSubtitleFile = $config->subtitles['mode'] === SubtitleMode::Soft
            && $config->subtitles['path'] !== null;
        $subInputIndex = 1 + count($config->overlays);

        if ($hasSoftSubtitleFile) {
            $args[] = '-i';
            $args[] = (string) $config->subtitles['path'];
        }

        $videoChain = $this->buildVideoFilterChain($config);
        $audioChain = $this->buildAudioFilterChain($config);

        if ($config->overlays !== [] && $config->videoCodec !== 'copy') {
            $filterComplex = $this->buildOverlayFilterComplex($videoChain, $config->overlays);
            $args[] = '-filter_complex';
            $args[] = $filterComplex;
            $args[] = '-map';
            $args[] = '[vout]';
            $args[] = '-map';
            $args[] = '0:a?';

            if ($hasSoftSubtitleFile) {
                $args[] = '-map';
                $args[] = "{$subInputIndex}:0";
            } else {
                $args[] = '-map';
                $args[] = '0:s?';
            }
        } else {
            if ($videoChain !== [] && $config->videoCodec !== 'copy') {
                $args[] = '-vf';
                $args[] = implode(',', $videoChain);
            }

            if ($hasSoftSubtitleFile) {
                $args[] = '-map';
                $args[] = '0:v:0';
                $args[] = '-map';
                $args[] = '0:a?';
                $args[] = '-map';
                $args[] = "{$subInputIndex}:0";
            }
        }

        if ($audioChain !== [] && $config->audioCodec !== 'copy') {
            $args[] = '-af';
            $args[] = implode(',', $audioChain);
        }

        // Video encoder selection
        $videoEncoder = $this->resolveVideoEncoder($config);
        $args[] = '-c:v';
        $args[] = $videoEncoder;

        if ($config->videoCodec !== 'copy' && $config->videoCodec !== 'gif') {
            $this->appendVideoRateControl($args, $config, $videoEncoder);
        }

        // Audio encoder selection
        $audioEncoder = $this->resolveAudioEncoder($config);
        if ($config->container === 'gif') {
            $args[] = '-an';
        } else {
            $args[] = '-c:a';
            $args[] = $audioEncoder;

            if ($config->audioCodec !== 'copy' && ! in_array($config->audioCodec, ['wav', 'flac', 'alac'], true)) {
                $args[] = '-b:a';
                $args[] = $config->audioBitrate;
            }

            if ($config->audioFilters['sample_rate'] !== null) {
                $args[] = '-ar';
                $args[] = (string) $config->audioFilters['sample_rate'];
            }
        }

        // Subtitles handling
        if ($config->subtitles['mode'] === SubtitleMode::Soft) {
            $subCodec = in_array($config->container, ['mp4', 'mov', 'm4a'], true) ? 'mov_text' : 'srt';
            $args[] = '-c:s';
            $args[] = $subCodec;
            $args[] = '-metadata:s:s:0';
            $args[] = 'language='.$config->subtitles['language'];
        } elseif ($config->subtitles['mode'] === SubtitleMode::None && $hasSoftSubtitleFile === false) {
            // Preserve embedded subtitles only when explicitly requested; otherwise keep default
        }

        if (in_array($config->container, ['mp4', 'mov'], true)) {
            $args[] = '-movflags';
            $args[] = '+faststart';
        }

        // Append advanced user options (+450 catalog)
        foreach ($config->advancedOptions as $flag => $value) {
            $normalizedFlag = str_starts_with($flag, '-') ? $flag : '-'.$flag;
            $args[] = $normalizedFlag;
            if ($value !== null && $value !== true && $value !== '') {
                $args[] = (string) $value;
            }
        }

        $args[] = $outputPath;

        return $args;
    }

    /**
     * Render a shell-escaped single-line command preview.
     *
     * @param  list<string>  $args
     */
    public function toShellString(array $args): string
    {
        return implode(' ', array_map(static function (string $arg): string {
            if ($arg === '' || preg_match('/[^A-Za-z0-9_\-\.\/:=,+@]/', $arg) === 1) {
                return escapeshellarg($arg);
            }

            return $arg;
        }, $args));
    }

    /**
     * @return list<string>
     */
    public function buildVideoFilterChain(PipelineConfig $config): array
    {
        $filters = [];

        // 1. Cropping (manual or auto-detected)
        if ($config->crop['enabled'] && $config->crop['width'] !== null && $config->crop['height'] !== null) {
            $filters[] = sprintf(
                'crop=%d:%d:%d:%d',
                $config->crop['width'],
                $config->crop['height'],
                $config->crop['x'],
                $config->crop['y'],
            );
        }

        // 2. Color adjustments (exposure, eq, colorbalance, vibrance, temperature, colorspace, LUT)
        $color = $config->color;
        if (abs($color['exposure']) > 0.001) {
            $filters[] = sprintf('exposure=exposure=%.2f', $color['exposure']);
        }

        if (
            abs($color['contrast'] - 1.0) > 0.001
            || abs($color['saturation'] - 1.0) > 0.001
            || abs($color['gamma'] - 1.0) > 0.001
        ) {
            $filters[] = sprintf(
                'eq=contrast=%.2f:saturation=%.2f:gamma=%.2f',
                $color['contrast'],
                $color['saturation'],
                $color['gamma'],
            );
        }

        if (abs($color['vibrance']) > 0.001) {
            $filters[] = sprintf('vibrance=intensity=%.2f', $color['vibrance']);
        }

        if (
            abs($color['red']) > 0.001
            || abs($color['green']) > 0.001
            || abs($color['blue']) > 0.001
            || abs($color['shadows']) > 0.001
            || abs($color['highlights']) > 0.001
        ) {
            $filters[] = sprintf(
                'colorbalance=rs=%.2f:gs=%.2f:bs=%.2f:rm=%.2f:gm=%.2f:bm=%.2f:rh=%.2f:gh=%.2f:bh=%.2f',
                $color['red'] + $color['shadows'],
                $color['green'] + $color['shadows'],
                $color['blue'] + $color['shadows'],
                $color['red'],
                $color['green'],
                $color['blue'],
                $color['red'] + $color['highlights'],
                $color['green'] + $color['highlights'],
                $color['blue'] + $color['highlights'],
            );
        }

        if ($color['temperature'] !== 6500) {
            $filters[] = sprintf('colortemperature=temperature=%d', $color['temperature']);
        }

        if ($color['lut_file'] !== null && $color['lut_file'] !== '') {
            $escapedLut = str_replace(["'", ':'], ["\\'", '\\:'], $color['lut_file']);
            $filters[] = "lut3d=file='{$escapedLut}'";
        } elseif ($color['lut_slug'] !== null && $color['lut_slug'] !== '') {
            $lut = $this->catalog->findLut($color['lut_slug']);
            if ($lut !== null) {
                $filters[] = $lut['filter'];
            }
        }

        /** @var array<string, array{label: string, filter: ?string}> $colorspaces */
        $colorspaces = config('ffmix.colorspaces', []);
        $csFilter = $colorspaces[$color['colorspace']]['filter'] ?? null;
        if (is_string($csFilter) && $csFilter !== '') {
            $filters[] = $csFilter;
        }

        // 3. Resolution scaling (4K, 5K, 8K, 1080p, 720p)
        /** @var array<string, array{label: string, width: ?int, height: ?int}> $resolutions */
        $resolutions = config('ffmix.resolutions', []);
        $res = $resolutions[$config->resolution] ?? null;
        if (is_array($res) && $res['width'] !== null) {
            $filters[] = sprintf('scale=%d:-2:flags=lanczos', $res['width']);
        }

        // 4. Hardburned subtitles
        if ($config->subtitles['mode'] === SubtitleMode::Hardburn && $config->subtitles['path'] !== null) {
            $escapedSub = str_replace(["'", ':'], ["\\'", '\\:'], $config->subtitles['path']);
            $filters[] = "subtitles='{$escapedSub}'";
        }

        // 5. Additional user-selected video filters from +60 catalog
        foreach ($config->videoFilters as $vf) {
            $filters[] = $vf;
        }

        // 6. Standard yuv420p compatibility for H.264/H.265/MP4 when not ProRes
        if ($config->videoCodec !== 'prores' && $config->videoCodec !== 'gif' && $config->videoCodec !== 'copy') {
            $filters[] = 'format=yuv420p';
        }

        return $filters;
    }

    /**
     * @return list<string>
     */
    public function buildAudioFilterChain(PipelineConfig $config): array
    {
        $af = $config->audioFilters;
        $filters = [];

        if (abs($af['volume'] - 1.0) > 0.001) {
            $filters[] = sprintf('volume=%.2f', $af['volume']);
        }

        if ($af['fade_in'] > 0.0) {
            $filters[] = sprintf('afade=t=in:st=0:d=%.2f', $af['fade_in']);
        }

        if ($af['fade_out'] > 0.0) {
            $filters[] = sprintf('afade=t=out:st=0:d=%.2f', $af['fade_out']);
        }

        if ($af['earwax']) {
            $filters[] = 'earwax';
        }

        if (abs($af['tempo'] - 1.0) > 0.001) {
            $filters[] = sprintf('atempo=%.2f', $af['tempo']);
        }

        if ($af['bs2b']) {
            $filters[] = 'bs2b=profile=cmoy';
        }

        if ($af['channel_map'] !== null && $af['channel_map'] !== '') {
            $filters[] = 'channelmap='.$af['channel_map'];
        }

        if ($af['dynaudnorm']) {
            $filters[] = 'dynaudnorm=f=500:g=31';
        }

        if ($af['loudnorm']) {
            $filters[] = 'loudnorm=I=-14:TP=-1.0:LRA=11';
        }

        if ($af['exciter']) {
            $filters[] = 'aexciter=level_in=1:level_out=1:amount=2:drive=8.5';
        }

        return $filters;
    }

    /**
     * Chain unlimited overlays on top of the main video filter chain.
     *
     * @param  list<string>  $videoChain
     * @param  list<array{path: string, x: string, y: string, width: ?int, height: ?int, opacity: float, start: ?float, end: ?float}>  $overlays
     */
    private function buildOverlayFilterComplex(array $videoChain, array $overlays): string
    {
        $parts = [];
        $currentLabel = '0:v';

        if ($videoChain !== []) {
            $parts[] = '[0:v]'.implode(',', $videoChain).'[vbase]';
            $currentLabel = 'vbase';
        }

        $total = count($overlays);
        foreach ($overlays as $index => $overlay) {
            $inputStream = ($index + 1).':v';
            $prepLabel = "ov{$index}";
            $prepFilters = ['format=rgba'];

            if ($overlay['width'] !== null || $overlay['height'] !== null) {
                $w = $overlay['width'] ?? -1;
                $h = $overlay['height'] ?? -1;
                $prepFilters[] = "scale={$w}:{$h}";
            }

            if ($overlay['opacity'] < 0.999) {
                $prepFilters[] = sprintf('colorchannelmixer=aa=%.2f', max(0.0, min(1.0, $overlay['opacity'])));
            }

            $parts[] = "[{$inputStream}]".implode(',', $prepFilters)."[{$prepLabel}]";

            $outLabel = ($index === $total - 1) ? 'vout' : "vstep{$index}";
            $overlayExpr = sprintf('overlay=x=%s:y=%s', $overlay['x'], $overlay['y']);

            if ($overlay['start'] !== null && $overlay['end'] !== null) {
                $overlayExpr .= sprintf(":enable='between(t,%.2f,%.2f)'", $overlay['start'], $overlay['end']);
            }

            $parts[] = "[{$currentLabel}][{$prepLabel}]{$overlayExpr}[{$outLabel}]";
            $currentLabel = $outLabel;
        }

        return implode(';', $parts);
    }

    private function resolveVideoEncoder(PipelineConfig $config): string
    {
        /** @var array<string, array{label: string, sw: string, hw: ?string}> $codecs */
        $codecs = config('ffmix.video_codecs', []);
        $entry = $codecs[$config->videoCodec] ?? null;

        if ($entry === null) {
            return $config->videoCodec;
        }

        if ($config->hwAccel && $entry['hw'] !== null) {
            return $entry['hw'];
        }

        return $entry['sw'];
    }

    private function resolveAudioEncoder(PipelineConfig $config): string
    {
        /** @var array<string, array{label: string, sw: string, hw: ?string}> $codecs */
        $codecs = config('ffmix.audio_codecs', []);
        $entry = $codecs[$config->audioCodec] ?? null;

        if ($entry === null) {
            return $config->audioCodec;
        }

        if ($config->hwAccel && $entry['hw'] !== null) {
            return $entry['hw'];
        }

        return $entry['sw'];
    }

    /**
     * @param  list<string>  $args
     */
    private function appendVideoRateControl(array &$args, PipelineConfig $config, string $videoEncoder): void
    {
        if (str_contains($videoEncoder, 'prores')) {
            $args[] = '-profile:v';
            $args[] = (string) ($config->proresProfile ?? 3);

            return;
        }

        if (str_ends_with($videoEncoder, '_videotoolbox')) {
            $args[] = '-b:v';
            $args[] = $config->videoBitrate ?? '8M';

            return;
        }

        if ($config->videoBitrate !== null) {
            $args[] = '-b:v';
            $args[] = $config->videoBitrate;

            return;
        }

        if (in_array($videoEncoder, ['libx264', 'libx265', 'libsvtav1', 'libvpx-vp9'], true)) {
            $args[] = '-crf';
            $args[] = (string) $config->crf;
        }
    }
}
