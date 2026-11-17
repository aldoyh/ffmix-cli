<?php

namespace App\Console\Commands;

use App\Services\Ffmix\CatalogService;
use Illuminate\Console\Command;

class FfmixCatalogCommand extends Command
{
    protected $signature = 'ffmix:catalog
        {type=summary : Catalog type (summary|luts|vfilters|afilters|options)}
        {--search= : Filter query string}
        {--limit=50 : Maximum rows to display}
        {--json : Output JSON}';

    protected $description = 'Browse +450 FFmpeg advanced options, +60 video filters, +80 Pro LUT presets, and audio DSP filters';

    public function handle(CatalogService $catalog): int
    {
        $type = (string) $this->argument('type');
        $search = (string) ($this->option('search') ?? '');
        $limit = max(1, (int) $this->option('limit'));

        $luts = $catalog->lutPresets();
        $vFilters = $catalog->videoFilters();
        $aFilters = $catalog->audioFilters();
        $options = $catalog->advancedOptions();

        if ($type === 'summary') {
            $summary = [
                'pro_lut_presets' => count($luts),
                'video_filters' => count($vFilters),
                'audio_filters' => count($aFilters),
                'advanced_options' => count($options),
            ];
            if ((bool) $this->option('json')) {
                $this->line((string) json_encode($summary, JSON_PRETTY_PRINT));

                return self::SUCCESS;
            }
            $this->table(['Catalog', 'Count'], [
                ['Pro LUT Presets', (string) count($luts)],
                ['Video Filters', (string) count($vFilters)],
                ['Audio Filters', (string) count($aFilters)],
                ['Advanced FFmpeg Options', (string) count($options)],
            ]);

            return self::SUCCESS;
        }

        if ($type === 'luts') {
            $filtered = array_values(array_filter($luts, static fn (array $item): bool => $search === '' || str_contains(mb_strtolower($item['slug'].' '.$item['name'].' '.$item['category']), mb_strtolower($search))));
            $slice = array_slice($filtered, 0, $limit);
            if ((bool) $this->option('json')) {
                $this->line((string) json_encode($slice, JSON_PRETTY_PRINT));

                return self::SUCCESS;
            }
            $this->table(['Slug', 'Category', 'Name'], array_map(static fn (array $i): array => [$i['slug'], $i['category'], $i['name']], $slice));

            return self::SUCCESS;
        }

        if ($type === 'vfilters') {
            $filtered = array_values(array_filter($vFilters, static fn (array $item): bool => $search === '' || str_contains(mb_strtolower($item['name'].' '.$item['category'].' '.$item['description']), mb_strtolower($search))));
            $slice = array_slice($filtered, 0, $limit);
            if ((bool) $this->option('json')) {
                $this->line((string) json_encode($slice, JSON_PRETTY_PRINT));

                return self::SUCCESS;
            }
            $this->table(['Filter', 'Category', 'Description'], array_map(static fn (array $i): array => [$i['name'], $i['category'], $i['description']], $slice));

            return self::SUCCESS;
        }

        if ($type === 'afilters') {
            $slice = array_slice($aFilters, 0, $limit);
            if ((bool) $this->option('json')) {
                $this->line((string) json_encode($slice, JSON_PRETTY_PRINT));

                return self::SUCCESS;
            }
            $this->table(['Name', 'Label', 'Description'], array_map(static fn (array $i): array => [$i['name'], $i['label'], $i['description']], $slice));

            return self::SUCCESS;
        }

        $opts = $catalog->searchAdvancedOptions($search, $limit);
        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($opts, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }
        $this->table(['Flag', 'Category', 'Hint', 'Description'], array_map(static fn (array $i): array => [$i['flag'], $i['category'], $i['value_hint'], $i['description']], $opts));

        return self::SUCCESS;
    }
}
