<?php

namespace App\Console\Commands;

use App\Enums\JobStatus;
use App\Enums\SubtitleMode;
use App\Models\MediaJob;
use App\Models\Preset;
use App\Services\Ffmix\AutoCropService;
use App\Services\Ffmix\FfmpegCommandBuilder;
use App\Services\Ffmix\PipelineConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class FfmixConvertCommand extends Command
{
    protected $signature = 'ffmix:convert
        {input : Input media file}
        {output? : Output media file}
        {--preset=desktop : Preset slug}
        {--codec= : Video codec override (h264|h265|h266|av1|prores|dnxhd|vp9|gif|copy)}
        {--audio-codec= : Audio codec override (aac|alac|mp3|flac|wav|opus|vorbis|copy)}
        {--resolution= : Resolution tier (source|720p|1080p|4k|5k|8k)}
        {--hw : Enable Apple VideoToolbox hardware acceleration}
        {--crf= : Constant Rate Factor (0-51)}
        {--start= : Trim start timestamp}
        {--end= : Trim end timestamp}
        {--lut= : Pro LUT preset slug (from 84-LUT catalog)}
        {--lut-file= : Path to .cube/.3dl LUT file}
        {--colorspace= : Target colorspace (auto|bt709|bt2020|bt601|srgb)}
        {--crop= : Manual crop W:H:X:Y}
        {--autocrop : Auto-detect and remove black borders}
        {--overlay=* : Overlay image or video paths}
        {--subtitles= : Subtitle file path}
        {--sub-mode=soft : Subtitle mode (soft|hardburn)}
        {--loudnorm : Enable EBU R128 loudness normalization}
        {--queue : Enqueue into batch worker pool instead of running synchronously}
        {--dry-run : Print compiled FFmpeg command without executing}';

    protected $description = 'Convert media with presets, VideoToolbox HW, color/LUTs, crop, overlays, subtitles & audio DSP';

    public function handle(
        FfmpegCommandBuilder $builder,
        AutoCropService $autoCrop,
    ): int {
        Preset::syncBuiltins();

        $input = (string) $this->argument('input');
        $presetSlug = (string) $this->option('preset');
        $preset = Preset::query()->where('slug', $presetSlug)->first();
        $config = PipelineConfig::fromArray($preset !== null ? $preset->config : []);

        if ($codec = $this->option('codec')) {
            $config->videoCodec = (string) $codec;
        }
        if ($audioCodec = $this->option('audio-codec')) {
            $config->audioCodec = (string) $audioCodec;
        }
        if ($res = $this->option('resolution')) {
            $config->resolution = (string) $res;
        }
        if ((bool) $this->option('hw')) {
            $config->hwAccel = true;
        }
        if ($this->option('crf') !== null) {
            $config->crf = (int) $this->option('crf');
        }
        if ($start = $this->option('start')) {
            $config->trimStart = (string) $start;
        }
        if ($end = $this->option('end')) {
            $config->trimEnd = (string) $end;
        }
        if ($lut = $this->option('lut')) {
            $config->color['lut_slug'] = (string) $lut;
        }
        if ($lutFile = $this->option('lut-file')) {
            $config->color['lut_file'] = (string) $lutFile;
        }
        if ($cs = $this->option('colorspace')) {
            $config->color['colorspace'] = (string) $cs;
        }
        if ((bool) $this->option('loudnorm')) {
            $config->audioFilters['loudnorm'] = true;
        }

        if ($cropRaw = $this->option('crop')) {
            $parts = array_map('intval', explode(':', (string) $cropRaw));
            if (count($parts) === 4) {
                $config->crop = [
                    'enabled' => true,
                    'mode' => 'manual',
                    'width' => $parts[0],
                    'height' => $parts[1],
                    'x' => $parts[2],
                    'y' => $parts[3],
                ];
            }
        } elseif ((bool) $this->option('autocrop')) {
            $detected = $autoCrop->detect($input);
            $config->crop = [
                'enabled' => true,
                'mode' => 'auto',
                'width' => $detected['width'],
                'height' => $detected['height'],
                'x' => $detected['x'],
                'y' => $detected['y'],
            ];
        }

        /** @var list<string> $overlayPaths */
        $overlayPaths = (array) $this->option('overlay');
        foreach ($overlayPaths as $ovPath) {
            $config->overlays[] = [
                'path' => $ovPath,
                'x' => 'W-w-20',
                'y' => '20',
                'width' => null,
                'height' => null,
                'opacity' => 1.0,
                'start' => null,
                'end' => null,
            ];
        }

        if ($subPath = $this->option('subtitles')) {
            $config->subtitles = [
                'mode' => SubtitleMode::tryFrom((string) $this->option('sub-mode')) ?? SubtitleMode::Soft,
                'path' => (string) $subPath,
                'language' => 'eng',
            ];
        }

        $output = $this->argument('output')
            ? (string) $this->argument('output')
            : preg_replace('/\.[^.]+$/', '', $input).'_ffmix.'.$config->container;

        $args = $builder->build($input, $output, $config, false);
        $preview = $builder->toShellString($args);

        if ((bool) $this->option('dry-run')) {
            $this->info('Dry-run FFmpeg command:');
            $this->line($preview);

            return self::SUCCESS;
        }

        if ((bool) $this->option('queue')) {
            $job = MediaJob::query()->create([
                'name' => basename($input),
                'input_path' => $input,
                'output_path' => $output,
                'preset_slug' => $presetSlug,
                'pipeline_config' => $config->toArray(),
                'command_preview' => $preview,
                'status' => JobStatus::Pending,
            ]);
            $this->info("Enqueued MediaJob #{$job->id} -> {$output}");

            return self::SUCCESS;
        }

        $this->info("Running: {$preview}");
        $result = Process::timeout(1800)->run($args);
        if (! $result->successful()) {
            $this->error($result->errorOutput() ?: $result->output());

            return self::FAILURE;
        }

        $this->info("Converted -> {$output}");

        return self::SUCCESS;
    }
}
