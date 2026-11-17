<?php

namespace App\Console\Commands;

use App\Services\Ffmix\DownstreamKeyService;
use App\Services\Ffmix\ShowPresetService;
use Illuminate\Console\Command;

class FfmixDskCommand extends Command
{
    protected $signature = 'ffmix:dsk
        {action=list : Action (list|render|apply|init-sample)}
        {--folder= : DSK HTML folder (defaults to active Show DSK folder)}
        {--template=sample-lower-third.html : HTML filename inside DSK folder}
        {--video= : Input video path when action=apply}
        {--output=dsk_output.mov : Output alpha .mov (render) or composited .mp4 (apply)}
        {--title=ffMix Live Broadcast : Lower-third primary title}
        {--subtitle=Normalized 1080p30 Feed : Lower-third subtitle}
        {--accent=#06b6d4 : Hex accent color}
        {--duration=6.0 : Animation duration in seconds}
        {--start=1.0 : Overlay start timestamp in seconds (apply)}
        {--fps=30 : Render frame rate}
        {--dry-run : Print overlay composite command without executing}';

    protected $description = 'Manage animated HTML Downstream Key (DSK) lower-third overlays with In/Out animations';

    public function handle(DownstreamKeyService $dsk, ShowPresetService $shows): int
    {
        $action = (string) $this->argument('action');
        $show = $shows->active();
        $showDsk = is_array($show->config['dsk'] ?? null) ? $show->config['dsk'] : [];
        $folder = (string) ($this->option('folder') ?: ($showDsk['folder'] ?? 'storage/app/ffmix/dsk'));

        if ($action === 'init-sample') {
            $path = $dsk->ensureSampleTemplate($folder);
            $this->info("Sample animated HTML lower-third ready -> {$path}");

            return self::SUCCESS;
        }

        if ($action === 'list') {
            $templates = $dsk->listTemplates($folder);
            $this->table(
                ['Filename', 'Title', 'Description', 'Path'],
                array_map(static fn (array $t): array => [$t['filename'], $t['title'], $t['description'], $t['path']], $templates),
            );

            return self::SUCCESS;
        }

        $resolvedDir = $dsk->resolveFolder($folder);
        $templateFile = (string) $this->option('template');
        $htmlPath = str_starts_with($templateFile, '/') ? $templateFile : rtrim($resolvedDir, '/').'/'.$templateFile;

        if ($action === 'render') {
            try {
                $res = $dsk->renderHtmlToAlphaMov(
                    htmlPath: $htmlPath,
                    outputMovPath: (string) $this->option('output'),
                    title: (string) $this->option('title'),
                    subtitle: (string) $this->option('subtitle'),
                    accent: (string) $this->option('accent'),
                    duration: (float) $this->option('duration'),
                    fps: (int) $this->option('fps'),
                );
            } catch (\Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            $this->info("Rendered {$res['frames']} alpha frames -> {$res['overlay_path']}");

            return self::SUCCESS;
        }

        if ($action === 'apply') {
            $video = (string) ($this->option('video') ?: '');
            if ($video === '') {
                $this->error('--video is required when action=apply.');

                return self::FAILURE;
            }

            $output = (string) $this->option('output');
            $duration = (float) $this->option('duration');
            $startAt = (float) $this->option('start');
            $overlayMov = sys_get_temp_dir().'/ffmix_dsk_'.uniqid('', true).'.mov';

            if ((bool) $this->option('dry-run')) {
                $args = $dsk->buildApplyOverlayCommand($video, $overlayMov, $output, $startAt, $duration);
                $this->line(implode(' ', array_map('escapeshellarg', $args)));

                return self::SUCCESS;
            }

            try {
                $dsk->renderHtmlToAlphaMov(
                    htmlPath: $htmlPath,
                    outputMovPath: $overlayMov,
                    title: (string) $this->option('title'),
                    subtitle: (string) $this->option('subtitle'),
                    accent: (string) $this->option('accent'),
                    duration: $duration,
                    fps: (int) $this->option('fps'),
                );
                $dsk->applyOverlay($video, $overlayMov, $output, $startAt, $duration);
            } catch (\Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            } finally {
                if (is_file($overlayMov)) {
                    @unlink($overlayMov);
                }
            }

            $this->info("Applied HTML Downstream Key '{$templateFile}' -> {$output}");

            return self::SUCCESS;
        }

        $this->error("Unknown DSK action '{$action}'. Use list, render, apply, or init-sample.");

        return self::FAILURE;
    }
}
