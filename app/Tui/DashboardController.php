<?php

namespace App\Tui;

use App\Enums\DashboardTab;
use App\Enums\JobStatus;
use App\Enums\MergeMode;
use App\Enums\SubtitleMode;
use App\Models\AnalysisReport;
use App\Models\DropZone;
use App\Models\MediaJob;
use App\Models\Preset;
use App\Models\ShowPreset;
use App\Services\Ffmix\AutoCropService;
use App\Services\Ffmix\BatchQueueRunner;
use App\Services\Ffmix\BitrateAnalyzerService;
use App\Services\Ffmix\CatalogService;
use App\Services\Ffmix\ContactSheetService;
use App\Services\Ffmix\DownstreamKeyService;
use App\Services\Ffmix\DropZoneWatcherService;
use App\Services\Ffmix\FfmpegCommandBuilder;
use App\Services\Ffmix\FfmpegEnvironmentService;
use App\Services\Ffmix\FolderWatchdogService;
use App\Services\Ffmix\ImageSequenceService;
use App\Services\Ffmix\MediaMergeService;
use App\Services\Ffmix\MediaProbeService;
use App\Services\Ffmix\PipelineConfig;
use App\Services\Ffmix\QualityMetricsService;
use App\Services\Ffmix\ShowPresetService;
use App\Services\Ffmix\TransitionService;

class DashboardController
{
    public function __construct(
        private DashboardRenderer $renderer,
        private FfmpegEnvironmentService $environment,
        private FfmpegCommandBuilder $builder,
        private BatchQueueRunner $runner,
        private DropZoneWatcherService $dropZones,
        private MediaProbeService $probe,
        private AutoCropService $autoCrop,
        private BitrateAnalyzerService $bitrate,
        private QualityMetricsService $quality,
        private ContactSheetService $contactSheet,
        private MediaMergeService $merge,
        private ImageSequenceService $images,
        private CatalogService $catalog,
        private ShowPresetService $showPresets,
        private FolderWatchdogService $watchdog,
        private DownstreamKeyService $dsk,
        private TransitionService $transitions,
    ) {}

    public function run(DashboardState $state, TerminalScreen $screen): void
    {
        Preset::syncBuiltins();
        $state->applyShowPreset($this->showPresets->active());
        $this->dsk->ensureSampleTemplate($state->dskFolder);
        $state->environment = $this->environment->inspect();
        $screen->enter();

        try {
            while (true) {
                $this->runner->tick();

                $dims = $screen->size();
                $frame = $this->renderer->render($state, $dims['width'], $dims['height']);
                $screen->drawFrame($frame);

                $key = $screen->readKey(90000);
                if ($key === null) {
                    continue;
                }

                if (! $this->handleKey($state, $key)) {
                    break;
                }
            }
        } finally {
            $this->runner->stopAll();
            $screen->restore();
        }
    }

    public function handleKey(DashboardState $state, string $key): bool
    {
        if ($key === 'CTRL_C') {
            return false;
        }

        if ($state->modal !== null) {
            return $this->handleModalKey($state, $key);
        }

        if ($key === 'q' || $key === 'Q') {
            return false;
        }

        if ($key === '?') {
            $state->modal = 'help';

            return true;
        }

        if ($key === 't') {
            $state->theme = $state->theme->toggle();
            $state->flashMessage = 'Switched to '.$state->theme->label().' theme';

            return true;
        }

        if ($key === 'TAB') {
            $state->tab = $state->tab->next();

            return true;
        }

        if ($key === 'SHIFT_TAB') {
            $state->tab = $state->tab->previous();

            return true;
        }

        if (in_array($key, ['1', '2', '3', '4', '5', '6', '7', '8', '9'], true)) {
            $state->tab = DashboardTab::fromNumber((int) $key);

            return true;
        }

        if ($key === 'UP' || $key === 'k') {
            $state->moveCursor(-1, $this->itemCountForTab($state));

            return true;
        }

        if ($key === 'DOWN' || $key === 'j') {
            $state->moveCursor(1, $this->itemCountForTab($state));

            return true;
        }

        if ($key === 'p') {
            $state->modal = 'preset_picker';
            $state->modalCursor = 0;

            return true;
        }

        if ($key === 'S') {
            $state->modal = 'show_picker';
            $state->modalCursor = 0;

            return true;
        }

        if ($key === 'W') {
            $this->openPrompt($state, 'show_save', 'Enter name to save current Show Preset:', $state->activeShowName);

            return true;
        }

        if ($key === 'I') {
            $this->openPrompt($state, 'show_import', 'Enter Show Preset JSON file path to import:', 'storage/app/ffmix/shows/show.json');

            return true;
        }

        if ($key === 'E') {
            $this->openPrompt($state, 'show_export', 'Enter destination JSON path to export active Show Preset:', 'storage/app/ffmix/shows/'.$state->activeShowSlug.'.json');

            return true;
        }

        if ($key === 'T') {
            $state->modal = 'transition_picker';
            $state->modalCursor = 0;

            return true;
        }

        if ($key === 'w') {
            $status = $this->watchdog->daemonStatus();
            if ($status['running']) {
                $this->watchdog->stopBackgroundDaemon();
                $state->flashMessage = 'Stopped Background Folder Watchdog';
            } else {
                $pid = $this->watchdog->startBackgroundDaemon(3);
                $state->flashMessage = "Started Background Folder Watchdog (PID {$pid})";
            }

            return true;
        }

        if ($key === 'z') {
            $results = $this->watchdog->scanAndNormalize($this->showPresets->active());
            $state->flashMessage = 'Watchdog scan normalized '.count($results).' media/slate asset(s) to MP4';

            return true;
        }

        if ($key === 'h') {
            $state->pipeline->hwAccel = ! $state->pipeline->hwAccel;
            $state->flashMessage = 'Apple VideoToolbox HW Accel: '.($state->pipeline->hwAccel ? 'ENABLED' : 'DISABLED');

            return true;
        }

        if ($key === 'r') {
            $this->runner->tick();
            $state->flashMessage = 'Batch queue active — '.$this->runner->activeCount().'/8 workers running';

            return true;
        }

        if ($key === 'a') {
            $this->openPrompt($state, 'add_job', 'Enter input media file path to enqueue:', $state->sampleInputPath);

            return true;
        }

        if ($key === 'l') {
            $state->modal = 'lut_picker';
            $state->modalCursor = 0;

            return true;
        }

        if ($key === '/') {
            $state->modal = 'catalog_search';
            $state->modalInput = '';

            return true;
        }

        if ($key === 'c') {
            $this->openPrompt($state, 'autocrop', 'Enter media file path for Auto CropDetect:', $state->sampleInputPath);

            return true;
        }

        if ($key === 'o') {
            $this->openPrompt($state, 'add_overlay', 'Enter overlay image/video path (e.g. logo.png):', 'logo.png');

            return true;
        }

        if ($key === 'b') {
            $this->openPrompt($state, 'bitrate', 'Enter video path for Bitrate Analyser:', $state->sampleInputPath);

            return true;
        }

        if ($key === 'v') {
            $this->openPrompt($state, 'quality', 'Enter "reference.mp4|distorted.mp4" for VMAF/PSNR/SSIM/CAMBI:', $state->sampleInputPath.'|'.$state->sampleOutputPath);

            return true;
        }

        if ($key === 'i') {
            $this->openPrompt($state, 'probe', 'Enter media file path to probe with ffprobe:', $state->sampleInputPath);

            return true;
        }

        if ($key === 'n') {
            $this->openPrompt($state, 'add_dropzone', 'Enter "Name|/watch/dir|/out/dir|preset":', 'Watch Inbox|/tmp/ffmix-in|/tmp/ffmix-out|desktop');

            return true;
        }

        if ($key === 's') {
            $created = $this->dropZones->scan();
            $state->flashMessage = 'Scanned Drop Zones: enqueued '.count($created).' new job(s)';

            return true;
        }

        if ($key === 'x' && $state->tab === DashboardTab::Queue) {
            $jobs = MediaJob::query()->orderByDesc('id')->get();
            $job = $jobs->get($state->currentIndex());
            if ($job instanceof MediaJob) {
                $this->runner->cancelJob($job->id);
                $state->flashMessage = "Cancelled job #{$job->id}";
            }

            return true;
        }

        if ($key === 'R' && $state->tab === DashboardTab::Queue) {
            $jobs = MediaJob::query()->orderByDesc('id')->get();
            $job = $jobs->get($state->currentIndex());
            if ($job instanceof MediaJob && in_array($job->status, [JobStatus::Failed, JobStatus::Cancelled], true)) {
                $job->update([
                    'status' => JobStatus::Pending,
                    'progress_percent' => 0.0,
                    'error_message' => null,
                ]);
                $state->flashMessage = "Re-queued job #{$job->id}";
            }

            return true;
        }

        if ($key === ' ' && $state->tab === DashboardTab::DropZones) {
            $zones = DropZone::query()->orderBy('id')->get();
            $zone = $zones->get($state->currentIndex());
            if ($zone instanceof DropZone) {
                $zone->update(['is_active' => ! $zone->is_active]);
                $state->flashMessage = "Drop Zone '{$zone->name}' ".($zone->is_active ? 'activated' : 'paused');
            }

            return true;
        }

        if ($key === 'ENTER') {
            $this->handleTabEnter($state);

            return true;
        }

        return true;
    }

    private function handleModalKey(DashboardState $state, string $key): bool
    {
        if ($key === 'ESC') {
            $state->modal = null;
            $state->modalInput = '';

            return true;
        }

        if ($state->modal === 'help') {
            if ($key === '?' || $key === 'ENTER' || $key === 'q') {
                $state->modal = null;
            }

            return true;
        }

        if ($state->modal === 'preset_picker') {
            $presets = Preset::query()->orderBy('category')->orderBy('name')->get();
            if ($key === 'UP' || $key === 'k') {
                $state->modalCursor = max(0, $state->modalCursor - 1);
            } elseif ($key === 'DOWN' || $key === 'j') {
                $state->modalCursor = min(max(0, $presets->count() - 1), $state->modalCursor + 1);
            } elseif ($key === 'ENTER') {
                $picked = $presets->get($state->modalCursor);
                if ($picked instanceof Preset) {
                    $state->activePresetSlug = $picked->slug;
                    $state->pipeline = PipelineConfig::fromArray($picked->config);
                    $state->flashMessage = "Applied preset: {$picked->name}";
                }
                $state->modal = null;
            }

            return true;
        }

        if ($state->modal === 'show_picker') {
            $shows = ShowPreset::query()->orderBy('id')->get();
            if ($key === 'UP' || $key === 'k') {
                $state->modalCursor = max(0, $state->modalCursor - 1);
            } elseif ($key === 'DOWN' || $key === 'j') {
                $state->modalCursor = min(max(0, $shows->count() - 1), $state->modalCursor + 1);
            } elseif ($key === 'ENTER') {
                $picked = $shows->get($state->modalCursor);
                if ($picked instanceof ShowPreset) {
                    $picked->activate();
                    $state->applyShowPreset($picked);
                    $state->flashMessage = "Loaded Show Preset: {$picked->name}";
                }
                $state->modal = null;
            }

            return true;
        }

        if ($state->modal === 'transition_picker') {
            $keys = array_keys($this->transitions->all());
            if ($key === 'UP' || $key === 'k') {
                $state->modalCursor = max(0, $state->modalCursor - 1);
            } elseif ($key === 'DOWN' || $key === 'j') {
                $state->modalCursor = min(max(0, count($keys) - 1), $state->modalCursor + 1);
            } elseif ($key === 'ENTER') {
                $picked = $keys[$state->modalCursor] ?? null;
                if ($picked !== null) {
                    $state->activeTransition = $picked;
                    $state->flashMessage = "Selected transition: {$picked}";
                }
                $state->modal = null;
            }

            return true;
        }

        if ($state->modal === 'lut_picker') {
            $luts = $this->catalog->lutPresets();
            if ($key === 'UP' || $key === 'k') {
                $state->modalCursor = max(0, $state->modalCursor - 1);
            } elseif ($key === 'DOWN' || $key === 'j') {
                $state->modalCursor = min(max(0, count($luts) - 1), $state->modalCursor + 1);
            } elseif ($key === 'ENTER') {
                $picked = $luts[$state->modalCursor] ?? null;
                if ($picked !== null) {
                    $state->pipeline->color['lut_slug'] = $picked['slug'];
                    $state->flashMessage = "Applied Pro LUT: {$picked['name']}";
                }
                $state->modal = null;
            }

            return true;
        }

        if ($key === 'BACKSPACE') {
            $state->modalInput = mb_substr($state->modalInput, 0, max(0, mb_strlen($state->modalInput) - 1));

            return true;
        }

        if ($key === 'ENTER' && $state->modal !== 'catalog_search') {
            $this->commitPromptModal($state);

            return true;
        }

        if (mb_strlen($key) === 1 && ord($key) >= 32) {
            $state->modalInput .= $key;
        }

        return true;
    }

    private function handleTabEnter(DashboardState $state): void
    {
        $idx = $state->currentIndex();
        $cfg = $state->pipeline;

        if ($state->tab === DashboardTab::Studio) {
            match ($idx) {
                0 => $state->modal = 'preset_picker',
                1 => $this->openPrompt($state, 'studio_input', 'Input media path:', $state->sampleInputPath),
                2 => $this->openPrompt($state, 'studio_output', 'Output media path:', $state->sampleOutputPath),
                3 => $this->cycleValue($cfg->container, ['mp4', 'mkv', 'mov', 'webm', 'ts', 'mxf', 'gif'], function (string $next) use ($cfg, $state): void {
                    $cfg->container = $next;
                    $state->flashMessage = "Container -> {$next}";
                }),
                4 => $this->cycleValue($cfg->videoCodec, ['h264', 'h265', 'h266', 'av1', 'prores', 'dnxhd', 'vp9', 'gif', 'copy'], function (string $next) use ($cfg, $state): void {
                    $cfg->videoCodec = $next;
                    $state->flashMessage = "Video codec -> {$next}";
                }),
                5 => $this->cycleValue($cfg->audioCodec, ['aac', 'alac', 'mp3', 'flac', 'wav', 'opus', 'vorbis', 'copy'], function (string $next) use ($cfg, $state): void {
                    $cfg->audioCodec = $next;
                    $state->flashMessage = "Audio codec -> {$next}";
                }),
                6 => $cfg->hwAccel = ! $cfg->hwAccel,
                7 => $this->cycleValue($cfg->resolution, ['source', '720p', '1080p', '4k', '5k', '8k'], function (string $next) use ($cfg, $state): void {
                    $cfg->resolution = $next;
                    $state->flashMessage = "Resolution -> {$next}";
                }),
                8 => $this->openPrompt($state, 'studio_crf', 'CRF quality (0-51):', (string) $cfg->crf),
                9 => $this->openPrompt($state, 'studio_trim', 'Trim "start|end" (e.g. 00:05|00:30):', ($cfg->trimStart ?? '').'|'.($cfg->trimEnd ?? '')),
                10 => $this->cycleSubtitleMode($state),
                11 => $this->openPrompt($state, 'studio_subpath', 'Subtitle file path (.srt/.ass/.vtt):', $cfg->subtitles['path'] ?? ''),
                12 => $state->modal = 'catalog_search',
                default => null,
            };
        } elseif ($state->tab === DashboardTab::Audio) {
            $af = &$state->pipeline->audioFilters;
            match ($idx) {
                0 => $af['volume'] = $af['volume'] >= 2.0 ? 0.5 : round($af['volume'] + 0.25, 2),
                1 => $af['fade_in'] = $af['fade_in'] > 0 ? 0.0 : 2.0,
                2 => $af['fade_out'] = $af['fade_out'] > 0 ? 0.0 : 2.0,
                3 => $af['earwax'] = ! $af['earwax'],
                4 => $af['tempo'] = $af['tempo'] >= 1.5 ? 0.75 : round($af['tempo'] + 0.25, 2),
                5 => $af['bs2b'] = ! $af['bs2b'],
                6 => $af['channel_map'] = $af['channel_map'] === null ? '0|1:stereo' : null,
                7 => $af['dynaudnorm'] = ! $af['dynaudnorm'],
                8 => $af['loudnorm'] = ! $af['loudnorm'],
                9 => $af['exciter'] = ! $af['exciter'],
                10 => $af['sample_rate'] = $af['sample_rate'] === 48000 ? 44100 : 48000,
                default => null,
            };
            $state->flashMessage = 'Updated audio filter chain';
        } elseif ($state->tab === DashboardTab::VisualFx) {
            $c = &$state->pipeline->color;
            match ($idx) {
                0 => $c['exposure'] = $c['exposure'] >= 1.0 ? -1.0 : round($c['exposure'] + 0.25, 2),
                1 => $c['contrast'] = $c['contrast'] >= 1.5 ? 0.8 : round($c['contrast'] + 0.1, 2),
                2 => $c['saturation'] = $c['saturation'] >= 1.6 ? 0.5 : round($c['saturation'] + 0.1, 2),
                3 => $c['gamma'] = $c['gamma'] >= 1.4 ? 0.7 : round($c['gamma'] + 0.1, 2),
                4 => $c['vibrance'] = $c['vibrance'] >= 0.6 ? -0.2 : round($c['vibrance'] + 0.15, 2),
                8 => $this->cycleValue($c['colorspace'], ['auto', 'bt709', 'bt2020', 'bt601', 'srgb'], function (string $next) use (&$c): void {
                    $c['colorspace'] = $next;
                }),
                9 => $state->modal = 'lut_picker',
                10 => $this->openPrompt($state, 'autocrop', 'Enter media file path for Auto CropDetect:', $state->sampleInputPath),
                11 => $this->openPrompt($state, 'add_overlay', 'Enter overlay image/video path:', 'logo.png'),
                default => null,
            };
        } elseif ($state->tab === DashboardTab::Tools) {
            $this->handleToolsEnter($state, $idx);
        } elseif ($state->tab === DashboardTab::Show) {
            match ($idx) {
                0 => $this->openPrompt($state, 'show_rename', 'Enter Show Name:', $state->activeShowName),
                1 => $state->modal = 'show_picker',
                2 => $this->openPrompt($state, 'watchdog_in', 'Watchdog Ingest Directory:', $state->watchdogInputDir),
                3 => $this->openPrompt($state, 'watchdog_out', 'Normalized MP4 Output Directory:', $state->watchdogNormalizedDir),
                5 => $this->handleKey($state, 'w'),
                6 => $state->modal = 'transition_picker',
                7 => $this->openPrompt($state, 'dsk_folder', 'DSK Animated HTML Folder:', $state->dskFolder),
                9 => $this->openPrompt($state, 'dsk_text', 'Enter "Title|Subtitle":', $state->dskTitle.'|'.$state->dskSubtitle),
                default => null,
            };
        }
    }

    private function handleToolsEnter(DashboardState $state, int $idx): void
    {
        try {
            $tc = &$state->toolsConfig;
            if ($idx === 2) {
                $res = $this->contactSheet->generate(
                    $tc['contact_input'],
                    $tc['contact_output'],
                    $tc['contact_cols'],
                    $tc['contact_rows'],
                );
                $state->flashMessage = "Contact sheet saved -> {$res['output']}";
            } elseif ($idx === 4) {
                $tc['merge_mode'] = $tc['merge_mode'] === 'passthru' ? 'complex' : 'passthru';
                $state->flashMessage = 'Merge mode -> '.$tc['merge_mode'];
            } elseif ($idx === 5) {
                $inputs = array_values(array_filter(array_map('trim', explode(',', $tc['merge_inputs']))));
                $mode = MergeMode::tryFrom($tc['merge_mode']) ?? MergeMode::Passthru;
                $res = $this->merge->merge($inputs, $tc['merge_output'], $mode);
                $state->flashMessage = "Merged {$res['count']} files -> {$res['output']}";
            } elseif ($idx === 6) {
                $args = $this->images->buildExportImagesCommand($tc['export_img_input'], $tc['export_img_pattern'], $tc['export_img_format']);
                $this->images->execute($args);
                $state->flashMessage = "Exported movie frames -> {$tc['export_img_pattern']}";
            } elseif ($idx === 7) {
                $args = $this->images->buildImagesToMovieCommand($tc['seq_input'], $tc['seq_output'], (float) $tc['seq_fps']);
                $this->images->execute($args);
                $state->flashMessage = "Built movie from images -> {$tc['seq_output']}";
            } else {
                $this->openPrompt($state, 'tool_edit_'.$idx, 'Edit tool parameter:', '');
            }
        } catch (\Throwable $e) {
            $state->flashMessage = 'Tool error: '.$e->getMessage();
        }
    }

    private function openPrompt(DashboardState $state, string $modal, string $prompt, string $initial): void
    {
        $state->modal = $modal;
        $state->modalPrompt = $prompt;
        $state->modalInput = $initial;
    }

    private function commitPromptModal(DashboardState $state): void
    {
        $modal = $state->modal;
        $val = trim($state->modalInput);
        $state->modal = null;

        try {
            if ($modal === 'add_job' && $val !== '') {
                $out = preg_replace('/\.[^.]+$/', '', $val).'_ffmix.'.$state->pipeline->container;
                $preview = $this->builder->toShellString($this->builder->build($val, $out, $state->pipeline, true));
                $job = MediaJob::query()->create([
                    'name' => basename($val),
                    'input_path' => $val,
                    'output_path' => $out,
                    'preset_slug' => $state->activePresetSlug,
                    'pipeline_config' => $state->pipeline->toArray(),
                    'command_preview' => $preview,
                ]);
                $state->flashMessage = "Enqueued job #{$job->id}: {$job->name}";
            } elseif ($modal === 'show_save' && $val !== '') {
                $state->activeShowName = $val;
                $saved = $this->showPresets->save($val, $state->toShowConfig());
                $state->applyShowPreset($saved);
                $state->flashMessage = "Saved & activated Show Preset '{$saved->slug}'";
            } elseif ($modal === 'show_import' && $val !== '') {
                $imported = $this->showPresets->importFromFile($val, true);
                $state->applyShowPreset($imported);
                $state->flashMessage = "Imported Show Preset '{$imported->name}'";
            } elseif ($modal === 'show_export' && $val !== '') {
                $active = $this->showPresets->active();
                $path = $this->showPresets->exportToFile($active, $val);
                $state->flashMessage = "Exported Show Preset -> {$path}";
            } elseif ($modal === 'show_rename' && $val !== '') {
                $state->activeShowName = $val;
                $state->flashMessage = "Show renamed to '{$val}' ([W] to save preset)";
            } elseif ($modal === 'watchdog_in' && $val !== '') {
                $state->watchdogInputDir = $val;
            } elseif ($modal === 'watchdog_out' && $val !== '') {
                $state->watchdogNormalizedDir = $val;
            } elseif ($modal === 'dsk_folder' && $val !== '') {
                $state->dskFolder = $val;
                $this->dsk->ensureSampleTemplate($val);
            } elseif ($modal === 'dsk_text' && str_contains($val, '|')) {
                [$title, $sub] = array_pad(array_map('trim', explode('|', $val, 2)), 2, '');
                $state->dskTitle = $title;
                $state->dskSubtitle = $sub;
            } elseif ($modal === 'studio_input' && $val !== '') {
                $state->sampleInputPath = $val;
            } elseif ($modal === 'studio_output' && $val !== '') {
                $state->sampleOutputPath = $val;
            } elseif ($modal === 'studio_crf' && is_numeric($val)) {
                $state->pipeline->crf = max(0, min(51, (int) $val));
            } elseif ($modal === 'studio_trim') {
                [$start, $end] = array_pad(explode('|', $val, 2), 2, '');
                $state->pipeline->trimStart = trim($start) !== '' ? trim($start) : null;
                $state->pipeline->trimEnd = trim($end) !== '' ? trim($end) : null;
            } elseif ($modal === 'studio_subpath') {
                $state->pipeline->subtitles['path'] = $val !== '' ? $val : null;
            } elseif ($modal === 'add_overlay' && $val !== '') {
                $state->pipeline->overlays[] = [
                    'path' => $val,
                    'x' => 'W-w-20',
                    'y' => '20',
                    'width' => 160,
                    'height' => null,
                    'opacity' => 0.9,
                    'start' => null,
                    'end' => null,
                ];
                $state->flashMessage = 'Added overlay: '.basename($val);
            } elseif ($modal === 'autocrop' && $val !== '') {
                $crop = $this->autoCrop->detect($val);
                $state->pipeline->crop = [
                    'enabled' => true,
                    'mode' => 'auto',
                    'width' => $crop['width'],
                    'height' => $crop['height'],
                    'x' => $crop['x'],
                    'y' => $crop['y'],
                ];
                $state->flashMessage = "Auto Crop detected: {$crop['filter']}";
            } elseif ($modal === 'bitrate' && $val !== '') {
                $res = $this->bitrate->analyze($val);
                $state->flashMessage = 'Bitrate Avg: '.$this->bitrate->formatBps($res['avg_bps']);
                $state->tab = DashboardTab::Analyzer;
            } elseif ($modal === 'quality' && str_contains($val, '|')) {
                [$ref, $dist] = array_map('trim', explode('|', $val, 2));
                $res = $this->quality->evaluate($ref, $dist);
                $state->flashMessage = sprintf('Quality VMAF=%s Grade=%s', (string) ($res['vmaf'] ?? 'N/A'), $res['grade']);
                $state->tab = DashboardTab::Analyzer;
            } elseif ($modal === 'probe' && $val !== '') {
                $state->lastProbe = $this->probe->probe($val);
                $state->flashMessage = 'Probed: '.basename($val);
                $state->tab = DashboardTab::Monitor;
            } elseif ($modal === 'add_dropzone' && str_contains($val, '|')) {
                [$name, $watch, $out, $preset] = array_pad(array_map('trim', explode('|', $val, 4)), 4, 'desktop');
                $zone = DropZone::query()->create([
                    'name' => $name ?: 'Drop Zone',
                    'watch_path' => $watch ?: '/tmp/ffmix-in',
                    'output_path' => $out ?: '/tmp/ffmix-out',
                    'preset_slug' => $preset ?: 'desktop',
                    'is_active' => true,
                ]);
                $state->flashMessage = "Created Drop Zone #{$zone->id}: {$zone->name}";
                $state->tab = DashboardTab::DropZones;
            }
        } catch (\Throwable $e) {
            $state->flashMessage = 'Error: '.$e->getMessage();
        }
    }

    /**
     * @param  list<string>  $options
     * @param  callable(string): void  $apply
     */
    private function cycleValue(string $current, array $options, callable $apply): void
    {
        $idx = array_search($current, $options, true);
        $next = $idx === false ? $options[0] : $options[($idx + 1) % count($options)];
        $apply($next);
    }

    private function cycleSubtitleMode(DashboardState $state): void
    {
        $modes = SubtitleMode::cases();
        $idx = array_search($state->pipeline->subtitles['mode'], $modes, true);
        $next = $idx === false ? SubtitleMode::None : $modes[($idx + 1) % count($modes)];
        $state->pipeline->subtitles['mode'] = $next;
        $state->flashMessage = 'Subtitle mode -> '.$next->label();
    }

    private function itemCountForTab(DashboardState $state): int
    {
        return match ($state->tab) {
            DashboardTab::Queue => MediaJob::query()->count(),
            DashboardTab::Studio => 13,
            DashboardTab::VisualFx => 13,
            DashboardTab::Audio => 11,
            DashboardTab::Analyzer => AnalysisReport::query()->count(),
            DashboardTab::Tools => 8,
            DashboardTab::DropZones => DropZone::query()->count(),
            DashboardTab::Monitor => 1,
            DashboardTab::Show => 11,
        };
    }
}
