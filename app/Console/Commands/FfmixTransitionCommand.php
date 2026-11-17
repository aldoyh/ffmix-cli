<?php

namespace App\Console\Commands;

use App\Services\Ffmix\TransitionService;
use Illuminate\Console\Command;

class FfmixTransitionCommand extends Command
{
    protected $signature = 'ffmix:transition
        {inputs?* : Two or more input video paths (omit or pass --list to list transitions)}
        {--list : List all available transitions including Logo Sting}
        {--transition=logo_sting : Transition name (cut, fade, fadeblack, fadewhite, wipeleft, ..., logo_sting)}
        {--duration=1.0 : Transition duration in seconds}
        {--sting= : Optional logo/video sting asset path for logo_sting transition}
        {--output=transition_out.mp4 : Output file path}
        {--dry-run : Print compiled FFmpeg command without executing}';

    protected $description = 'Join clips using basic xfade transitions or a Logo Sting transition';

    public function handle(TransitionService $service): int
    {
        if ((bool) $this->option('list') || empty($this->argument('inputs'))) {
            $rows = [];
            foreach ($service->all() as $key => $meta) {
                $rows[] = [$key, $meta['label'], $meta['description']];
            }
            $this->table(['Key', 'Label', 'Description'], $rows);

            return self::SUCCESS;
        }

        /** @var list<string> $inputs */
        $inputs = array_values((array) $this->argument('inputs'));
        $transition = (string) $this->option('transition');
        $duration = max(0.2, (float) $this->option('duration'));
        $sting = $this->option('sting') ? (string) $this->option('sting') : null;
        $output = (string) $this->option('output');

        try {
            if ((bool) $this->option('dry-run')) {
                $args = $service->buildCommand($inputs, $output, $transition, $duration, stingAssetPath: $sting);
                $this->line(implode(' ', array_map('escapeshellarg', $args)));

                return self::SUCCESS;
            }

            $res = $service->apply($inputs, $output, $transition, $duration, $sting);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Rendered '{$res['transition']}' transition -> {$res['output']}");

        return self::SUCCESS;
    }
}
