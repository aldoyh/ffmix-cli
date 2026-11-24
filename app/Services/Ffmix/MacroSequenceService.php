<?php

namespace App\Services\Ffmix;

use RuntimeException;

class MacroSequenceService
{
    public function list(): array
    {
        $dir = $this->directory();
        if (! is_dir($dir)) {
            return [];
        }

        $names = [];
        foreach (glob($dir.'/*.json') ?: [] as $file) {
            $names[] = pathinfo($file, PATHINFO_FILENAME);
        }
        sort($names);

        return $names;
    }

    public function save(string $name, array $steps): string
    {
        $slug = trim(preg_replace('/[^a-z0-9]+/i', '-', strtolower($name)), '-');
        if ($slug === '') {
            throw new RuntimeException('Macro name is required.');
        }

        $dir = $this->directory();
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $path = $dir.'/'.$slug.'.json';
        file_put_contents($path, json_encode([
            'name' => $name,
            'steps' => $steps,
            'saved_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    public function load(string $name): array
    {
        $slug = trim(preg_replace('/[^a-z0-9]+/i', '-', strtolower($name)), '-');
        $path = $this->directory().'/'.$slug.'.json';
        if (! is_file($path)) {
            throw new RuntimeException("Macro '{$name}' not found.");
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded) || ! is_array($decoded['steps'] ?? null)) {
            throw new RuntimeException("Macro '{$name}' is invalid.");
        }

        return $decoded['steps'];
    }

    private function directory(): string
    {
        return storage_path('app/ffmix/macros');
    }
}
