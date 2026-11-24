<?php

namespace App\Services\Ffmix;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class MediaSampleService
{
    /** @var list<string> */
    private const SUPPORTED_EXTENSIONS = [
        'aac',
        'aiff',
        'avi',
        'flac',
        'm4a',
        'm4v',
        'mkv',
        'mov',
        'mp3',
        'mp4',
        'mpeg',
        'mpg',
        'ts',
        'wav',
        'webm',
    ];

    /** @return list<array{label: string, source: string, kind: string, relative: string}> */
    public function all(): array
    {
        $samples = [];
        $seen = [];

        foreach ((array) config('ffmix.sample_media', []) as $sample) {
            if (! is_array($sample)) {
                continue;
            }

            $source = isset($sample['url'])
                ? (string) $sample['url']
                : $this->resolveMediaPath((string) ($sample['path'] ?? ''));
            if ($source === '' || (! str_starts_with($source, 'http') && ! is_file($source))) {
                continue;
            }

            $label = (string) ($sample['label'] ?? basename($source));
            $kind = (string) ($sample['kind'] ?? $this->inferKind($source));
            $relative = isset($sample['url']) ? 'Live HLS' : basename($source);
            $seen[$source] = true;
            $samples[] = [
                'label' => $label,
                'source' => $source,
                'kind' => $kind,
                'relative' => $relative,
            ];
        }

        $root = (string) config('ffmix.sample_media_root', rtrim((string) getenv('HOME'), '/').'/Movies');
        if (is_dir($root)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                    continue;
                }

                $extension = strtolower($file->getExtension());
                if (! in_array($extension, self::SUPPORTED_EXTENSIONS, true)) {
                    continue;
                }

                $source = $file->getRealPath() ?: $file->getPathname();
                if (isset($seen[$source])) {
                    continue;
                }

                $relative = ltrim(substr($source, strlen(rtrim($root, '/'))), '/');
                $seen[$source] = true;
                $samples[] = [
                    'label' => pathinfo($source, PATHINFO_FILENAME),
                    'source' => $source,
                    'kind' => $this->inferKind($source),
                    'relative' => $relative,
                ];
            }
        }

        usort($samples, static function (array $a, array $b): int {
            $rank = [
                'hls' => 0,
                'audio-hls' => 1,
                'video' => 2,
                'audio' => 3,
            ];
            $rankA = $rank[strtolower($a['kind'])] ?? 4;
            $rankB = $rank[strtolower($b['kind'])] ?? 4;
            $rankResult = $rankA <=> $rankB;

            return $rankResult !== 0 ? $rankResult : strcasecmp($a['label'], $b['label']);
        });

        return array_values($samples);
    }

    private function resolveMediaPath(string $path): string
    {
        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, '/')) {
            return $path;
        }

        $root = (string) config('ffmix.sample_media_root', rtrim((string) getenv('HOME'), '/').'/Movies');

        return rtrim($root, '/').'/'.$path;
    }

    private function inferKind(string $source): string
    {
        $extension = strtolower(pathinfo(parse_url($source, PHP_URL_PATH) ?: $source, PATHINFO_EXTENSION));

        return in_array($extension, ['mp3', 'm4a', 'wav', 'flac', 'aac', 'aiff'], true)
            ? 'audio'
            : 'video';
    }
}
