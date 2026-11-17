<?php

namespace App\Console\Commands;

use App\Services\Ffmix\BitrateAnalyzerService;
use App\Services\Ffmix\MediaProbeService;
use Illuminate\Console\Command;

class FfmixProbeCommand extends Command
{
    protected $signature = 'ffmix:probe
        {input : Path to media file}
        {--json : Output raw JSON probe payload}';

    protected $description = 'Inspect media file streams, codecs, colorspace, and bitrate using ffprobe';

    public function handle(MediaProbeService $probe, BitrateAnalyzerService $bitrate): int
    {
        $input = (string) $this->argument('input');

        try {
            $info = $probe->probe($input);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Probe: '.$info['path']);
        $rows = [
            ['Format', $info['format_name']],
            ['Duration', sprintf('%.2f s', $info['duration'])],
            ['Size', number_format($info['size_bytes'] / 1024 / 1024, 2).' MB'],
            ['Overall Bitrate', $bitrate->formatBps($info['bitrate_bps'])],
            ['Subtitles', (string) $info['subtitles_count'].' stream(s)'],
        ];

        if ($info['video'] !== null) {
            $v = $info['video'];
            $rows[] = ['Video Stream', "{$v['codec']} {$v['width']}x{$v['height']} @ {$v['fps']}fps ({$v['pix_fmt']}, {$v['color_space']})"];
        }

        if ($info['audio'] !== null) {
            $a = $info['audio'];
            $rows[] = ['Audio Stream', "{$a['codec']} {$a['sample_rate']}Hz {$a['channels']}ch ({$a['channel_layout']})"];
        }

        $this->table(['Property', 'Value'], $rows);

        return self::SUCCESS;
    }
}
