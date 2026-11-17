<?php

namespace App\Tui;

use App\Enums\DashboardTab;
use App\Enums\ThemeMode;
use App\Models\ShowPreset;
use App\Services\Ffmix\PipelineConfig;

class DashboardState
{
    public DashboardTab $tab = DashboardTab::Queue;

    public ThemeMode $theme = ThemeMode::Dark;

    /** @var array<string, int> */
    public array $selectedIndex = [
        'queue' => 0,
        'studio' => 0,
        'fx' => 0,
        'audio' => 0,
        'analyzer' => 0,
        'tools' => 0,
        'dropzones' => 0,
        'monitor' => 0,
        'show' => 0,
    ];

    public string $activePresetSlug = 'desktop';

    public string $activeShowSlug = 'main-broadcast';

    public string $activeShowName = 'Main Broadcast Live';

    public string $activeTransition = 'logo_sting';

    public float $transitionDuration = 1.0;

    public ?string $transitionStingAsset = null;

    public string $watchdogInputDir = 'storage/app/ffmix/ingest';

    public string $watchdogNormalizedDir = 'storage/app/ffmix/normalized';

    public int $normalizeWidth = 1920;

    public int $normalizeHeight = 1080;

    public int $normalizeFps = 30;

    public float $slateDuration = 5.0;

    public string $dskFolder = 'storage/app/ffmix/dsk';

    public string $dskTemplate = 'sample-lower-third.html';

    public string $dskTitle = 'ffMix Live Broadcast';

    public string $dskSubtitle = 'Normalized 1080p30 Feed • Studio A';

    public string $dskAccent = '#06b6d4';

    public float $dskDuration = 6.0;

    public float $dskStartAt = 1.0;

    public PipelineConfig $pipeline;

    public string $sampleInputPath = 'input.mov';

    public string $sampleOutputPath = 'output.mp4';

    public ?string $modal = null;

    public string $modalPrompt = '';

    public string $modalInput = '';

    public int $modalCursor = 0;

    public string $searchQuery = '';

    public string $flashMessage = 'Ready — [?] Help  [1-9] Tabs  [S] Show Presets  [w] Watchdog';

    /** @var array<string, mixed>|null */
    public ?array $lastProbe = null;

    /** @var array{
     *   available: bool,
     *   version: string,
     *   ffmpeg_bin: string,
     *   ffprobe_bin: string,
     *   videotoolbox: bool,
     *   libvmaf: bool,
     *   encoders: list<string>,
     * } */
    public array $environment = [
        'available' => false,
        'version' => 'unknown',
        'ffmpeg_bin' => 'ffmpeg',
        'ffprobe_bin' => 'ffprobe',
        'videotoolbox' => false,
        'libvmaf' => false,
        'encoders' => [],
    ];

    /**
     * Tool form state for Tab 6 (Contact sheet / Merge / Images).
     *
     * @var array{
     *   contact_input: string,
     *   contact_output: string,
     *   contact_cols: int,
     *   contact_rows: int,
     *   merge_inputs: string,
     *   merge_output: string,
     *   merge_mode: string,
     *   export_img_input: string,
     *   export_img_pattern: string,
     *   export_img_format: string,
     *   seq_input: string,
     *   seq_output: string,
     *   seq_fps: int,
     * }
     */
    public array $toolsConfig = [
        'contact_input' => 'input.mp4',
        'contact_output' => 'contact_sheet.png',
        'contact_cols' => 4,
        'contact_rows' => 4,
        'merge_inputs' => 'part1.mp4,part2.mp4',
        'merge_output' => 'merged.mp4',
        'merge_mode' => 'passthru',
        'export_img_input' => 'input.mp4',
        'export_img_pattern' => 'frames/frame_%04d.png',
        'export_img_format' => 'png',
        'seq_input' => 'frames/frame_%04d.png',
        'seq_output' => 'sequence.mp4',
        'seq_fps' => 24,
    ];

    public function __construct(?ThemeMode $theme = null)
    {
        $defaultTheme = ThemeMode::tryFrom((string) config('ffmix.default_theme', 'dark')) ?? ThemeMode::Dark;
        $this->theme = $theme ?? $defaultTheme;
        $this->pipeline = new PipelineConfig;
    }

    public function applyShowPreset(ShowPreset $show): void
    {
        $cfg = $show->config;
        $this->activeShowSlug = $show->slug;
        $this->activeShowName = (string) ($cfg['show_name'] ?? $show->name);

        if (is_array($cfg['normalize'] ?? null)) {
            $n = $cfg['normalize'];
            $this->normalizeWidth = (int) ($n['width'] ?? 1920);
            $this->normalizeHeight = (int) ($n['height'] ?? 1080);
            $this->normalizeFps = (int) ($n['fps'] ?? 30);
            $this->slateDuration = (float) ($n['slate_duration'] ?? 5.0);
        }

        if (is_array($cfg['watchdog'] ?? null)) {
            $w = $cfg['watchdog'];
            $this->watchdogInputDir = (string) ($w['input_dir'] ?? $this->watchdogInputDir);
            $this->watchdogNormalizedDir = (string) ($w['normalized_dir'] ?? $this->watchdogNormalizedDir);
        }

        if (is_array($cfg['dsk'] ?? null)) {
            $d = $cfg['dsk'];
            $this->dskFolder = (string) ($d['folder'] ?? $this->dskFolder);
            $this->dskTemplate = (string) ($d['active_template'] ?? $this->dskTemplate);
            $this->dskTitle = (string) ($d['title'] ?? $this->dskTitle);
            $this->dskSubtitle = (string) ($d['subtitle'] ?? $this->dskSubtitle);
            $this->dskAccent = (string) ($d['accent'] ?? $this->dskAccent);
            $this->dskDuration = (float) ($d['duration'] ?? $this->dskDuration);
            $this->dskStartAt = (float) ($d['start_at'] ?? $this->dskStartAt);
        }

        if (is_array($cfg['transition'] ?? null)) {
            $t = $cfg['transition'];
            $this->activeTransition = (string) ($t['type'] ?? $this->activeTransition);
            $this->transitionDuration = (float) ($t['duration'] ?? $this->transitionDuration);
            $this->transitionStingAsset = isset($t['sting_asset']) && $t['sting_asset'] !== '' ? (string) $t['sting_asset'] : null;
        }

        if (is_array($cfg['pipeline'] ?? null)) {
            $this->pipeline = PipelineConfig::fromArray($cfg['pipeline']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toShowConfig(): array
    {
        return [
            'show_name' => $this->activeShowName,
            'normalize' => [
                'width' => $this->normalizeWidth,
                'height' => $this->normalizeHeight,
                'fps' => $this->normalizeFps,
                'video_codec' => $this->pipeline->videoCodec,
                'audio_codec' => $this->pipeline->audioCodec,
                'audio_bitrate' => $this->pipeline->audioBitrate,
                'hw_accel' => $this->pipeline->hwAccel,
                'slate_duration' => $this->slateDuration,
            ],
            'watchdog' => [
                'enabled' => true,
                'input_dir' => $this->watchdogInputDir,
                'normalized_dir' => $this->watchdogNormalizedDir,
                'poll_seconds' => 3,
            ],
            'dsk' => [
                'folder' => $this->dskFolder,
                'active_template' => $this->dskTemplate,
                'title' => $this->dskTitle,
                'subtitle' => $this->dskSubtitle,
                'accent' => $this->dskAccent,
                'duration' => $this->dskDuration,
                'start_at' => $this->dskStartAt,
            ],
            'transition' => [
                'type' => $this->activeTransition,
                'duration' => $this->transitionDuration,
                'sting_asset' => $this->transitionStingAsset,
            ],
            'pipeline' => $this->pipeline->toArray(),
        ];
    }

    public function currentIndex(): int
    {
        return $this->selectedIndex[$this->tab->value] ?? 0;
    }

    public function moveCursor(int $delta, int $maxCount): void
    {
        if ($maxCount <= 0) {
            $this->selectedIndex[$this->tab->value] = 0;

            return;
        }

        $next = ($this->currentIndex() + $delta) % $maxCount;
        if ($next < 0) {
            $next += $maxCount;
        }

        $this->selectedIndex[$this->tab->value] = $next;
    }
}
