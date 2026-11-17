<?php

namespace App\Console\Commands;

use App\Models\NormalizedAsset;
use App\Services\Ffmix\FolderWatchdogService;
use App\Services\Ffmix\ShowPresetService;
use Illuminate\Console\Command;

class FfmixWatchdogCommand extends Command
{
    protected $signature = 'ffmix:watchdog
        {action=scan : Action (scan|start|stop|status|run|list)}
        {--interval=3 : Poll interval in seconds}
        {--max-ticks=0 : Maximum iterations in run mode (0 = continuous)}
        {--dry-run : Record pending assets without running FFmpeg}';

    protected $description = 'Background folder monitor/watchdog that normalizes videos, images, and slates into uniform MP4 files';

    public function handle(FolderWatchdogService $watchdog, ShowPresetService $shows): int
    {
        $action = (string) $this->argument('action');
        $show = $shows->active();

        if ($action === 'start') {
            $pid = $watchdog->startBackgroundDaemon((int) $this->option('interval'));
            $this->info("Background Folder Watchdog started (PID {$pid}) for Show '{$show->name}'.");

            return self::SUCCESS;
        }

        if ($action === 'stop') {
            $stopped = $watchdog->stopBackgroundDaemon();
            $this->info($stopped ? 'Background Folder Watchdog stopped.' : 'Watchdog was not running.');

            return self::SUCCESS;
        }

        if ($action === 'status') {
            $status = $watchdog->daemonStatus();
            $this->info($status['running'] ? "Running (PID {$status['pid']})" : 'Stopped');

            return self::SUCCESS;
        }

        if ($action === 'list') {
            $assets = NormalizedAsset::query()->orderByDesc('id')->get();
            $this->table(
                ['ID', 'Type', 'Status', 'Source', 'Normalized MP4'],
                $assets->map(static fn (NormalizedAsset $a): array => [
                    $a->id,
                    $a->asset_type,
                    $a->status,
                    basename($a->source_path),
                    basename($a->normalized_path),
                ])->all(),
            );

            return self::SUCCESS;
        }

        if ($action === 'run') {
            $interval = max(1, (int) $this->option('interval'));
            $maxTicks = max(0, (int) $this->option('max-ticks'));
            $ticks = 0;

            $this->info("Folder Watchdog running for Show '{$show->name}' every {$interval}s...");
            while ($maxTicks === 0 || $ticks < $maxTicks) {
                $watchdog->scanAndNormalize($show, (bool) $this->option('dry-run'));
                $ticks++;
                if ($maxTicks === 0 || $ticks < $maxTicks) {
                    sleep($interval);
                }
            }

            return self::SUCCESS;
        }

        $processed = $watchdog->scanAndNormalize($show, (bool) $this->option('dry-run'));
        $this->info('Watchdog normalized '.count($processed)." asset(s) for Show '{$show->name}'.");

        return self::SUCCESS;
    }
}
