<?php

namespace App\Console\Commands;

use App\Models\ShowPreset;
use App\Services\Ffmix\ShowPresetService;
use Illuminate\Console\Command;

class FfmixShowCommand extends Command
{
    protected $signature = 'ffmix:show
        {action=list : Action (list|select|save|export|import)}
        {target? : Show slug/name or JSON file path}
        {--name= : Show preset name when saving}
        {--description= : Description when saving}
        {--json : Output JSON list}';

    protected $description = 'Manage entire Show Presets (select, save, export JSON, and import JSON)';

    public function handle(ShowPresetService $shows): int
    {
        ShowPreset::syncBuiltins();
        $action = (string) $this->argument('action');
        $target = $this->argument('target') ? (string) $this->argument('target') : null;

        try {
            if ($action === 'select') {
                if ($target === null) {
                    $this->error('Specify a Show slug or ID to select.');

                    return self::FAILURE;
                }
                $selected = $shows->select($target);
                $this->info("Active Show set to '{$selected->name}' ({$selected->slug})");

                return self::SUCCESS;
            }

            if ($action === 'save') {
                $name = (string) ($this->option('name') ?: $target ?: '');
                if ($name === '') {
                    $this->error('Specify --name or target Show name to save.');

                    return self::FAILURE;
                }
                $active = $shows->active();
                $config = $active->config;
                $config['show_name'] = $name;
                $saved = $shows->save($name, $config, $this->option('description') ? (string) $this->option('description') : null);
                $this->info("Saved & activated Show Preset '{$saved->slug}' ({$saved->name})");

                return self::SUCCESS;
            }

            if ($action === 'export') {
                $active = $shows->active();
                $out = $target ?: storage_path('app/ffmix/shows/'.$active->slug.'.json');
                $path = $shows->exportToFile($active, $out);
                $this->info("Exported Show Preset '{$active->slug}' -> {$path}");

                return self::SUCCESS;
            }

            if ($action === 'import') {
                if ($target === null) {
                    $this->error('Specify a JSON file path to import.');

                    return self::FAILURE;
                }
                $imported = $shows->importFromFile($target, true);
                $this->info("Imported & activated Show Preset '{$imported->name}' ({$imported->slug})");

                return self::SUCCESS;
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $all = ShowPreset::query()->orderBy('id')->get();
        if ((bool) $this->option('json')) {
            $this->line($all->toJson(JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->table(
            ['Active', 'Slug', 'Name', 'Transition', 'DSK Template'],
            $all->map(static fn (ShowPreset $s): array => [
                $s->is_active ? '★' : '',
                $s->slug,
                $s->name,
                (string) ($s->config['transition']['type'] ?? 'fade'),
                (string) ($s->config['dsk']['active_template'] ?? 'sample-lower-third.html'),
            ])->all(),
        );

        return self::SUCCESS;
    }
}
