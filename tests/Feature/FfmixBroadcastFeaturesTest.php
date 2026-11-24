<?php

use App\Enums\DashboardTab;
use App\Models\NormalizedAsset;
use App\Models\ShowPreset;
use App\Services\Ffmix\DownstreamKeyService;
use App\Services\Ffmix\FolderWatchdogService;
use App\Services\Ffmix\MediaProbeService;
use App\Services\Ffmix\ShowPresetService;
use App\Services\Ffmix\TransitionService;
use App\Tui\Ansi;
use App\Tui\DashboardController;
use App\Tui\DashboardRenderer;
use App\Tui\DashboardState;
use Illuminate\Support\Facades\Process;

it('renders the multi-line ffMix ASCII banner and Show Name in a concise container', function (): void {
    /** @var DashboardRenderer $renderer */
    $renderer = app(DashboardRenderer::class);
    $state = new DashboardState;
    $state->activeShowName = 'Evening Keynote Live';
    $state->tab = DashboardTab::Show;

    $frame = $renderer->render($state, 124, 36);
    $plain = Ansi::strip($frame);
    $lines = explode("\n", $plain);

    expect($lines[1])->toContain('▄▀▀ ▄▀▀ █▄ ▄█ ▀ ▀▄▀')
        ->and($lines[2])->toContain('█▀  █▀  █ ▀ █ █  █')
        ->and($lines[3])->toContain('▀   ▀   ▀   ▀ ▀ ▄▀▄')
        ->and($plain)->toContain('SHOW: Evening Keynote Live')
        ->and($plain)->toContain('Show Control, Watchdog & HTML DSK');
});

it('includes all basic xfade transitions and the Logo Sting transition', function (): void {
    /** @var TransitionService $transitions */
    $transitions = app(TransitionService::class);
    $catalog = $transitions->all();

    expect($catalog)->toHaveKeys([
        'cut', 'fade', 'fadeblack', 'fadewhite',
        'wipeleft', 'wiperight', 'wipeup', 'wipedown',
        'slideleft', 'slideright', 'circleopen', 'circleclose',
        'radial', 'pixelize', 'dissolve', 'zoomin', 'logo_sting',
    ]);

    $proceduralSting = implode(' ', $transitions->buildCommand(
        ['clip1.mp4', 'clip2.mp4'],
        'out.mp4',
        'logo_sting',
        1.0,
        durationsSeconds: [5.0, 5.0],
    ));

    expect($proceduralSting)
        ->toContain('xfade=transition=fade')
        ->toContain('acrossfade=d=')
        ->toContain("drawtext=text='ffMix'");

    $customSting = implode(' ', $transitions->buildCommand(
        ['clip1.mp4', 'clip2.mp4'],
        'out.mp4',
        'logo_sting',
        1.0,
        stingAssetPath: 'brand_logo.png',
        durationsSeconds: [5.0, 5.0],
    ));

    expect($customSting)
        ->toContain('brand_logo.png')
        ->toContain('[sting]')
        ->toContain('overlay=(W-w)/2:(H-h)/2');
});

it('saves, selects, exports, and imports entire Show Presets', function (): void {
    /** @var ShowPresetService $service */
    $service = app(ShowPresetService::class);

    $active = $service->active();
    expect($active->slug)->toBe('main-broadcast');

    $saved = $service->save('Morning Studio Show', [
        'show_name' => 'Morning Studio Show',
        'normalize' => ['width' => 1280, 'height' => 720, 'fps' => 30, 'slate_duration' => 4.0],
        'watchdog' => ['input_dir' => '/tmp/in', 'normalized_dir' => '/tmp/out'],
        'dsk' => ['folder' => 'storage/app/ffmix/dsk', 'active_template' => 'sample-lower-third.html', 'title' => 'Morning Host'],
        'transition' => ['type' => 'logo_sting', 'duration' => 1.2],
        'pipeline' => ['container' => 'mp4', 'video_codec' => 'h264'],
    ]);

    expect($saved->is_active)->toBeTrue()
        ->and(ShowPreset::query()->where('slug', 'main-broadcast')->first()?->is_active)->toBeFalse();

    $exportFile = sys_get_temp_dir().'/ffmix_show_export_'.uniqid().'.json';
    $service->exportToFile($saved, $exportFile);
    expect(is_file($exportFile))->toBeTrue();

    // Switch back, delete saved, then re-import from JSON
    $service->select('main-broadcast');
    $saved->delete();

    $imported = $service->importFromFile($exportFile, true);
    expect($imported->slug)->toBe('morning-studio-show')
        ->and($imported->is_active)->toBeTrue()
        ->and($imported->config['dsk']['title'])->toBe('Morning Host');

    @unlink($exportFile);
});

it('watchdog normalizes incoming videos and images/slates into uniform MP4 files', function (): void {
    $ffmpeg = (string) config('ffmix.binaries.ffmpeg', 'ffmpeg');
    if (! Process::run([$ffmpeg, '-version'])->successful()) {
        $this->markTestSkipped('ffmpeg binary not available.');
    }

    $tmp = sys_get_temp_dir().'/ffmix_watchdog_'.uniqid();
    $inDir = $tmp.'/in';
    $outDir = $tmp.'/out';
    mkdir($inDir, 0775, true);
    mkdir($outDir, 0775, true);

    // Create a silent 1s non-standard video (320x240 @ 15fps) and a PNG slate image (400x300)
    $vidSrc = $inDir.'/camera_clip.mov';
    $imgSrc = $inDir.'/intro_slate.png';

    Process::run([
        $ffmpeg, '-hide_banner', '-y',
        '-f', 'lavfi', '-i', 'testsrc=duration=1:size=320x240:rate=15',
        '-c:v', 'libx264', '-pix_fmt', 'yuv420p', $vidSrc,
    ])->throw();

    Process::run([
        $ffmpeg, '-hide_banner', '-y',
        '-f', 'lavfi', '-i', 'color=c=navy:size=400x300',
        '-frames:v', '1', $imgSrc,
    ])->throw();

    $show = ShowPreset::factory()->create([
        'slug' => 'watchdog-test-show',
        'name' => 'Watchdog Test Show',
        'is_active' => true,
        'config' => [
            'show_name' => 'Watchdog Test Show',
            'normalize' => [
                'width' => 640,
                'height' => 360,
                'fps' => 24,
                'video_codec' => 'h264',
                'audio_codec' => 'aac',
                'audio_bitrate' => '128k',
                'hw_accel' => false,
                'slate_duration' => 1.5,
            ],
            'watchdog' => [
                'enabled' => true,
                'input_dir' => $inDir,
                'normalized_dir' => $outDir,
                'poll_seconds' => 2,
            ],
        ],
    ]);

    /** @var FolderWatchdogService $watchdog */
    $watchdog = app(FolderWatchdogService::class);
    /** @var MediaProbeService $probe */
    $probe = app(MediaProbeService::class);

    $normalized = $watchdog->scanAndNormalize($show);
    expect($normalized)->toHaveCount(2);

    // Second scan skips already-normalized unchanged files
    expect($watchdog->scanAndNormalize($show))->toHaveCount(0);

    foreach ($normalized as $asset) {
        expect($asset->status)->toBe('ready')
            ->and(is_file($asset->normalized_path))->toBeTrue();

        $info = $probe->probe($asset->normalized_path);
        expect($info['video'])->not->toBeNull()
            ->and($info['video']['width'])->toBe(640)
            ->and($info['video']['height'])->toBe(360)
            ->and($info['audio'])->not->toBeNull()
            ->and($info['audio']['sample_rate'])->toBe(48000);
    }

    expect(NormalizedAsset::query()->where('asset_type', 'slate')->count())->toBe(1)
        ->and(NormalizedAsset::query()->where('asset_type', 'video')->count())->toBe(1);

    // Also verify Logo Sting transition succeeds directly on the two uniform MP4s
    /** @var TransitionService $transitions */
    $transitions = app(TransitionService::class);
    $transOut = $tmp.'/stitched_sting.mp4';
    $paths = array_map(static fn (NormalizedAsset $a): string => $a->normalized_path, $normalized);
    $transitions->apply($paths, $transOut, 'logo_sting', 0.4);
    expect(is_file($transOut))->toBeTrue();

    array_map('unlink', glob($inDir.'/*') ?: []);
    array_map('unlink', glob($outDir.'/*') ?: []);
    @unlink($transOut);
    @rmdir($inDir);
    @rmdir($outDir);
    @rmdir($tmp);
});

it('discovers HTML Downstream Key templates including sample lower-third and builds overlay commands', function (): void {
    $dskDir = sys_get_temp_dir().'/ffmix_dsk_dir_'.uniqid();
    mkdir($dskDir, 0775, true);

    /** @var DownstreamKeyService $dsk */
    $dsk = app(DownstreamKeyService::class);
    $templates = $dsk->listTemplates($dskDir);

    expect($templates)->toHaveCount(1)
        ->and($templates[0]['filename'])->toBe('sample-lower-third.html')
        ->and($templates[0]['title'])->toBe('ffMix Broadcast Lower Third');

    $html = (string) file_get_contents($templates[0]['path']);
    expect($html)
        ->toContain('ffmixSeek')
        ->toContain('background: transparent');

    $cmd = implode(' ', $dsk->buildApplyOverlayCommand('show.mp4', 'lower_third.mov', 'final.mp4', 2.0, 6.0));
    expect($cmd)
        ->toContain('-itsoffset 2')
        ->toContain("overlay=0:0:eof_action=pass:enable='between(t,2.00,8.00)'");

    @unlink($dskDir.'/sample-lower-third.html');
    @rmdir($dskDir);
});

it('opens Show Preset picker and Transition picker modals in TUI controller and exposes CLI commands', function (): void {
    /** @var DashboardController $controller */
    $controller = app(DashboardController::class);
    $state = new DashboardState;

    expect($controller->handleKey($state, 'S'))->toBeTrue()
        ->and($state->modal)->toBe('show_picker');
    expect($controller->handleKey($state, 'ESC'))->toBeTrue();

    // Transition picker is a global key (outside Show tab where ⇧T toggles stream)
    $state->tab = DashboardTab::Queue;
    expect($controller->handleKey($state, 'T'))->toBeTrue()
        ->and($state->modal)->toBe('transition_picker');
    expect($controller->handleKey($state, 'ESC'))->toBeTrue();

    $this->artisan('ffmix:show', ['action' => 'list'])->assertSuccessful();
    $this->artisan('ffmix:transition', ['--list' => true])->assertSuccessful();
    $this->artisan('ffmix:dsk', ['action' => 'list'])->assertSuccessful();
    $this->artisan('ffmix:watchdog', ['action' => 'status'])->assertSuccessful();
});
