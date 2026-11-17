<?php

namespace App\Console\Commands;

use App\Enums\DashboardTab;
use App\Enums\JobStatus;
use App\Enums\ThemeMode;
use App\Models\AnalysisReport;
use App\Models\DropZone;
use App\Models\MediaJob;
use App\Models\Preset;
use App\Services\Ffmix\DownstreamKeyService;
use App\Services\Ffmix\FfmpegCommandBuilder;
use App\Services\Ffmix\FfmpegEnvironmentService;
use App\Services\Ffmix\PipelineConfig;
use App\Services\Ffmix\ShowPresetService;
use App\Tui\Ansi;
use App\Tui\DashboardController;
use App\Tui\DashboardRenderer;
use App\Tui\DashboardState;
use App\Tui\TerminalScreen;
use Illuminate\Console\Command;
use Symfony\Component\Console\Output\OutputInterface;

class FfmixDashboardCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ffmix
        {--tab=queue : Initial tab (queue|studio|fx|audio|analyzer|tools|dropzones|monitor|show)}
        {--theme=dark : TUI theme (dark|light)}
        {--snapshot : Render a single TUI frame and exit without raw TTY mode}
        {--demo : Seed demo queue jobs, drop zones, and analyzer telemetry if empty}
        {--width=124 : Snapshot width in columns}
        {--height=36 : Snapshot height in rows}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Launch the ffMix interactive TUI Broadcast & Media Workstation Dashboard';

    public function handle(
        DashboardController $controller,
        DashboardRenderer $renderer,
        FfmpegEnvironmentService $environment,
        FfmpegCommandBuilder $builder,
        ShowPresetService $showPresets,
        DownstreamKeyService $dsk,
    ): int {
        Preset::syncBuiltins();
        $activeShow = $showPresets->active();

        if ((bool) $this->option('demo')) {
            $this->seedDemoData($builder);
        }

        $theme = ThemeMode::tryFrom((string) $this->option('theme')) ?? ThemeMode::Dark;
        $tab = DashboardTab::tryFrom((string) $this->option('tab')) ?? DashboardTab::Queue;

        $state = new DashboardState($theme);
        $state->tab = $tab;
        $state->applyShowPreset($activeShow);
        $dsk->ensureSampleTemplate($state->dskFolder);

        if ((bool) $this->option('snapshot') || ! $this->input->isInteractive()) {
            $state->environment = $environment->inspect();
            $width = max(80, (int) $this->option('width'));
            $height = max(24, (int) $this->option('height'));
            $frame = $renderer->render($state, $width, $height);
            $rendered = $this->output->isDecorated() ? $frame : Ansi::strip($frame);
            $this->output->writeln($rendered, OutputInterface::OUTPUT_RAW);

            return self::SUCCESS;
        }

        $controller->run($state, new TerminalScreen);

        return self::SUCCESS;
    }

    private function seedDemoData(FfmpegCommandBuilder $builder): void
    {
        if (MediaJob::query()->count() === 0) {
            $samples = [
                ['keynote_4k_master.mov', 'youtube', JobStatus::Running, 1, 64.5, 48.2, '2.1x', '14.2 Mbps'],
                ['reel_vertical_cut.mp4', 'tiktok', JobStatus::Running, 2, 38.0, 62.4, '2.6x', '6.4 Mbps'],
                ['trailer_prores_hq.mov', 'production', JobStatus::Completed, null, 100.0, 54.0, '1.9x', '118.0 Mbps'],
                ['podcast_episode_12.wav', 'iphone', JobStatus::Pending, null, 0.0, 0.0, null, null],
            ];

            foreach ($samples as [$name, $slug, $status, $slot, $pct, $fps, $speed, $bitrate]) {
                $preset = Preset::query()->where('slug', $slug)->first();
                $config = PipelineConfig::fromArray($preset !== null ? $preset->config : []);
                $out = preg_replace('/\.[^.]+$/', '', $name).'_out.'.$config->container;

                MediaJob::query()->create([
                    'name' => $name,
                    'input_path' => "/Media/Inbox/{$name}",
                    'output_path' => "/Media/Exports/{$out}",
                    'preset_slug' => $slug,
                    'pipeline_config' => $config->toArray(),
                    'command_preview' => $builder->toShellString($builder->build("/Media/Inbox/{$name}", "/Media/Exports/{$out}", $config)),
                    'status' => $status,
                    'worker_slot' => $slot,
                    'progress_percent' => $pct,
                    'fps' => $fps,
                    'speed' => $speed,
                    'bitrate' => $bitrate,
                    'duration_seconds' => 120.0,
                    'processed_seconds' => round(120.0 * ($pct / 100.0), 1),
                ]);
            }
        }

        if (DropZone::query()->count() === 0) {
            DropZone::query()->create([
                'name' => 'YouTube 4K Drop',
                'watch_path' => '/tmp/ffmix-youtube-in',
                'output_path' => '/tmp/ffmix-youtube-out',
                'preset_slug' => 'youtube',
                'extensions' => ['mov', 'mp4', 'mkv'],
                'is_active' => true,
                'processed_count' => 14,
            ]);
            DropZone::query()->create([
                'name' => 'iPhone HEVC Auto',
                'watch_path' => '/tmp/ffmix-iphone-in',
                'output_path' => '/tmp/ffmix-iphone-out',
                'preset_slug' => 'iphone',
                'extensions' => ['mov', 'mp4', 'mxf'],
                'is_active' => true,
                'processed_count' => 7,
            ]);
        }

        if (AnalysisReport::query()->count() === 0) {
            AnalysisReport::query()->create([
                'kind' => 'bitrate',
                'source_path' => '/Media/Exports/keynote_4k_master.mp4',
                'summary' => [
                    'duration_seconds' => 16.0,
                    'avg_bps' => 12450000,
                    'min_bps' => 6200000,
                    'max_bps' => 22800000,
                    'p95_bps' => 20400000,
                    'keyframes' => 8,
                ],
                'series' => [6200000, 8400000, 11200000, 15800000, 22800000, 19200000, 13400000, 9800000, 11000000, 14600000, 20400000, 16200000, 10800000, 8900000, 9500000, 11400000],
            ]);

            AnalysisReport::query()->create([
                'kind' => 'quality',
                'source_path' => '/Media/Exports/keynote_4k_master.mp4',
                'reference_path' => '/Media/Inbox/keynote_4k_master.mov',
                'summary' => [
                    'vmaf' => 95.84,
                    'psnr_y' => 44.12,
                    'psnr_avg' => 43.88,
                    'ssim' => 0.9862,
                    'cambi' => 0.42,
                    'grade' => 'Transparent / Mastering',
                ],
                'series' => [95.2, 95.8, 96.1, 95.4, 96.3],
            ]);
        }
    }
}
