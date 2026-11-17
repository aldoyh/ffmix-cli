<?php

namespace App\Console\Commands;

use App\Services\Ffmix\ImageSequenceService;
use Illuminate\Console\Command;

class FfmixImagesCommand extends Command
{
    protected $signature = 'ffmix:images
        {mode : Action mode (to-images|to-movie)}
        {input : Input movie file or image sequence pattern (e.g. frames/frame_%04d.png)}
        {output : Output pattern or target movie file}
        {--format=png : Export image format (png|bmp|jpg|tiff|dpx|sgi|tga)}
        {--fps=24 : Frame rate for export or assembly}
        {--codec=libx264 : Video codec when assembling images to movie}
        {--dry-run : Print FFmpeg command without executing}';

    protected $description = 'Export movies to image sequences (.png/.bmp/.jpg/.tiff/.dpx/.sgi/.tga) or assemble images into movies';

    public function handle(ImageSequenceService $service): int
    {
        $mode = (string) $this->argument('mode');
        $input = (string) $this->argument('input');
        $output = (string) $this->argument('output');

        try {
            $args = match ($mode) {
                'to-images' => $service->buildExportImagesCommand(
                    $input,
                    $output,
                    (string) $this->option('format'),
                    (float) $this->option('fps'),
                ),
                'to-movie' => $service->buildImagesToMovieCommand(
                    $input,
                    $output,
                    (float) $this->option('fps'),
                    (string) $this->option('codec'),
                ),
                default => throw new \InvalidArgumentException("Unknown mode '{$mode}'. Use 'to-images' or 'to-movie'."),
            };

            if ((bool) $this->option('dry-run')) {
                $this->line(implode(' ', array_map('escapeshellarg', $args)));

                return self::SUCCESS;
            }

            $service->execute($args);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Completed {$mode} -> {$output}");

        return self::SUCCESS;
    }
}
