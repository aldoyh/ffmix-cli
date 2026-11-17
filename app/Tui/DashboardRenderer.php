<?php

namespace App\Tui;

use App\Enums\DashboardTab;
use App\Enums\JobStatus;
use App\Models\AnalysisReport;
use App\Models\DropZone;
use App\Models\MediaJob;
use App\Models\NormalizedAsset;
use App\Models\Preset;
use App\Models\ShowPreset;
use App\Services\Ffmix\BatchQueueRunner;
use App\Services\Ffmix\BitrateAnalyzerService;
use App\Services\Ffmix\CatalogService;
use App\Services\Ffmix\DownstreamKeyService;
use App\Services\Ffmix\FfmpegCommandBuilder;
use App\Services\Ffmix\FolderWatchdogService;
use App\Services\Ffmix\TransitionService;

class DashboardRenderer
{
    public function __construct(
        private FfmpegCommandBuilder $builder,
        private CatalogService $catalog,
        private BitrateAnalyzerService $bitrateAnalyzer,
        private BatchQueueRunner $runner,
        private FolderWatchdogService $watchdog,
        private DownstreamKeyService $dsk,
        private TransitionService $transitions,
    ) {}

    public function render(DashboardState $state, int $width, int $height): string
    {
        $theme = new Theme($state->theme);
        $lines = [];

        // Rows 1-5: Concise ASCII Banner (ffMix) + Active Show & Telemetry Container
        foreach ($this->renderBannerContainer($state, $width, $theme) as $headerLine) {
            $lines[] = $headerLine;
        }

        // Row 6: Tab Bar
        $lines[] = $this->renderTabBar($state, $width, $theme);

        // Main Body (height - 7 rows: 5 banner + 1 tab bar + 1 footer)
        $bodyHeight = max(12, $height - 7);
        $bodyLines = match ($state->tab) {
            DashboardTab::Queue => $this->renderQueueTab($state, $width, $bodyHeight, $theme),
            DashboardTab::Studio => $this->renderStudioTab($state, $width, $bodyHeight, $theme),
            DashboardTab::VisualFx => $this->renderVisualFxTab($state, $width, $bodyHeight, $theme),
            DashboardTab::Audio => $this->renderAudioTab($state, $width, $bodyHeight, $theme),
            DashboardTab::Analyzer => $this->renderAnalyzerTab($state, $width, $bodyHeight, $theme),
            DashboardTab::Tools => $this->renderToolsTab($state, $width, $bodyHeight, $theme),
            DashboardTab::DropZones => $this->renderDropZonesTab($state, $width, $bodyHeight, $theme),
            DashboardTab::Monitor => $this->renderMonitorTab($state, $width, $bodyHeight, $theme),
            DashboardTab::Show => $this->renderShowTab($state, $width, $bodyHeight, $theme),
        };

        if ($state->modal !== null) {
            $bodyLines = $this->overlayModal($bodyLines, $state, $width, $bodyHeight, $theme);
        }

        foreach ($bodyLines as $line) {
            $lines[] = $line;
        }

        // Bottom Footer Bar
        $lines[] = $this->renderFooter($state, $width, $theme);

        return implode("\n", $lines);
    }

    /**
     * Concise 3-line ffMix ASCII logo banner paired with Show Name & live telemetry inside one box.
     *
     * @return list<string>
     */
    private function renderBannerContainer(DashboardState $state, int $width, Theme $theme): array
    {
        $env = $state->environment;
        $ffBadge = $env['available'] ? "FFmpeg {$env['version']}" : 'FFmpeg:missing';
        $vtBadge = $env['videotoolbox'] ? 'VT:ON' : 'VT:OFF';
        $wdStatus = $this->watchdog->daemonStatus();
        $wdBadge = $wdStatus['running'] ? $theme->success('Watchdog:RUN') : $theme->muted('Watchdog:IDLE');
        $normCount = NormalizedAsset::query()->where('status', 'ready')->count();
        $workers = sprintf('Workers:%d/%d', $this->runner->activeCount(), $this->runner->maxWorkers());

        $logo = [
            $theme->accent('▄▀▀ ▄▀▀ █▄ ▄█ ▀ ▀▄▀'),
            $theme->accent('█▀  █▀  █ ▀ █ █  █ '),
            $theme->muted('▀   ▀   ▀   ▀ ▀ ▄▀▄'),
        ];

        $showLabel = $theme->bold('SHOW: ').$theme->success($state->activeShowName);
        $presetMeta = $theme->muted("Preset: {$state->activeShowSlug} ({$state->normalizeWidth}x{$state->normalizeHeight}@{$state->normalizeFps}fps)");
        $dskMeta = $theme->muted('DSK: ').$theme->text($state->dskTemplate).'  │  '.$theme->muted('Trans: ').$theme->accent($state->activeTransition);

        $right1 = "{$ffBadge} │ {$vtBadge} │ {$workers}";
        $right2 = "{$wdBadge} ({$normCount} MP4) │ Theme:".$state->theme->label();
        $right3 = '[S] Shows  [W] Save  [I] Import';

        $innerWidth = max(40, $width - 4);
        $rows = [];
        $rows[] = $this->composeBannerRow($logo[0], $showLabel, $right1, $innerWidth, $theme);
        $rows[] = $this->composeBannerRow($logo[1], $presetMeta, $right2, $innerWidth, $theme);
        $rows[] = $this->composeBannerRow($logo[2], $dskMeta, $right3, $innerWidth, $theme);

        return Ansi::box('ffMix Broadcast & Media Studio', $rows, $width, 5, $theme, true);
    }

    private function composeBannerRow(string $logoLine, string $center, string $right, int $innerWidth, Theme $theme): string
    {
        $left = $logoLine.' '.$theme->muted('│').' '.$center;
        $gap = max(1, $innerWidth - Ansi::width($left) - Ansi::width($right));

        return Ansi::fit($left.str_repeat(' ', $gap).$right, $innerWidth);
    }

    private function renderTabBar(DashboardState $state, int $width, Theme $theme): string
    {
        $parts = [];
        foreach (DashboardTab::cases() as $tab) {
            $label = " [{$tab->number()}]{$tab->label()} ";
            $parts[] = $state->tab === $tab
                ? $theme->activeTab($label)
                : $theme->inactiveTab($label);
        }

        $joined = implode(' ', $parts);

        return Ansi::pad($joined, $width);
    }

    private function renderFooter(DashboardState $state, int $width, Theme $theme): string
    {
        $keys = ' [1-9] Tabs  [↑↓] Nav  [S] Show  [w] Watchdog  [r] Run 8x  [a] Add  [p] Preset  [t] Theme  [?] Help  [q] Quit ';
        $flash = $state->flashMessage;

        $availableForFlash = max(12, $width - Ansi::width($keys) - 3);
        $flashTruncated = Ansi::truncate($flash, $availableForFlash);
        $line = $keys.'│ '.$flashTruncated;

        return $theme->footerBar(Ansi::fit($line, $width));
    }

    /**
     * Tab 1: Batch Queue (up to 8 simultaneous workers).
     *
     * @return list<string>
     */
    private function renderQueueTab(DashboardState $state, int $width, int $height, Theme $theme): array
    {
        $leftWidth = (int) floor($width * 0.54);
        $rightWidth = $width - $leftWidth - 1;

        $jobs = MediaJob::query()->orderByDesc('id')->limit(max(5, $height - 4))->get();
        $selectedIdx = min($state->currentIndex(), max(0, $jobs->count() - 1));

        $leftLines = [
            $theme->muted('ID   Status  Slot  Progress         FPS    Speed   File'),
            $theme->muted(str_repeat('─', max(10, $leftWidth - 4))),
        ];

        if ($jobs->isEmpty()) {
            $leftLines[] = '';
            $leftLines[] = $theme->muted('  No media jobs in queue yet.');
            $leftLines[] = $theme->accent('  Press [a] to add a file or [7] -> [s] to scan Drop Zones.');
        } else {
            foreach ($jobs as $idx => $job) {
                $slotStr = $job->worker_slot !== null ? "W{$job->worker_slot}" : '--';
                $bar = Ansi::progressBar($job->progress_percent, 10, $theme);
                $pct = sprintf('%5.1f%%', $job->progress_percent);
                $fps = sprintf('%5.1f', $job->fps);
                $speed = Ansi::fit($job->speed ?? '-', 6);
                $name = Ansi::truncate($job->name, max(10, $leftWidth - 52));

                $statusStyled = match ($job->status) {
                    JobStatus::Running => $theme->accent($job->status->badge()),
                    JobStatus::Completed => $theme->success($job->status->badge()),
                    JobStatus::Failed => $theme->danger($job->status->badge()),
                    JobStatus::Cancelled => $theme->warning($job->status->badge()),
                    JobStatus::Pending => $theme->muted($job->status->badge()),
                };

                $row = sprintf('#%-3d %s %s %s %s %s %s %s', $job->id, $statusStyled, $slotStr, $bar, $pct, $fps, $speed, $name);
                $leftLines[] = $idx === $selectedIdx ? $theme->selectedRow(Ansi::fit(Ansi::strip($row), $leftWidth - 4)) : $row;
            }
        }

        $rightLines = [
            $theme->accent('8-Worker Parallel Pool (Apple Silicon / Multi-Core)'),
        ];

        $runningBySlot = [];
        foreach ($jobs as $j) {
            if ($j->status === JobStatus::Running && $j->worker_slot !== null) {
                $runningBySlot[$j->worker_slot] = $j;
            }
        }

        for ($slot = 1; $slot <= 8; $slot += 2) {
            $cell1 = $this->formatWorkerSlot($slot, $runningBySlot[$slot] ?? null, $theme);
            $cell2 = $this->formatWorkerSlot($slot + 1, $runningBySlot[$slot + 1] ?? null, $theme);
            $rightLines[] = Ansi::fit($cell1, 28).' │ '.$cell2;
        }

        $rightLines[] = $theme->muted(str_repeat('─', max(10, $rightWidth - 4)));
        $selectedJob = $jobs->get($selectedIdx);

        if ($selectedJob instanceof MediaJob) {
            $rightLines[] = $theme->bold("Selected Job #{$selectedJob->id}: {$selectedJob->name}");
            $rightLines[] = "Input  : {$selectedJob->input_path}";
            $rightLines[] = "Output : {$selectedJob->output_path}";
            $rightLines[] = 'Preset : '.($selectedJob->preset_slug ?? 'custom').'  │  Status: '.$selectedJob->status->label();
            if ($selectedJob->error_message !== null && $selectedJob->error_message !== '') {
                $rightLines[] = $theme->danger('Error  : '.Ansi::truncate($selectedJob->error_message, $rightWidth - 14));
            }
            $rightLines[] = $theme->muted('FFmpeg Command:');
            $cmd = $selectedJob->command_preview ?? '';
            foreach (str_split($cmd, max(20, $rightWidth - 6)) as $chunk) {
                $rightLines[] = $theme->dim($chunk);
                if (count($rightLines) >= $height - 3) {
                    break;
                }
            }
        } else {
            $rightLines[] = $theme->muted('Queue Summary:');
            $rightLines[] = '  Pending  : '.MediaJob::query()->where('status', JobStatus::Pending->value)->count();
            $rightLines[] = '  Running  : '.MediaJob::query()->where('status', JobStatus::Running->value)->count();
            $rightLines[] = '  Completed: '.MediaJob::query()->where('status', JobStatus::Completed->value)->count();
            $rightLines[] = '  Failed   : '.MediaJob::query()->where('status', JobStatus::Failed->value)->count();
        }

        $leftBox = Ansi::box('Batch Queue ([a] Add  [r] Run 8x  [x] Cancel  [R] Retry)', $leftLines, $leftWidth, $height, $theme, true);
        $rightBox = Ansi::box('Worker Pool & Inspector', $rightLines, $rightWidth, $height, $theme);

        return Ansi::joinColumns($leftBox, $rightBox);
    }

    private function formatWorkerSlot(int $slot, ?MediaJob $job, Theme $theme): string
    {
        if ($job === null) {
            return $theme->muted("W{$slot}: [idle      ]   0%");
        }

        $bar = Ansi::progressBar($job->progress_percent, 10, $theme);

        return $theme->accent("W{$slot}: ")."[{$bar}] ".sprintf('%3.0f%%', $job->progress_percent);
    }

    /**
     * Tab 2: Studio.
     *
     * @return list<string>
     */
    private function renderStudioTab(DashboardState $state, int $width, int $height, Theme $theme): array
    {
        $leftWidth = (int) floor($width * 0.50);
        $rightWidth = $width - $leftWidth - 1;
        $cfg = $state->pipeline;

        $params = [
            ['Preset Profile', $state->activePresetSlug.' ([p] choose)'],
            ['Input File', $state->sampleInputPath],
            ['Output File', $state->sampleOutputPath],
            ['Container', strtoupper($cfg->container).' (mp4/mkv/mov/webm/mxf/ts/gif)'],
            ['Video Codec', $cfg->videoCodec.' (h264/h265/h266/av1/prores/dnxhd/vp9)'],
            ['Audio Codec', $cfg->audioCodec.' (aac/alac/mp3/flac/wav/opus/vorbis)'],
            ['Apple VideoToolbox HW', $cfg->hwAccel ? 'ENABLED (H264/HEVC/ProRes/AAC/ALAC)' : 'DISABLED (Software)'],
            ['Resolution Tier', strtoupper($cfg->resolution).' (source/720p/1080p/4K/5K/8K)'],
            ['Quality (CRF / Bitrate)', $cfg->videoBitrate !== null ? "Bitrate {$cfg->videoBitrate}" : "CRF {$cfg->crf}"],
            ['Trim Start / End', ($cfg->trimStart ?? '00:00').' -> '.($cfg->trimEnd ?? 'EOF')],
            ['Subtitle Mode', $cfg->subtitles['mode']->label()],
            ['Subtitle File', $cfg->subtitles['path'] ?? '(none)'],
            ['+450 Advanced Options', count($cfg->advancedOptions).' active ([/] search catalog)'],
        ];

        $selected = min($state->currentIndex(), count($params) - 1);
        $leftLines = [
            $theme->muted('Press [Enter] to edit selected setting, [h] HW Accel, [a] Enqueue'),
            $theme->muted(str_repeat('─', max(10, $leftWidth - 4))),
        ];

        foreach ($params as $idx => [$key, $val]) {
            $line = sprintf('%-24s : %s', $key, $val);
            $leftLines[] = $idx === $selected ? $theme->selectedRow(Ansi::fit($line, $leftWidth - 4)) : $line;
        }

        $presets = Preset::query()->orderBy('category')->orderBy('name')->get();
        $rightLines = [
            $theme->accent('Device & Platform Presets ('.$presets->count().' available):'),
        ];

        foreach ($presets->take(max(4, (int) floor($height / 2) - 2)) as $preset) {
            $marker = $preset->slug === $state->activePresetSlug ? $theme->success('● ') : $theme->muted('○ ');
            $rightLines[] = $marker.sprintf('%-18s', $preset->slug).' '.$theme->muted($preset->category.' — '.$preset->name);
        }

        $rightLines[] = $theme->muted(str_repeat('─', max(10, $rightWidth - 4)));
        $rightLines[] = $theme->accent('Live Compiled FFmpeg Command:');

        $args = $this->builder->build($state->sampleInputPath, $state->sampleOutputPath, $cfg, true);
        $cmd = $this->builder->toShellString($args);
        foreach (str_split($cmd, max(20, $rightWidth - 6)) as $chunk) {
            $rightLines[] = $theme->text($chunk);
            if (count($rightLines) >= $height - 3) {
                break;
            }
        }

        $leftBox = Ansi::box('Transcode Studio ([Enter] Edit  [p] Preset  [h] VideoToolbox)', $leftLines, $leftWidth, $height, $theme, true);
        $rightBox = Ansi::box('Presets & Command Builder', $rightLines, $rightWidth, $height, $theme);

        return Ansi::joinColumns($leftBox, $rightBox);
    }

    /**
     * Tab 3: Visual FX.
     *
     * @return list<string>
     */
    private function renderVisualFxTab(DashboardState $state, int $width, int $height, Theme $theme): array
    {
        $leftWidth = (int) floor($width * 0.50);
        $rightWidth = $width - $leftWidth - 1;
        $cfg = $state->pipeline;
        $c = $cfg->color;

        $items = [
            ['Exposure (EV)', sprintf('%+.2f', $c['exposure'])],
            ['Contrast', sprintf('%.2f', $c['contrast'])],
            ['Saturation', sprintf('%.2f', $c['saturation'])],
            ['Gamma', sprintf('%.2f', $c['gamma'])],
            ['Vibrance', sprintf('%+.2f', $c['vibrance'])],
            ['Shadows / Highlights', sprintf('%+.2f / %+.2f', $c['shadows'], $c['highlights'])],
            ['RGB Balance (R/G/B)', sprintf('%+.2f / %+.2f / %+.2f', $c['red'], $c['green'], $c['blue'])],
            ['White Balance (Kelvin)', "{$c['temperature']} K"],
            ['Colorspace Convert', strtoupper($c['colorspace'])],
            ['Pro LUT Preset (+80)', $c['lut_file'] ?? $c['lut_slug'] ?? '(none) ([l] browse 84 LUTs)'],
            ['Cropping', $cfg->crop['enabled'] ? "{$cfg->crop['width']}x{$cfg->crop['height']}@{$cfg->crop['x']},{$cfg->crop['y']}" : 'Disabled ([c] Auto CropDetect)'],
            ['Overlays (Unlimited)', count($cfg->overlays).' layers ([o] add logo/PiP)'],
            ['Video Filters (+60)', count($cfg->videoFilters).' active ([f] browse 64 filters)'],
        ];

        $selected = min($state->currentIndex(), count($items) - 1);
        $leftLines = [
            $theme->muted('[Enter] Edit  [l] 84 Pro LUTs  [c] CropDetect  [o] Add Overlay'),
            $theme->muted(str_repeat('─', max(10, $leftWidth - 4))),
        ];

        foreach ($items as $idx => [$key, $val]) {
            $line = sprintf('%-24s : %s', $key, $val);
            $leftLines[] = $idx === $selected ? $theme->selectedRow(Ansi::fit($line, $leftWidth - 4)) : $line;
        }

        $vfChain = $this->builder->buildVideoFilterChain($cfg);
        $luts = $this->catalog->lutPresets();
        $vFilters = $this->catalog->videoFilters();

        $rightLines = [
            $theme->accent('Compiled Video Filtergraph (-vf / -filter_complex):'),
            $vfChain === [] ? $theme->muted('  (passthru / no active video filters)') : $theme->text('  '.implode(',', $vfChain)),
            '',
            $theme->accent('Pro LUT Library ('.count($luts).' Presets) & Video Filters ('.count($vFilters).' Filters):'),
        ];

        foreach (array_slice($luts, 0, 6) as $lut) {
            $active = $c['lut_slug'] === $lut['slug'] ? $theme->success('★ ') : $theme->muted('· ');
            $rightLines[] = $active.sprintf('%-22s', $lut['slug']).' '.$theme->muted($lut['category']);
        }

        if ($cfg->overlays !== []) {
            $rightLines[] = '';
            $rightLines[] = $theme->accent('Active Overlays:');
            foreach ($cfg->overlays as $i => $ov) {
                $rightLines[] = sprintf('  #%d %s @ (%s,%s) opacity=%.2f', $i + 1, basename($ov['path']), $ov['x'], $ov['y'], $ov['opacity']);
            }
        }

        $leftBox = Ansi::box('Color, LUTs, Crop & Overlays', $leftLines, $leftWidth, $height, $theme, true);
        $rightBox = Ansi::box('Filtergraph & LUT Catalog', $rightLines, $rightWidth, $height, $theme);

        return Ansi::joinColumns($leftBox, $rightBox);
    }

    /**
     * Tab 4: Audio Lab.
     *
     * @return list<string>
     */
    private function renderAudioTab(DashboardState $state, int $width, int $height, Theme $theme): array
    {
        $leftWidth = (int) floor($width * 0.52);
        $rightWidth = $width - $leftWidth - 1;
        $af = $state->pipeline->audioFilters;

        $items = [
            ['Volume Multiplier', sprintf('%.2fx', $af['volume'])],
            ['Fade In (seconds)', sprintf('%.1fs', $af['fade_in'])],
            ['Fade Out (seconds)', sprintf('%.1fs', $af['fade_out'])],
            ['Earwax (Headphone)', $af['earwax'] ? 'ON' : 'OFF'],
            ['Audio Tempo', sprintf('%.2fx', $af['tempo'])],
            ['Bauer Stereo (bs2b)', $af['bs2b'] ? 'ON' : 'OFF'],
            ['Channel Mapping', $af['channel_map'] ?? '(auto)'],
            ['Dynamic Normalizer', $af['dynaudnorm'] ? 'ON' : 'OFF'],
            ['EBU R128 Loudnorm', $af['loudnorm'] ? 'ON (-14 LUFS)' : 'OFF'],
            ['Harmonic Exciter', $af['exciter'] ? 'ON' : 'OFF'],
            ['Sample Rate (Hz)', $af['sample_rate'] !== null ? "{$af['sample_rate']} Hz" : 'Source'],
        ];

        $selected = min($state->currentIndex(), count($items) - 1);
        $leftLines = [
            $theme->muted('[Enter] Toggle or edit selected audio filter'),
            $theme->muted(str_repeat('─', max(10, $leftWidth - 4))),
        ];

        foreach ($items as $idx => [$key, $val]) {
            $line = sprintf('%-24s : %s', $key, $val);
            $leftLines[] = $idx === $selected ? $theme->selectedRow(Ansi::fit($line, $leftWidth - 4)) : $line;
        }

        $chain = $this->builder->buildAudioFilterChain($state->pipeline);
        $rightLines = [
            $theme->accent('Compiled Audio Filtergraph (-af):'),
            $chain === [] ? $theme->muted('  (no audio filters active)') : $theme->text('  '.implode(',', $chain)),
            '',
            $theme->accent('Available Audio DSP Filters:'),
        ];

        foreach ($this->catalog->audioFilters() as $filter) {
            $rightLines[] = '  • '.sprintf('%-26s', $filter['label']).' '.$theme->muted($filter['description']);
        }

        $leftBox = Ansi::box('Audio Filters & Mastering', $leftLines, $leftWidth, $height, $theme, true);
        $rightBox = Ansi::box('Audio Filterchain & DSP Reference', $rightLines, $rightWidth, $height, $theme);

        return Ansi::joinColumns($leftBox, $rightBox);
    }

    /**
     * Tab 5: Video Quality Metrics & Bitrate Analyser.
     *
     * @return list<string>
     */
    private function renderAnalyzerTab(DashboardState $state, int $width, int $height, Theme $theme): array
    {
        $leftWidth = (int) floor($width * 0.48);
        $rightWidth = $width - $leftWidth - 1;

        $reports = AnalysisReport::query()->orderByDesc('id')->limit(10)->get();
        $selectedIdx = min($state->currentIndex(), max(0, $reports->count() - 1));

        $leftLines = [
            $theme->muted('[b] Run Bitrate Analysis   [v] Run VMAF/PSNR/SSIM/CAMBI'),
            $theme->muted(str_repeat('─', max(10, $leftWidth - 4))),
        ];

        if ($reports->isEmpty()) {
            $leftLines[] = '';
            $leftLines[] = $theme->muted('  No analysis reports yet.');
            $leftLines[] = $theme->accent('  Press [b] to analyze bitrate or [v] to score VMAF/PSNR/SSIM/CAMBI.');
        } else {
            foreach ($reports as $idx => $report) {
                $tag = $report->kind === 'quality' ? $theme->accent('QUALITY') : $theme->success('BITRATE');
                $file = basename($report->source_path);
                $metric = $report->kind === 'quality'
                    ? sprintf('VMAF:%s SSIM:%s', (string) ($report->summary['vmaf'] ?? '-'), (string) ($report->summary['ssim'] ?? '-'))
                    : 'Avg:'.$this->bitrateAnalyzer->formatBps((int) ($report->summary['avg_bps'] ?? 0));

                $row = "#{$report->id} [{$tag}] ".Ansi::fit($file, 18).' '.$metric;
                $leftLines[] = $idx === $selectedIdx ? $theme->selectedRow(Ansi::fit(Ansi::strip($row), $leftWidth - 4)) : $row;
            }
        }

        $rightLines = [];
        $selectedReport = $reports->get($selectedIdx);
        if ($selectedReport instanceof AnalysisReport) {
            $sum = $selectedReport->summary;
            if ($selectedReport->kind === 'quality') {
                $rightLines[] = $theme->accent('Video Quality Metrics Report');
                $rightLines[] = 'Source    : '.$selectedReport->source_path;
                $rightLines[] = 'Reference : '.($selectedReport->reference_path ?? '-');
                $rightLines[] = '';
                $rightLines[] = sprintf('  VMAF Score  : %s', $theme->success((string) ($sum['vmaf'] ?? 'N/A')));
                $rightLines[] = sprintf('  PSNR (Y/Avg): %s dB / %s dB', (string) ($sum['psnr_y'] ?? 'N/A'), (string) ($sum['psnr_avg'] ?? 'N/A'));
                $rightLines[] = sprintf('  SSIM        : %s', (string) ($sum['ssim'] ?? 'N/A'));
                $rightLines[] = sprintf('  CAMBI       : %s', (string) ($sum['cambi'] ?? 'N/A'));
                $rightLines[] = '  Verdict     : '.$theme->accent((string) ($sum['grade'] ?? 'Measured'));
            } else {
                $rightLines[] = $theme->accent('Video Bitrate Analyser Telemetry');
                $rightLines[] = 'File      : '.$selectedReport->source_path;
                $rightLines[] = sprintf('Duration  : %.2fs   Keyframes: %d', (float) ($sum['duration_seconds'] ?? 0), (int) ($sum['keyframes'] ?? 0));
                $rightLines[] = 'Average   : '.$theme->accent($this->bitrateAnalyzer->formatBps((int) ($sum['avg_bps'] ?? 0)));
                $rightLines[] = 'Min / Max : '.$this->bitrateAnalyzer->formatBps((int) ($sum['min_bps'] ?? 0)).' / '.$theme->warning($this->bitrateAnalyzer->formatBps((int) ($sum['max_bps'] ?? 0)));
                $rightLines[] = 'P95 Peak  : '.$this->bitrateAnalyzer->formatBps((int) ($sum['p95_bps'] ?? 0));
                $rightLines[] = '';

                /** @var list<int> $series */
                $series = array_map('intval', $selectedReport->series ?? []);
                $spark = $this->bitrateAnalyzer->renderSparkline($series, max(20, $rightWidth - 8));
                $rightLines[] = $theme->muted('Per-Second Bitrate Timeline:');
                $rightLines[] = $theme->accent('  '.$spark);
            }
        } else {
            $rightLines[] = $theme->accent('Supported Quality & Telemetry Engines:');
            $rightLines[] = '  • VMAF  (Netflix perceptual video quality 0-100)';
            $rightLines[] = '  • PSNR  (Peak Signal-to-Noise Ratio in dB)';
            $rightLines[] = '  • SSIM  (Structural Similarity Index 0.0-1.0)';
            $rightLines[] = '  • CAMBI (Contrast-Aware Multiscale Banding Index)';
            $rightLines[] = '  • Bitrate Analyser (packet-level bps + sparkline)';
        }

        $leftBox = Ansi::box('Quality & Bitrate Reports', $leftLines, $leftWidth, $height, $theme, true);
        $rightBox = Ansi::box('Telemetry & Sparkline Inspector', $rightLines, $rightWidth, $height, $theme);

        return Ansi::joinColumns($leftBox, $rightBox);
    }

    /**
     * Tab 6: Contact Sheet Creator, Merge Media Files, Transitions + Logo Sting, Movies <-> Images.
     *
     * @return list<string>
     */
    private function renderToolsTab(DashboardState $state, int $width, int $height, Theme $theme): array
    {
        $leftWidth = (int) floor($width * 0.50);
        $rightWidth = $width - $leftWidth - 1;
        $tc = $state->toolsConfig;

        $tools = [
            ['Contact Sheet: Input', $tc['contact_input']],
            ['Contact Sheet: Grid', "{$tc['contact_cols']}x{$tc['contact_rows']} -> {$tc['contact_output']}"],
            ['Run Contact Sheet Creator', '[Enter] Generate thumbnail grid'],
            ['Merge: Input Files', $tc['merge_inputs']],
            ['Merge: Mode', strtoupper($tc['merge_mode']).' (passthru | complex)'],
            ['Run Merge Media Files', "[Enter] Merge -> {$tc['merge_output']}"],
            ['Export Movie -> Images', "{$tc['export_img_input']} -> {$tc['export_img_pattern']} ({$tc['export_img_format']})"],
            ['Images -> Movie Sequence', "{$tc['seq_input']} @ {$tc['seq_fps']}fps -> {$tc['seq_output']}"],
        ];

        $selected = min($state->currentIndex(), count($tools) - 1);
        $leftLines = [
            $theme->muted('Use [↑/↓] and [Enter] to configure or execute media tools'),
            $theme->muted(str_repeat('─', max(10, $leftWidth - 4))),
        ];

        foreach ($tools as $idx => [$key, $val]) {
            $line = sprintf('%-24s : %s', $key, $val);
            $leftLines[] = $idx === $selected ? $theme->selectedRow(Ansi::fit($line, $leftWidth - 4)) : $line;
        }

        $allTrans = $this->transitions->all();
        $rightLines = [
            $theme->accent('Transitions Catalog ('.count($allTrans).' incl. Logo Sting):'),
            '  Active: '.$theme->success($state->activeTransition).' ('.$state->transitionDuration.'s)  [T] Switch Transition',
            $theme->muted('  '.implode(', ', array_keys($allTrans))),
            '',
            $theme->bold('1. Video Thumbnail Contact Sheet Creator'),
            '   CLI: ffmix contact-sheet <in> <out> --cols=4 --rows=4',
            '',
            $theme->bold('2. Merging Media Files & Transitions / Logo Sting'),
            '   CLI: ffmix merge a.mp4 b.mp4 --mode=passthru|complex',
            '   CLI: ffmix transition a.mp4 b.mp4 --transition=logo_sting',
            '',
            $theme->bold('3. Export Movies <-> Images (.png/.bmp/.jpg/.tiff/.dpx/.sgi/.tga)'),
            '   CLI: ffmix images to-images|to-movie <in> <out>',
        ];

        $leftBox = Ansi::box('Contact Sheet, Merge & Image Sequences', $leftLines, $leftWidth, $height, $theme, true);
        $rightBox = Ansi::box('Transitions & Utility Reference', $rightLines, $rightWidth, $height, $theme);

        return Ansi::joinColumns($leftBox, $rightBox);
    }

    /**
     * Tab 7: Drop Zones.
     *
     * @return list<string>
     */
    private function renderDropZonesTab(DashboardState $state, int $width, int $height, Theme $theme): array
    {
        $leftWidth = (int) floor($width * 0.52);
        $rightWidth = $width - $leftWidth - 1;

        $zones = DropZone::query()->orderBy('id')->get();
        $selectedIdx = min($state->currentIndex(), max(0, $zones->count() - 1));

        $leftLines = [
            $theme->muted('[n] New Drop Zone   [s] Scan Active Zones   [Space] Toggle Active'),
            $theme->muted(str_repeat('─', max(10, $leftWidth - 4))),
        ];

        if ($zones->isEmpty()) {
            $leftLines[] = '';
            $leftLines[] = $theme->muted('  No Drop Zones configured.');
            $leftLines[] = $theme->accent('  Press [n] to create an automated Drop Zone watch folder.');
        } else {
            foreach ($zones as $idx => $zone) {
                $status = $zone->is_active ? $theme->success('● ON ') : $theme->muted('○ OFF');
                $row = "#{$zone->id} {$status} ".Ansi::fit($zone->name, 18)." preset:{$zone->preset_slug} ({$zone->processed_count} jobs)";
                $leftLines[] = $idx === $selectedIdx ? $theme->selectedRow(Ansi::fit(Ansi::strip($row), $leftWidth - 4)) : $row;
            }
        }

        $rightLines = [];
        $selectedZone = $zones->get($selectedIdx);
        if ($selectedZone instanceof DropZone) {
            $rightLines[] = $theme->accent("Drop Zone: {$selectedZone->name}");
            $rightLines[] = 'Watch Path  : '.$selectedZone->watch_path;
            $rightLines[] = 'Output Path : '.$selectedZone->output_path;
            $rightLines[] = 'Preset      : '.$selectedZone->preset_slug;
            $rightLines[] = 'Extensions  : '.implode(', ', $selectedZone->extensions ?? ['mp4', 'mov', 'mkv']);
            $rightLines[] = 'Post Action : '.$selectedZone->post_action;
            $rightLines[] = 'Processed   : '.$selectedZone->processed_count.' files';
            $rightLines[] = 'Last Scan   : '.($selectedZone->last_scanned_at?->toDateTimeString() ?? 'Never');
        } else {
            $rightLines[] = $theme->accent('Automated Drop Zones');
            $rightLines[] = 'Create unlimited drop zones that map watched folders to';
            $rightLines[] = 'target presets and automatically dispatch batch jobs.';
            $rightLines[] = '';
            $rightLines[] = 'CLI Daemon: ffmix dropzone watch';
        }

        $leftBox = Ansi::box('Drop Zones (Watch Folders)', $leftLines, $leftWidth, $height, $theme, true);
        $rightBox = Ansi::box('Drop Zone Inspector', $rightLines, $rightWidth, $height, $theme);

        return Ansi::joinColumns($leftBox, $rightBox);
    }

    /**
     * Tab 8: Monitor & Probe Inspector.
     *
     * @return list<string>
     */
    private function renderMonitorTab(DashboardState $state, int $width, int $height, Theme $theme): array
    {
        $leftWidth = (int) floor($width * 0.56);
        $rightWidth = $width - $leftWidth - 1;

        $logs = $this->runner->monitorLogs(max(8, $height - 5));
        $leftLines = [
            $theme->muted('Live FFmpeg Worker Output & Progress Stream ([i] Probe File)'),
            $theme->muted(str_repeat('─', max(10, $leftWidth - 4))),
        ];

        if ($logs === []) {
            $leftLines[] = $theme->muted('  Waiting for FFmpeg worker activity...');
        } else {
            foreach ($logs as $log) {
                $leftLines[] = Ansi::truncate($log, $leftWidth - 5);
            }
        }

        $rightLines = [
            $theme->accent('FFprobe Media Stream Inspector:'),
        ];

        $probe = $state->lastProbe;
        if ($probe === null) {
            $rightLines[] = $theme->muted('  Press [i] to probe a media file with ffprobe.');
        } else {
            $rightLines[] = 'File     : '.basename((string) ($probe['path'] ?? ''));
            $rightLines[] = 'Format   : '.(string) ($probe['format_name'] ?? 'unknown');
            $rightLines[] = sprintf('Duration : %.2fs', (float) ($probe['duration'] ?? 0));
            $rightLines[] = 'Bitrate  : '.$this->bitrateAnalyzer->formatBps((int) ($probe['bitrate_bps'] ?? 0));
            if (is_array($probe['video'] ?? null)) {
                $v = $probe['video'];
                $rightLines[] = sprintf('Video    : %s %dx%d @ %sfps (%s)', $v['codec'], $v['width'], $v['height'], $v['fps'], $v['pix_fmt']);
                $rightLines[] = 'Color    : '.$v['color_space'];
            }
            if (is_array($probe['audio'] ?? null)) {
                $a = $probe['audio'];
                $rightLines[] = sprintf('Audio    : %s %dHz %dch (%s)', $a['codec'], $a['sample_rate'], $a['channels'], $a['channel_layout']);
            }
            $rightLines[] = 'Subtitles: '.(int) ($probe['subtitles_count'] ?? 0).' stream(s)';
        }

        $leftBox = Ansi::box('Monitor Output (Live FFmpeg Stream)', $leftLines, $leftWidth, $height, $theme, true);
        $rightBox = Ansi::box('Media Probe Inspector', $rightLines, $rightWidth, $height, $theme);

        return Ansi::joinColumns($leftBox, $rightBox);
    }

    /**
     * Tab 9: Show Presets, Background Watchdog Normalization, Transitions & HTML Downstream Keyer.
     *
     * @return list<string>
     */
    private function renderShowTab(DashboardState $state, int $width, int $height, Theme $theme): array
    {
        $leftWidth = (int) floor($width * 0.50);
        $rightWidth = $width - $leftWidth - 1;

        $wdStatus = $this->watchdog->daemonStatus();
        $wdLabel = $wdStatus['running'] ? "RUNNING (PID {$wdStatus['pid']})" : 'STOPPED ([w] Start / [z] Scan)';

        $items = [
            ['Active Show Name', $state->activeShowName.' ([S] switch Show)'],
            ['Show Preset Actions', '[W] Save  │  [I] Import JSON  │  [E] Export JSON'],
            ['Watchdog Ingest Dir', $state->watchdogInputDir],
            ['Normalized MP4 Dir', $state->watchdogNormalizedDir],
            ['Norm Target Format', "{$state->normalizeWidth}x{$state->normalizeHeight} @ {$state->normalizeFps}fps MP4 (slate {$state->slateDuration}s)"],
            ['Background Watchdog', $wdLabel],
            ['Active Transition', "{$state->activeTransition} ({$state->transitionDuration}s) ([T] change)"],
            ['DSK HTML Folder', $state->dskFolder],
            ['DSK Active HTML', $state->dskTemplate],
            ['DSK Lower-Third Text', "{$state->dskTitle} │ {$state->dskSubtitle}"],
            ['DSK Timing & Accent', "start={$state->dskStartAt}s dur={$state->dskDuration}s color={$state->dskAccent}"],
        ];

        $selected = min($state->currentIndex(), count($items) - 1);
        $leftLines = [
            $theme->muted('[Enter] Edit  [S] Show Picker  [w] Watchdog  [z] Normalize Now  [T] Transition'),
            $theme->muted(str_repeat('─', max(10, $leftWidth - 4))),
        ];

        foreach ($items as $idx => [$key, $val]) {
            $line = sprintf('%-21s : %s', $key, $val);
            $leftLines[] = $idx === $selected ? $theme->selectedRow(Ansi::fit($line, $leftWidth - 4)) : $line;
        }

        $assets = NormalizedAsset::query()->orderByDesc('id')->limit(6)->get();
        $templates = $this->dsk->listTemplates($state->dskFolder);
        $shows = ShowPreset::query()->orderBy('id')->get();

        $rightLines = [
            $theme->accent('Normalized Uniform MP4 Assets (Videos + Images/Slates):'),
        ];

        if ($assets->isEmpty()) {
            $rightLines[] = $theme->muted('  No normalized assets yet. Drop media/images into ingest & press [z] or [w].');
        } else {
            foreach ($assets as $asset) {
                $badge = $asset->status === 'ready' ? $theme->success('✓ MP4') : $theme->warning($asset->status);
                $rightLines[] = sprintf('  %s [%-5s] %s -> %s', $badge, $asset->asset_type, basename($asset->source_path), basename($asset->normalized_path));
            }
        }

        $rightLines[] = '';
        $rightLines[] = $theme->accent('HTML Downstream Key Templates ('.count($templates).' in folder):');
        foreach (array_slice($templates, 0, 4) as $tpl) {
            $mark = $tpl['filename'] === $state->dskTemplate ? $theme->success('● ') : $theme->muted('○ ');
            $rightLines[] = '  '.$mark.$tpl['filename'].' — '.$theme->muted($tpl['title']);
        }

        $rightLines[] = '';
        $rightLines[] = $theme->accent('Saved Show Presets ('.$shows->count().'):');
        foreach ($shows->take(4) as $sp) {
            $mark = $sp->slug === $state->activeShowSlug ? $theme->success('★ ') : $theme->muted('· ');
            $rightLines[] = '  '.$mark.$sp->slug.' ('.$sp->name.')';
        }

        $leftBox = Ansi::box('Show Control, Watchdog & HTML DSK', $leftLines, $leftWidth, $height, $theme, true);
        $rightBox = Ansi::box('Normalized MP4s, DSK Templates & Show Presets', $rightLines, $rightWidth, $height, $theme);

        return Ansi::joinColumns($leftBox, $rightBox);
    }

    /**
     * Overlay centered modal dialog on top of rendered body lines.
     *
     * @param  list<string>  $bodyLines
     * @return list<string>
     */
    private function overlayModal(
        array $bodyLines,
        DashboardState $state,
        int $width,
        int $height,
        Theme $theme,
    ): array {
        $modalWidth = min($width - 6, 78);
        $modalLines = [];
        $modalTitle = 'Dialog';

        if ($state->modal === 'help') {
            $modalTitle = 'ffMix TUI Keyboard Shortcuts & Broadcast Controls';
            $modalLines = [
                $theme->accent('Navigation & Appearance'),
                '  1-9 / Tab      Switch Queue, Studio, FX/LUT, Audio, Analyze, Tools, DropZone, Monitor, Show/DSK',
                '  ↑ / ↓ / j / k  Move selection cursor',
                '  t              Toggle Dark / Light theme',
                '  ? / Esc        Open / Close Help modal',
                '  q              Quit cleanly and restore terminal',
                '',
                $theme->accent('Show Presets, Watchdog & Transitions'),
                '  S / W / I / E  Select Show Preset / Save Show / Import JSON / Export JSON',
                '  w / z          Start/Stop Background Watchdog / Scan & Normalize MP4s now',
                '  T              Select Transition (20 basic xfade + Logo Sting)',
                '',
                $theme->accent('Queue, Studio, FX & Analysis'),
                '  a / r / x / R  Add Job / Run 8x Workers / Cancel / Retry',
                '  p / h / /      Pick Preset / Toggle VideoToolbox HW / Search +450 Options',
                '  l / c / o      84 Pro LUTs / Auto CropDetect / Add Overlay',
                '  b / v / i      Bitrate Analyser / VMAF+PSNR+SSIM+CAMBI / Probe File',
            ];
        } elseif ($state->modal === 'preset_picker') {
            $modalTitle = 'Select Preset ([↑/↓] + [Enter])';
            $presets = Preset::query()->orderBy('category')->orderBy('name')->get();
            foreach ($presets as $idx => $p) {
                $row = sprintf('%-18s %-14s %s', $p->slug, $p->category, $p->name);
                $modalLines[] = $idx === $state->modalCursor ? $theme->selectedRow(Ansi::fit($row, $modalWidth - 6)) : $row;
            }
        } elseif ($state->modal === 'show_picker') {
            $modalTitle = 'Select Entire Show Preset ([↑/↓] + [Enter])';
            $shows = ShowPreset::query()->orderBy('id')->get();
            foreach ($shows as $idx => $sp) {
                $active = $sp->is_active ? '★' : ' ';
                $row = sprintf('%s %-20s %s', $active, $sp->slug, $sp->name);
                $modalLines[] = $idx === $state->modalCursor ? $theme->selectedRow(Ansi::fit($row, $modalWidth - 6)) : $row;
            }
        } elseif ($state->modal === 'transition_picker') {
            $modalTitle = 'Select Transition ([↑/↓] + [Enter])';
            $all = $this->transitions->all();
            $keys = array_keys($all);
            $offset = max(0, $state->modalCursor - 6);
            foreach (array_slice($keys, $offset, 12, true) as $idx => $key) {
                $item = $all[$key];
                $row = sprintf('%-14s %-22s %s', $key, $item['label'], $item['description']);
                $modalLines[] = $idx === $state->modalCursor ? $theme->selectedRow(Ansi::fit($row, $modalWidth - 6)) : $row;
            }
        } elseif ($state->modal === 'lut_picker') {
            $modalTitle = 'Pro LUT Presets (84) ([↑/↓] + [Enter])';
            $luts = $this->catalog->lutPresets();
            $offset = max(0, $state->modalCursor - 6);
            foreach (array_slice($luts, $offset, 14, true) as $idx => $lut) {
                $row = sprintf('%-22s %-20s %s', $lut['slug'], $lut['category'], $lut['name']);
                $modalLines[] = $idx === $state->modalCursor ? $theme->selectedRow(Ansi::fit($row, $modalWidth - 6)) : $row;
            }
        } elseif ($state->modal === 'catalog_search') {
            $modalTitle = 'Search +450 FFmpeg Options (type to filter, [Esc] close)';
            $modalLines[] = 'Filter: '.$theme->accent($state->modalInput.'█');
            $modalLines[] = $theme->muted(str_repeat('─', $modalWidth - 6));
            foreach ($this->catalog->searchAdvancedOptions($state->modalInput, 12) as $opt) {
                $modalLines[] = sprintf('%-24s %-22s %s', $opt['flag'], Ansi::truncate($opt['category'], 20), Ansi::truncate($opt['description'], 24));
            }
        } else {
            $modalTitle = 'Input Required ([Enter] confirm, [Esc] cancel)';
            $modalLines = [
                $state->modalPrompt,
                '',
                $theme->accent('> '.$state->modalInput.'█'),
            ];
        }

        $modalHeight = min($height - 2, max(8, count($modalLines) + 4));
        $box = Ansi::box($modalTitle, $modalLines, $modalWidth, $modalHeight, $theme, true);

        $topOffset = max(1, (int) floor(($height - $modalHeight) / 2));
        $leftPad = str_repeat(' ', max(0, (int) floor(($width - $modalWidth) / 2)));

        foreach ($box as $i => $line) {
            $targetRow = $topOffset + $i;
            if (isset($bodyLines[$targetRow])) {
                $bodyLines[$targetRow] = $leftPad.$line;
            }
        }

        return $bodyLines;
    }
}
