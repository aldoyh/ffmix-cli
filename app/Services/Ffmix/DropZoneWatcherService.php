<?php

namespace App\Services\Ffmix;

use App\Models\DropZone;
use App\Models\MediaJob;
use App\Models\Preset;

class DropZoneWatcherService
{
    public function __construct(private FfmpegCommandBuilder $builder) {}

    /**
     * Scan all active Drop Zones (or a single DropZone) and enqueue matching new media files.
     *
     * @return list<MediaJob>
     */
    public function scan(?DropZone $onlyZone = null): array
    {
        $zones = $onlyZone !== null
            ? collect([$onlyZone])
            : DropZone::query()->where('is_active', true)->orderBy('id')->get();

        $createdJobs = [];

        foreach ($zones as $zone) {
            if (! is_dir($zone->watch_path)) {
                continue;
            }

            if (! is_dir($zone->output_path)) {
                @mkdir($zone->output_path, 0775, true);
            }

            $preset = Preset::query()->where('slug', $zone->preset_slug)->first();
            $configArr = $preset !== null ? $preset->config : [];
            $config = PipelineConfig::fromArray($configArr);
            $allowedExts = array_map('strtolower', $zone->extensions ?? ['mp4', 'mov', 'mkv', 'mxf', 'avi', 'webm']);

            $entries = scandir($zone->watch_path) ?: [];
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                $fullPath = rtrim($zone->watch_path, '/').'/'.$entry;
                if (! is_file($fullPath)) {
                    continue;
                }

                $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
                if (! in_array($ext, $allowedExts, true)) {
                    continue;
                }

                // Skip if already queued/completed for this input & output
                $baseName = pathinfo($entry, PATHINFO_FILENAME);
                $outputFile = rtrim($zone->output_path, '/').'/'.$baseName.'.'.$config->container;

                $alreadyQueued = MediaJob::query()
                    ->where('input_path', $fullPath)
                    ->where('output_path', $outputFile)
                    ->exists();

                if ($alreadyQueued || is_file($outputFile)) {
                    continue;
                }

                $preview = $this->builder->toShellString(
                    $this->builder->build($fullPath, $outputFile, $config, true)
                );

                $job = MediaJob::query()->create([
                    'name' => "[DZ:{$zone->name}] {$entry}",
                    'input_path' => $fullPath,
                    'output_path' => $outputFile,
                    'preset_slug' => $zone->preset_slug,
                    'pipeline_config' => $config->toArray(),
                    'command_preview' => $preview,
                ]);

                $createdJobs[] = $job;
                $zone->increment('processed_count');
            }

            $zone->last_scanned_at = now();
            $zone->save();
        }

        return $createdJobs;
    }
}
