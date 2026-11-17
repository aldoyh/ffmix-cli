<?php

namespace App\Console\Commands;

use App\Models\Preset;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class FfmixPresetsCommand extends Command
{
    protected $signature = 'ffmix:presets
        {action=list : Action (list|create)}
        {--name= : Custom preset name}
        {--codec=h264 : Video codec}
        {--audio-codec=aac : Audio codec}
        {--container=mp4 : Output container}
        {--resolution=1080p : Resolution tier}
        {--hw : Enable VideoToolbox HW acceleration}
        {--json : Output JSON}';

    protected $description = 'List built-in device/platform presets or create custom presets';

    public function handle(): int
    {
        Preset::syncBuiltins();

        if ($this->argument('action') === 'create') {
            $name = (string) ($this->option('name') ?: '');
            if ($name === '') {
                $this->error('--name is required to create a preset.');

                return self::FAILURE;
            }

            $preset = Preset::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'category' => 'Custom',
                    'description' => 'User custom preset',
                    'is_builtin' => false,
                    'config' => [
                        'container' => (string) $this->option('container'),
                        'video_codec' => (string) $this->option('codec'),
                        'audio_codec' => (string) $this->option('audio-codec'),
                        'resolution' => (string) $this->option('resolution'),
                        'hw_accel' => (bool) $this->option('hw'),
                    ],
                ],
            );

            $this->info("Saved preset '{$preset->slug}' ({$preset->name})");

            return self::SUCCESS;
        }

        $presets = Preset::query()->orderBy('category')->orderBy('name')->get();

        if ((bool) $this->option('json')) {
            $this->line($presets->toJson(JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->table(
            ['Slug', 'Category', 'Name', 'Codec', 'Resolution', 'HW Accel'],
            $presets->map(static fn (Preset $p): array => [
                $p->slug,
                $p->category,
                $p->name,
                (string) ($p->config['video_codec'] ?? 'h264'),
                (string) ($p->config['resolution'] ?? 'source'),
                ! empty($p->config['hw_accel']) ? 'VideoToolbox' : 'Software',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
