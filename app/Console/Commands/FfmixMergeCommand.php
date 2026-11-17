<?php

namespace App\Console\Commands;

use App\Enums\MergeMode;
use App\Services\Ffmix\MediaMergeService;
use Illuminate\Console\Command;

class FfmixMergeCommand extends Command
{
    protected $signature = 'ffmix:merge
        {inputs* : Two or more media file paths to stitch together}
        {--mode=passthru : Merge mode (passthru|complex)}
        {--output=merged.mp4 : Output file path}
        {--dry-run : Print compiled FFmpeg command without executing}';

    protected $description = 'Stitch unlimited media files together using concat passthru or complex filter';

    public function handle(MediaMergeService $service): int
    {
        /** @var list<string> $inputs */
        $inputs = array_values((array) $this->argument('inputs'));
        $output = (string) $this->option('output');
        $mode = MergeMode::tryFrom((string) $this->option('mode')) ?? MergeMode::Passthru;

        try {
            if ((bool) $this->option('dry-run')) {
                $built = $service->buildCommand($inputs, $output, $mode, createManifest: false);
                $this->line(implode(' ', array_map('escapeshellarg', $built['args'])));

                return self::SUCCESS;
            }

            $res = $service->merge($inputs, $output, $mode);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Merged {$res['count']} files ({$res['mode']}) -> {$res['output']}");

        return self::SUCCESS;
    }
}
