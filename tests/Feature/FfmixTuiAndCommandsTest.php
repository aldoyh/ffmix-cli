<?php

use App\Enums\DashboardTab;
use App\Enums\ThemeMode;
use App\Models\Preset;
use App\Tui\DashboardController;
use App\Tui\DashboardRenderer;
use App\Tui\DashboardState;

it('renders all 8 TUI dashboard tabs in both Dark and Light themes', function (): void {
    Preset::syncBuiltins();

    /** @var DashboardRenderer $renderer */
    $renderer = app(DashboardRenderer::class);

    foreach ([ThemeMode::Dark, ThemeMode::Light] as $theme) {
        foreach (DashboardTab::cases() as $tab) {
            $state = new DashboardState($theme);
            $state->tab = $tab;

            $frame = $renderer->render($state, 124, 34);
            expect($frame)
                ->toContain('ffMix Broadcast & Media Studio')
                ->toContain('▄▀▀ ▄▀▀ █▄ ▄█ ▀ ▀▄▀')
                ->toContain($tab->label())
                ->toContain('╭─')
                ->toContain('╰─');
        }
    }
});

it('handles TUI navigation, theme switching, hardware accel toggle, and modals', function (): void {
    Preset::syncBuiltins();

    /** @var DashboardController $controller */
    $controller = app(DashboardController::class);
    $state = new DashboardState(ThemeMode::Dark);

    expect($controller->handleKey($state, '2'))->toBeTrue()
        ->and($state->tab)->toBe(DashboardTab::Studio);

    expect($controller->handleKey($state, 'TAB'))->toBeTrue()
        ->and($state->tab)->toBe(DashboardTab::VisualFx);

    expect($controller->handleKey($state, 't'))->toBeTrue()
        ->and($state->theme)->toBe(ThemeMode::Light);

    expect($controller->handleKey($state, 'h'))->toBeTrue()
        ->and($state->pipeline->hwAccel)->toBeTrue();

    expect($controller->handleKey($state, '?'))->toBeTrue()
        ->and($state->modal)->toBe('help');

    expect($controller->handleKey($state, 'ESC'))->toBeTrue()
        ->and($state->modal)->toBeNull();

    expect($controller->handleKey($state, '/'))->toBeTrue()
        ->and($state->modal)->toBe('catalog_search');

    expect($controller->handleKey($state, 'ESC'))->toBeTrue()
        ->and($controller->handleKey($state, 'q'))->toBeFalse();
});

it('runs TUI snapshot, presets, catalog, dropzone, and dry-run CLI commands', function (): void {
    $this->artisan('ffmix', ['--snapshot' => true, '--demo' => true, '--tab' => 'queue'])
        ->assertSuccessful()
        ->expectsOutputToContain('ffMix Broadcast & Media Studio');

    $this->artisan('ffmix:presets', ['action' => 'list'])
        ->assertSuccessful();

    $this->artisan('ffmix:presets', [
        'action' => 'create',
        '--name' => 'Custom Master 4K',
        '--codec' => 'h265',
        '--resolution' => '4k',
        '--hw' => true,
    ])->assertSuccessful();

    expect(Preset::query()->where('slug', 'custom-master-4k')->exists())->toBeTrue();

    $this->artisan('ffmix:catalog', ['type' => 'summary', '--json' => true])
        ->assertSuccessful();

    $this->artisan('ffmix:convert', [
        'input' => 'demo.mov',
        'output' => 'demo.mp4',
        '--preset' => 'youtube',
        '--lut' => 'teal-and-orange',
        '--dry-run' => true,
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('loudnorm=I=-14');

    $this->artisan('ffmix:merge', [
        'inputs' => ['a.mp4', 'b.mp4'],
        '--mode' => 'complex',
        '--dry-run' => true,
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('concat=n=2:v=1:a=1');

    $this->artisan('ffmix:images', [
        'mode' => 'to-images',
        'input' => 'movie.mp4',
        'output' => 'frames/f_%04d.dpx',
        '--format' => 'dpx',
        '--dry-run' => true,
    ])->assertSuccessful();
});
