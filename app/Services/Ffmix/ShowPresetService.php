<?php

namespace App\Services\Ffmix;

use App\Models\ShowPreset;
use Illuminate\Support\Str;
use RuntimeException;

class ShowPresetService
{
    public function active(): ShowPreset
    {
        return ShowPreset::activeOrDefault();
    }

    public function select(string $slugOrId): ShowPreset
    {
        ShowPreset::syncBuiltins();

        $preset = is_numeric($slugOrId)
            ? ShowPreset::query()->find((int) $slugOrId)
            : ShowPreset::query()->where('slug', $slugOrId)->first();

        if ($preset === null) {
            throw new RuntimeException("Show preset '{$slugOrId}' not found.");
        }

        $preset->activate();

        return $preset->fresh() ?? $preset;
    }

    /**
     * Save or update a Show Preset with full show, watchdog, normalization, DSK, transition, and pipeline config.
     *
     * @param  array<string, mixed>  $config
     */
    public function save(string $name, array $config, ?string $description = null, bool $activate = true): ShowPreset
    {
        $slug = Str::slug($name);
        $config['show_name'] = $config['show_name'] ?? $name;

        $preset = ShowPreset::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'description' => $description ?? ($config['description'] ?? 'Saved Show Preset'),
                'config' => $config,
            ],
        );

        if ($activate) {
            $preset->activate();
        }

        return $preset->fresh() ?? $preset;
    }

    public function exportToFile(ShowPreset $preset, string $filePath): string
    {
        $dir = dirname($filePath);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $payload = [
            'schema' => 'ffmix.show_preset.v1',
            'slug' => $preset->slug,
            'name' => $preset->name,
            'description' => $preset->description,
            'config' => $preset->config,
            'exported_at' => now()->toIso8601String(),
        ];

        file_put_contents($filePath, (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $filePath;
    }

    public function importFromFile(string $filePath, bool $activate = true): ShowPreset
    {
        if (! is_file($filePath)) {
            throw new RuntimeException("Show preset file not found: {$filePath}");
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode((string) file_get_contents($filePath), true);
        if (! is_array($decoded) || ! is_string($decoded['name'] ?? null) || ! is_array($decoded['config'] ?? null)) {
            throw new RuntimeException('Invalid Show Preset JSON format (expected name and config keys).');
        }

        $name = (string) $decoded['name'];
        $slug = is_string($decoded['slug'] ?? null) && $decoded['slug'] !== ''
            ? Str::slug($decoded['slug'])
            : Str::slug($name);

        $preset = ShowPreset::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'description' => is_string($decoded['description'] ?? null) ? $decoded['description'] : 'Imported Show Preset',
                'config' => $decoded['config'],
            ],
        );

        if ($activate) {
            $preset->activate();
        }

        return $preset->fresh() ?? $preset;
    }
}
