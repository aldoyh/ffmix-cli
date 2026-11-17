<?php

namespace App\Services\Ffmix;

use App\Enums\SubtitleMode;

class PipelineConfig
{
    /**
     * @param  array{exposure: float, contrast: float, saturation: float, gamma: float, vibrance: float, shadows: float, highlights: float, red: float, green: float, blue: float, temperature: int, colorspace: string, lut_slug: ?string, lut_file: ?string}  $color
     * @param  array{enabled: bool, mode: string, width: ?int, height: ?int, x: int, y: int}  $crop
     * @param  list<array{path: string, x: string, y: string, width: ?int, height: ?int, opacity: float, start: ?float, end: ?float}>  $overlays
     * @param  array{mode: SubtitleMode, path: ?string, language: string}  $subtitles
     * @param  list<string>  $videoFilters
     * @param  array{volume: float, fade_in: float, fade_out: float, earwax: bool, tempo: float, bs2b: bool, channel_map: ?string, dynaudnorm: bool, loudnorm: bool, exciter: bool, sample_rate: ?int}  $audioFilters
     * @param  array<string, string|int|float|bool|null>  $advancedOptions
     */
    public function __construct(
        public string $container = 'mp4',
        public string $videoCodec = 'h264',
        public string $audioCodec = 'aac',
        public bool $hwAccel = false,
        public string $resolution = 'source',
        public int $crf = 20,
        public ?string $videoBitrate = null,
        public string $audioBitrate = '192k',
        public ?string $trimStart = null,
        public ?string $trimEnd = null,
        public array $color = [
            'exposure' => 0.0,
            'contrast' => 1.0,
            'saturation' => 1.0,
            'gamma' => 1.0,
            'vibrance' => 0.0,
            'shadows' => 0.0,
            'highlights' => 0.0,
            'red' => 0.0,
            'green' => 0.0,
            'blue' => 0.0,
            'temperature' => 6500,
            'colorspace' => 'auto',
            'lut_slug' => null,
            'lut_file' => null,
        ],
        public array $crop = [
            'enabled' => false,
            'mode' => 'manual',
            'width' => null,
            'height' => null,
            'x' => 0,
            'y' => 0,
        ],
        public array $overlays = [],
        public array $subtitles = [
            'mode' => SubtitleMode::None,
            'path' => null,
            'language' => 'eng',
        ],
        public array $videoFilters = [],
        public array $audioFilters = [
            'volume' => 1.0,
            'fade_in' => 0.0,
            'fade_out' => 0.0,
            'earwax' => false,
            'tempo' => 1.0,
            'bs2b' => false,
            'channel_map' => null,
            'dynaudnorm' => false,
            'loudnorm' => false,
            'exciter' => false,
            'sample_rate' => null,
        ],
        public array $advancedOptions = [],
        public ?int $proresProfile = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $defaults = new self;

        $colorInput = is_array($data['color'] ?? null) ? $data['color'] : [];
        $color = [
            'exposure' => (float) ($colorInput['exposure'] ?? $defaults->color['exposure']),
            'contrast' => (float) ($colorInput['contrast'] ?? $defaults->color['contrast']),
            'saturation' => (float) ($colorInput['saturation'] ?? $defaults->color['saturation']),
            'gamma' => (float) ($colorInput['gamma'] ?? $defaults->color['gamma']),
            'vibrance' => (float) ($colorInput['vibrance'] ?? $defaults->color['vibrance']),
            'shadows' => (float) ($colorInput['shadows'] ?? $defaults->color['shadows']),
            'highlights' => (float) ($colorInput['highlights'] ?? $defaults->color['highlights']),
            'red' => (float) ($colorInput['red'] ?? $defaults->color['red']),
            'green' => (float) ($colorInput['green'] ?? $defaults->color['green']),
            'blue' => (float) ($colorInput['blue'] ?? $defaults->color['blue']),
            'temperature' => (int) ($colorInput['temperature'] ?? $defaults->color['temperature']),
            'colorspace' => (string) ($colorInput['colorspace'] ?? ($data['colorspace'] ?? $defaults->color['colorspace'])),
            'lut_slug' => isset($colorInput['lut_slug']) ? (string) $colorInput['lut_slug'] : null,
            'lut_file' => isset($colorInput['lut_file']) ? (string) $colorInput['lut_file'] : null,
        ];

        $cropInput = is_array($data['crop'] ?? null) ? $data['crop'] : [];
        $crop = [
            'enabled' => (bool) ($cropInput['enabled'] ?? false),
            'mode' => (string) ($cropInput['mode'] ?? 'manual'),
            'width' => isset($cropInput['width']) ? (int) $cropInput['width'] : null,
            'height' => isset($cropInput['height']) ? (int) $cropInput['height'] : null,
            'x' => (int) ($cropInput['x'] ?? 0),
            'y' => (int) ($cropInput['y'] ?? 0),
        ];

        $overlays = [];
        if (is_array($data['overlays'] ?? null)) {
            foreach ($data['overlays'] as $overlay) {
                if (! is_array($overlay) || empty($overlay['path'])) {
                    continue;
                }
                $overlays[] = [
                    'path' => (string) $overlay['path'],
                    'x' => (string) ($overlay['x'] ?? '20'),
                    'y' => (string) ($overlay['y'] ?? '20'),
                    'width' => isset($overlay['width']) ? (int) $overlay['width'] : null,
                    'height' => isset($overlay['height']) ? (int) $overlay['height'] : null,
                    'opacity' => (float) ($overlay['opacity'] ?? 1.0),
                    'start' => isset($overlay['start']) ? (float) $overlay['start'] : null,
                    'end' => isset($overlay['end']) ? (float) $overlay['end'] : null,
                ];
            }
        }

        $subInput = is_array($data['subtitles'] ?? null) ? $data['subtitles'] : [];
        $subModeRaw = $subInput['mode'] ?? SubtitleMode::None->value;
        $subMode = $subModeRaw instanceof SubtitleMode
            ? $subModeRaw
            : (SubtitleMode::tryFrom((string) $subModeRaw) ?? SubtitleMode::None);

        $subtitles = [
            'mode' => $subMode,
            'path' => isset($subInput['path']) && $subInput['path'] !== '' ? (string) $subInput['path'] : null,
            'language' => (string) ($subInput['language'] ?? 'eng'),
        ];

        $videoFilters = [];
        if (is_array($data['video_filters'] ?? null)) {
            foreach ($data['video_filters'] as $vf) {
                if (is_string($vf) && $vf !== '') {
                    $videoFilters[] = $vf;
                }
            }
        }

        $audioInput = is_array($data['audio_filters'] ?? null) ? $data['audio_filters'] : [];
        $audioFilters = [
            'volume' => (float) ($audioInput['volume'] ?? 1.0),
            'fade_in' => (float) ($audioInput['fade_in'] ?? 0.0),
            'fade_out' => (float) ($audioInput['fade_out'] ?? 0.0),
            'earwax' => (bool) ($audioInput['earwax'] ?? false),
            'tempo' => (float) ($audioInput['tempo'] ?? 1.0),
            'bs2b' => (bool) ($audioInput['bs2b'] ?? false),
            'channel_map' => isset($audioInput['channel_map']) && $audioInput['channel_map'] !== '' ? (string) $audioInput['channel_map'] : null,
            'dynaudnorm' => (bool) ($audioInput['dynaudnorm'] ?? false),
            'loudnorm' => (bool) ($audioInput['loudnorm'] ?? false),
            'exciter' => (bool) ($audioInput['exciter'] ?? false),
            'sample_rate' => isset($audioInput['sample_rate']) ? (int) $audioInput['sample_rate'] : null,
        ];

        $advancedOptions = [];
        if (is_array($data['advanced_options'] ?? null)) {
            foreach ($data['advanced_options'] as $flag => $val) {
                if (is_string($flag) && $flag !== '' && (is_scalar($val) || $val === null)) {
                    $advancedOptions[$flag] = $val;
                }
            }
        }

        return new self(
            container: (string) ($data['container'] ?? $defaults->container),
            videoCodec: (string) ($data['video_codec'] ?? $defaults->videoCodec),
            audioCodec: (string) ($data['audio_codec'] ?? $defaults->audioCodec),
            hwAccel: (bool) ($data['hw_accel'] ?? $defaults->hwAccel),
            resolution: (string) ($data['resolution'] ?? $defaults->resolution),
            crf: (int) ($data['crf'] ?? $defaults->crf),
            videoBitrate: isset($data['video_bitrate']) && $data['video_bitrate'] !== '' ? (string) $data['video_bitrate'] : null,
            audioBitrate: (string) ($data['audio_bitrate'] ?? $defaults->audioBitrate),
            trimStart: isset($data['trim_start']) && $data['trim_start'] !== '' ? (string) $data['trim_start'] : null,
            trimEnd: isset($data['trim_end']) && $data['trim_end'] !== '' ? (string) $data['trim_end'] : null,
            color: $color,
            crop: $crop,
            overlays: $overlays,
            subtitles: $subtitles,
            videoFilters: $videoFilters,
            audioFilters: $audioFilters,
            advancedOptions: $advancedOptions,
            proresProfile: isset($data['prores_profile']) ? (int) $data['prores_profile'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'container' => $this->container,
            'video_codec' => $this->videoCodec,
            'audio_codec' => $this->audioCodec,
            'hw_accel' => $this->hwAccel,
            'resolution' => $this->resolution,
            'crf' => $this->crf,
            'video_bitrate' => $this->videoBitrate,
            'audio_bitrate' => $this->audioBitrate,
            'trim_start' => $this->trimStart,
            'trim_end' => $this->trimEnd,
            'color' => $this->color,
            'crop' => $this->crop,
            'overlays' => $this->overlays,
            'subtitles' => [
                'mode' => $this->subtitles['mode']->value,
                'path' => $this->subtitles['path'],
                'language' => $this->subtitles['language'],
            ],
            'video_filters' => $this->videoFilters,
            'audio_filters' => $this->audioFilters,
            'advanced_options' => $this->advancedOptions,
            'prores_profile' => $this->proresProfile,
        ];
    }
}
