<?php

namespace App\Console\Commands;

use App\Models\DropZone;
use App\Services\Ffmix\BatchQueueRunner;
use App\Services\Ffmix\DropZoneWatcherService;
use Illuminate\Console\Command;

class FfmixDropZoneCommand extends Command
{
    protected $signature = 'ffmix:dropzone
        {action=list : Action (list|create|scan|watch)}
        {--name= : Drop Zone name}
        {--watch= : Directory path to watch}
        {--output= : Directory path for converted outputs}
        {--preset=desktop : Preset slug to apply}
        {--interval=2 : Poll interval in seconds for watch mode}
        {--max-ticks=0 : Maximum watch iterations (0 = unlimited)}';

    protected $description = 'Manage unlimited Drop Zones (watch folders) and auto-process media files';

    public function handle(DropZoneWatcherService $watcher, BatchQueueRunner $runner): int
    {
        $action = (string) $this->argument('action');

        if ($action === 'create') {
            $name = (string) ($this->option('name') ?: 'Drop Zone');
            $watch = (string) ($this->option('watch') ?: '');
            $output = (string) ($this->option('output') ?: '');
            if ($watch === '' || $output === '') {
                $this->error('--watch and --output are required when creating a Drop Zone.');

                return self::FAILURE;
            }

            $zone = DropZone::query()->create([
                'name' => $name,
                'watch_path' => $watch,
                'output_path' => $output,
                'preset_slug' => (string) $this->option('preset'),
                'is_active' => true,
            ]);

            $this->info("Created Drop Zone #{$zone->id}: {$zone->name}");

            return self::SUCCESS;
        }

        if ($action === 'scan') {
            $jobs = $watcher->scan();
            $this->info('Scanned active Drop Zones: enqueued '.count($jobs).' job(s).');

            return self::SUCCESS;
        }

        if ($action === 'watch') {
            $interval = max(1, (int) $this->option('interval'));
            $maxTicks = max(0, (int) $this->option('max-ticks'));
            $ticks = 0;
            $this->info("Watching active Drop Zones every {$interval}s (Ctrl+C to stop)...");
            while ($maxTicks === 0 || $ticks < $maxTicks) {
                $watcher->scan();
                $runner->tick();
                $ticks++;
                if ($maxTicks === 0 || $ticks < $maxTicks) {
                    sleep($interval);
                }
            }

            return self::SUCCESS;
        }

        $zones = DropZone::query()->orderBy('id')->get();
        $this->table(
            ['ID', 'Name', 'Watch Path', 'Output Path', 'Preset', 'Active', 'Processed'],
            $zones->map(static fn (DropZone $z): array => [
                $z->id,
                $z->name,
                $z->watch_path,
                $z->output_path,
                $z->preset_slug,
                $z->is_active ? 'Yes' : 'No',
                $z->processed_count,
            ])->all(),
        );

        return self::SUCCESS;
    }
}
