<?php

namespace App\Console\Commands;

use App\Services\Ffmix\ContactSheetService;
use Illuminate\Console\Command;

class FfmixContactSheetCommand extends Command
{
    protected $signature = 'ffmix:contact-sheet
        {input : Input video path}
        {output? : Output contact sheet image path}
        {--cols=4 : Grid columns}
        {--rows=4 : Grid rows}
        {--width=320 : Width of each thumbnail tile}
        {--no-timestamps : Omit timestamp overlays on tiles}';

    protected $description = 'Create a thumbnail contact sheet grid image from a video';

    public function handle(ContactSheetService $service): int
    {
        $input = (string) $this->argument('input');
        $output = $this->argument('output')
            ? (string) $this->argument('output')
            : preg_replace('/\.[^.]+$/', '', $input).'_sheet.png';

        try {
            $result = $service->generate(
                inputPath: $input,
                outputPath: $output,
                cols: max(1, (int) $this->option('cols')),
                rows: max(1, (int) $this->option('rows')),
                thumbWidth: max(64, (int) $this->option('width')),
                timestamps: ! (bool) $this->option('no-timestamps'),
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Contact sheet ({$result['cols']}x{$result['rows']}) saved -> {$result['output']}");

        return self::SUCCESS;
    }
}
