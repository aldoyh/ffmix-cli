<?php

test('returns a successful response', function () {
    $response = $this->get('/');

    $response->assertOk();
});

test('renders the FaceTime multiview welcome page with PGM tally controls', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('FaceTime Multiview');
    $response->assertSee('ffMix');
    $response->assertSee('Take to PGM');
    $response->assertSee('PREVIEW');
    $response->assertSee('PGM');
    $response->assertSee('system-footer');
    $response->assertSee('All systems operational');
    $response->assertSee('data-multiview-source="1"', false);
});

test('returns system service status without blocking the welcome page', function () {
    $response = $this->get('/system/status');

    $response->assertOk();
    $response->assertJsonStructure([
        'web',
        'watchdog',
        'stream',
        'latest_error',
    ]);
});

test('lists curated and discovered media samples', function () {
    $response = $this->get('/system/media-samples');

    $response->assertOk();
    $response->assertJsonStructure([
        'count',
        'samples',
    ]);
    $response->assertJsonFragment(['label' => 'Bahrain - Manama']);
    $response->assertJsonFragment(['label' => 'Ours. Yours. Bahrain']);
    $response->assertJsonFragment(['label' => 'Bahrain TV Live']);
    $response->assertJsonFragment(['label' => 'Radio 98.4 Live']);
});

test('renders media sample controls on the stream dashboard', function () {
    $response = $this->get('/stream');

    $response->assertOk();
    $response->assertSee('Sample Media');
    $response->assertSee('Bahrain TV Live');
    $response->assertSee('Radio 98.4 Live');
});
