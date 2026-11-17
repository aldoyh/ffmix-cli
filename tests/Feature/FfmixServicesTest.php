<?php

use App\Enums\MergeMode;
use App\Models\DropZone;
use App\Models\MediaJob;
use App\Models\Preset;
use App\Services\Ffmix\BitrateAnalyzerService;
use App\Services\Ffmix\CatalogService;
use App\Services\Ffmix\ContactSheetService;
use App\Services\Ffmix\DropZoneWatcherService;
use App\Services\Ffmix\ImageSequenceService;
use App\Services\Ffmix\MediaMergeService;
use App\Services\Ffmix\QualityMetricsService;
use Illuminate\Support\Facades\Process;

it('exposes >= 80 Pro LUT presets, >= 60 video filters, and >= 450 advanced FFmpeg options', function (): void {
    /** @var CatalogService $catalog */
    $catalog = app(CatalogService::class);

    expect(count($catalog->lutPresets()))->toBeGreaterThanOrEqual(80);
    expect(count($catalog->videoFilters()))->toBeGreaterThanOrEqual(60);
    expect(count($catalog->audioFilters()))->toBeGreaterThanOrEqual(10);
    expect(count($catalog->advancedOptions()))->toBeGreaterThanOrEqual(450);

    $vtMatches = $catalog->searchAdvancedOptions('videotoolbox', 10);
    expect($vtMatches)->not->toBeEmpty();
});

it('aggregates packet bitrates into per-second buckets and sparklines', function (): void {
    /** @var BitrateAnalyzerService $analyzer */
    $analyzer = app(BitrateAnalyzerService::class);

    $packets = [
        ['pts_time' => '0.10', 'size' => 100000, 'flags' => 'K_'],
        ['pts_time' => '0.50', 'size' => 150000, 'flags' => '__'],
        ['pts_time' => '1.20', 'size' => 400000, 'flags' => 'K_'],
        ['pts_time' => '2.30', 'size' => 250000, 'flags' => '__'],
    ];

    $stats = $analyzer->aggregatePackets($packets);
    $sparkline = $analyzer->renderSparkline($stats['buckets'], 10);

    expect($stats['keyframes'])->toBe(2)
        ->and($stats['buckets'])->toBe([2000000, 3200000, 2000000])
        ->and($stats['min_bps'])->toBe(2000000)
        ->and($stats['max_bps'])->toBe(3200000)
        ->and($stats['avg_bps'])->toBe(2400000)
        ->and(mb_strlen($sparkline))->toBe(3);
});

it('parses VMAF, PSNR, SSIM, and CAMBI metrics from JSON and stderr', function (): void {
    /** @var QualityMetricsService $service */
    $service = app(QualityMetricsService::class);

    $json = (string) json_encode([
        'pooled_metrics' => [
            'vmaf' => ['mean' => 94.321],
            'psnr_y' => ['mean' => 42.156],
            'float_ssim' => ['mean' => 0.98412],
            'cambi' => ['mean' => 0.318],
        ],
        'frames' => [
            ['metrics' => ['vmaf' => 94.1]],
            ['metrics' => ['vmaf' => 94.5]],
        ],
    ]);

    $parsed = $service->parseOutput('', $json);

    expect($parsed['vmaf'])->toBe(94.32)
        ->and($parsed['psnr_y'])->toBe(42.16)
        ->and($parsed['ssim'])->toBe(0.9841)
        ->and($parsed['cambi'])->toBe(0.318)
        ->and($parsed['frame_vmaf'])->toBe([94.1, 94.5]);
});

it('builds contact sheet, merge, and image sequence commands properly', function (): void {
    /** @var ContactSheetService $contactSheet */
    $contactSheet = app(ContactSheetService::class);
    $csCmd = implode(' ', $contactSheet->buildCommand('in.mp4', 'sheet.png', 4, 3, 320, true, 24.0));
    expect($csCmd)->toContain('tile=4x3')->toContain('scale=320:-2')->toContain('drawtext=');

    /** @var MediaMergeService $merge */
    $merge = app(MediaMergeService::class);
    $passthru = $merge->buildCommand(['a.mp4', 'b.mp4'], 'out.mp4', MergeMode::Passthru, createManifest: false);
    expect(implode(' ', $passthru['args']))->toContain('-f concat')->toContain('-c copy');

    $complex = $merge->buildCommand(['a.mp4', 'b.mp4'], 'out.mp4', MergeMode::Complex);
    expect(implode(' ', $complex['args']))->toContain('-filter_complex')->toContain('concat=n=2:v=1:a=1');

    /** @var ImageSequenceService $images */
    $images = app(ImageSequenceService::class);
    foreach (['png', 'bmp', 'jpg', 'tiff', 'dpx', 'sgi', 'tga'] as $ext) {
        $cmd = implode(' ', $images->buildExportImagesCommand('in.mp4', "out/frame_%04d.{$ext}", $ext, 2.0));
        expect($cmd)->toContain("out/frame_%04d.{$ext}");
    }

    $toMovie = implode(' ', $images->buildImagesToMovieCommand('out/frame_%04d.png', 'assembled.mp4', 24.0));
    expect($toMovie)->toContain('-framerate 24')->toContain('assembled.mp4');
});

it('scans Drop Zones and enqueues new files without duplicating', function (): void {
    Preset::syncBuiltins();

    $dir = sys_get_temp_dir().'/ffmix_dz_test_'.uniqid();
    $out = $dir.'/out';
    mkdir($dir, 0775, true);
    file_put_contents($dir.'/clip1.mov', 'dummy');
    file_put_contents($dir.'/ignore.txt', 'dummy');

    $zone = DropZone::factory()->create([
        'name' => 'Test Watch',
        'watch_path' => $dir,
        'output_path' => $out,
        'preset_slug' => 'desktop',
        'extensions' => ['mov', 'mp4'],
        'is_active' => true,
    ]);

    /** @var DropZoneWatcherService $watcher */
    $watcher = app(DropZoneWatcherService::class);

    $first = $watcher->scan($zone);
    $second = $watcher->scan($zone);

    expect($first)->toHaveCount(1)
        ->and($second)->toHaveCount(0)
        ->and(MediaJob::query()->count())->toBe(1)
        ->and($zone->fresh()->processed_count)->toBe(1);

    @unlink($dir.'/clip1.mov');
    @unlink($dir.'/ignore.txt');
    @rmdir($out);
    @rmdir($dir);
});

it('executes real FFmpeg probe, bitrate analysis, contact sheet, convert, and quality metrics on a generated test clip', function (): void {
    $ffmpeg = (string) config('ffmix.binaries.ffmpeg', 'ffmpeg');
    $check = Process::run([$ffmpeg, '-version']);
    if (! $check->successful()) {
        $this->markTestSkipped('ffmpeg binary not available.');
    }

    $tmp = sys_get_temp_dir().'/ffmix_e2e_'.uniqid();
    mkdir($tmp, 0775, true);
    $src = $tmp.'/source.mp4';
    $enc = $tmp.'/encoded.mp4';
    $sheet = $tmp.'/sheet.png';

    // Generate real 2-second test video with audio tone
    $gen = Process::run([
        $ffmpeg, '-hide_banner', '-y',
        '-f', 'lavfi', '-i', 'testsrc=duration=2:size=320x240:rate=24',
        '-f', 'lavfi', '-i', 'sine=frequency=440:duration=2',
        '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-c:a', 'aac',
        $src,
    ]);
    expect($gen->successful())->toBeTrue();

    $this->artisan('ffmix:probe', ['input' => $src, '--json' => true])
        ->assertSuccessful();

    $this->artisan('ffmix:bitrate', ['input' => $src, '--json' => true])
        ->assertSuccessful();

    $this->artisan('ffmix:contact-sheet', [
        'input' => $src,
        'output' => $sheet,
        '--cols' => 2,
        '--rows' => 2,
        '--width' => 160,
        '--no-timestamps' => true,
    ])->assertSuccessful();
    expect(is_file($sheet))->toBeTrue();

    $this->artisan('ffmix:convert', [
        'input' => $src,
        'output' => $enc,
        '--preset' => 'desktop',
        '--crf' => 24,
    ])->assertSuccessful();
    expect(is_file($enc))->toBeTrue();

    $this->artisan('ffmix:quality', [
        'reference' => $src,
        'distorted' => $enc,
        '--json' => true,
    ])->assertSuccessful();

    @unlink($src);
    @unlink($enc);
    @unlink($sheet);
    @rmdir($tmp);
});
