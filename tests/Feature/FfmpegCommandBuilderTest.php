<?php

use App\Enums\SubtitleMode;
use App\Services\Ffmix\FfmpegCommandBuilder;
use App\Services\Ffmix\PipelineConfig;

it('compiles Apple VideoToolbox hardware accelerated 4K HEVC command', function (): void {
    /** @var FfmpegCommandBuilder $builder */
    $builder = app(FfmpegCommandBuilder::class);

    $config = PipelineConfig::fromArray([
        'container' => 'mp4',
        'video_codec' => 'h265',
        'audio_codec' => 'aac',
        'hw_accel' => true,
        'resolution' => '4k',
        'audio_bitrate' => '256k',
    ]);

    $args = $builder->build('/media/in.mov', '/media/out.mp4', $config, true);
    $cmd = $builder->toShellString($args);

    expect($cmd)
        ->toContain('-hwaccel videotoolbox')
        ->toContain('-c:v hevc_videotoolbox')
        ->toContain('-c:a aac_at')
        ->toContain('scale=3840:-2:flags=lanczos')
        ->toContain('-movflags +faststart')
        ->toContain('/media/out.mp4');
});

it('compiles color adjustments, LUT preset, crop, 5K/8K scale, and trimming', function (): void {
    /** @var FfmpegCommandBuilder $builder */
    $builder = app(FfmpegCommandBuilder::class);

    $config = PipelineConfig::fromArray([
        'container' => 'mkv',
        'video_codec' => 'h264',
        'audio_codec' => 'flac',
        'resolution' => '8k',
        'crf' => 18,
        'trim_start' => '00:00:05',
        'trim_end' => '00:00:45',
        'color' => [
            'exposure' => 0.4,
            'contrast' => 1.15,
            'saturation' => 1.2,
            'gamma' => 0.95,
            'vibrance' => 0.2,
            'shadows' => 0.05,
            'highlights' => -0.05,
            'red' => 0.02,
            'green' => 0.0,
            'blue' => -0.02,
            'temperature' => 5600,
            'colorspace' => 'bt709',
            'lut_slug' => 'kodak-2383-d65',
        ],
        'crop' => [
            'enabled' => true,
            'mode' => 'manual',
            'width' => 1920,
            'height' => 800,
            'x' => 0,
            'y' => 140,
        ],
    ]);

    $args = $builder->build('source.mov', 'graded.mkv', $config, false);
    $cmd = $builder->toShellString($args);

    expect($cmd)
        ->toContain('-ss 00:00:05')
        ->toContain('-to 00:00:45')
        ->toContain('crop=1920:800:0:140')
        ->toContain('exposure=exposure=0.40')
        ->toContain('eq=contrast=1.15:saturation=1.20:gamma=0.95')
        ->toContain('vibrance=intensity=0.20')
        ->toContain('colortemperature=temperature=5600')
        ->toContain('colorspace=all=bt709')
        ->toContain('scale=7680:-2:flags=lanczos')
        ->toContain('-c:v libx264')
        ->toContain('-crf 18')
        ->toContain('-c:a flac');
});

it('compiles unlimited overlays, subtitles, audio DSP filters, and advanced options', function (): void {
    /** @var FfmpegCommandBuilder $builder */
    $builder = app(FfmpegCommandBuilder::class);

    $config = PipelineConfig::fromArray([
        'container' => 'mp4',
        'video_codec' => 'h264',
        'audio_codec' => 'aac',
        'overlays' => [
            ['path' => 'logo.png', 'x' => 'W-w-20', 'y' => '20', 'width' => 160, 'opacity' => 0.85],
            ['path' => 'pip.mp4', 'x' => '20', 'y' => 'H-h-20', 'width' => 320, 'height' => 180, 'opacity' => 1.0, 'start' => 2.0, 'end' => 10.0],
        ],
        'subtitles' => [
            'mode' => SubtitleMode::Soft->value,
            'path' => 'captions.srt',
            'language' => 'eng',
        ],
        'audio_filters' => [
            'volume' => 1.25,
            'fade_in' => 1.5,
            'fade_out' => 2.0,
            'earwax' => true,
            'tempo' => 1.1,
            'bs2b' => true,
            'channel_map' => '0|1:stereo',
            'dynaudnorm' => true,
            'loudnorm' => true,
            'exciter' => true,
            'sample_rate' => 48000,
        ],
        'advanced_options' => [
            '-g' => '48',
            '-tune' => 'film',
        ],
    ]);

    $args = $builder->build('main.mp4', 'final.mp4', $config, false);
    $cmd = $builder->toShellString($args);

    expect($cmd)
        ->toContain('-filter_complex')
        ->toContain('[ov0]')
        ->toContain('[ov1]')
        ->toContain('between(t,2.00,10.00)')
        ->toContain('-c:s mov_text')
        ->toContain('volume=1.25')
        ->toContain('earwax')
        ->toContain('bs2b=profile=cmoy')
        ->toContain('dynaudnorm=')
        ->toContain('loudnorm=I=-14')
        ->toContain('aexciter=')
        ->toContain('-ar 48000')
        ->toContain('-g 48')
        ->toContain('-tune film');
});
