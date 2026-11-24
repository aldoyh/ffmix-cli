<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#090b0f">

        <title>ffMix · FaceTime Multiview</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="multiview-body">
        <main class="multiview-app" aria-label="ffMix FaceTime multiview control room">
            <header class="multiview-header">
                <div class="multiview-brand">
                    <div class="brand-logo" role="img" aria-label="ffMix">
                        <span>ff</span><strong>Mix</strong>
                    </div>
                    <div>
                        <p class="brand-context">FaceTime Multiview · {{ config('ffmix.builtin_show_presets.0.name', 'Main Broadcast Live') }}</p>
                    </div>
                </div>

                <div class="pgm-status" role="status" aria-live="polite">
                    <span class="status-dot" aria-hidden="true"></span>
                    <span id="on-air-label">ON AIR · PGM 1</span>
                </div>
            </header>

            <div class="multiview-layout">
                <section class="source-grid" aria-label="FaceTime source multiview">
                    <button type="button" class="source-tile source-selected" data-multiview-source="1" aria-pressed="true">
                        <video id="camera-feed" class="camera-feed" autoplay muted playsinline aria-label="Local camera feed"></video>
                        <div class="camera-fallback camera-a" aria-hidden="true">
                            <span>AM</span>
                        </div>
                        <div class="tile-top">
                            <span class="source-name">Source 1 · Camera</span>
                            <span class="tally-led preview-led" data-preview-led="1">PREVIEW</span>
                            <span class="tally-led pgm-led" data-pgm-led="1">PGM</span>
                        </div>
                        <div class="tile-bottom">
                            <span>Alex Morgan</span>
                            <span>1080p30</span>
                        </div>
                    </button>

                    <button type="button" class="source-tile" data-multiview-source="2" aria-pressed="false">
                        <div class="camera-fallback camera-b" aria-hidden="true">
                            <span>JL</span>
                        </div>
                        <div class="tile-top">
                            <span class="source-name">Source 2 · Remote</span>
                            <span class="tally-led preview-led" data-preview-led="2">PREVIEW</span>
                            <span class="tally-led pgm-led" data-pgm-led="2">PGM</span>
                        </div>
                        <div class="tile-bottom">
                            <span>Jordan Lee</span>
                            <span>720p60</span>
                        </div>
                    </button>

                    <button type="button" class="source-tile" data-multiview-source="3" aria-pressed="false">
                        <div class="camera-fallback camera-c" aria-hidden="true">
                            <span>SK</span>
                        </div>
                        <div class="tile-top">
                            <span class="source-name">Source 3 · Guest</span>
                            <span class="tally-led preview-led" data-preview-led="3">PREVIEW</span>
                            <span class="tally-led pgm-led" data-pgm-led="3">PGM</span>
                        </div>
                        <div class="tile-bottom">
                            <span>Samira Khan</span>
                            <span>1080p30</span>
                        </div>
                    </button>

                    <button type="button" class="source-tile" data-multiview-source="4" aria-pressed="false">
                        <div class="camera-fallback camera-d" aria-hidden="true">
                            <span>RI</span>
                        </div>
                        <div class="tile-top">
                            <span class="source-name">Source 4 · Remote</span>
                            <span class="tally-led preview-led" data-preview-led="4">PREVIEW</span>
                            <span class="tally-led pgm-led" data-pgm-led="4">PGM</span>
                        </div>
                        <div class="tile-bottom">
                            <span>Riley Imani</span>
                            <span>720p30</span>
                        </div>
                    </button>
                </section>

                <aside class="switcher-panel" aria-label="Program and preview controls">
                    <section class="switcher-section">
                        <p class="switcher-label">PREVIEW</p>
                        <strong id="preview-name">Source 1 · Alex Morgan</strong>
                        <span class="preview-rail" aria-hidden="true"></span>
                    </section>

                    <section class="switcher-section pgm-section">
                        <p class="switcher-label">PGM</p>
                        <strong id="pgm-name">Source 1 · Alex Morgan</strong>
                        <span class="pgm-rail" aria-hidden="true"></span>
                    </section>

                    <button type="button" id="take-pgm" class="take-button">
                        Take to PGM
                        <span aria-hidden="true">↗</span>
                    </button>

                    <button type="button" id="enable-camera" class="camera-button">
                        Enable local camera
                    </button>

                    <dl class="show-details">
                        <div>
                            <dt>DSK</dt>
                            <dd>{{ config('ffmix.dsk.active_template', 'sample-lower-third.html') }}</dd>
                        </div>
                        <div>
                            <dt>Transition</dt>
                            <dd>{{ config('ffmix.transitions.logo_sting.label', 'logo_sting') }}</dd>
                        </div>
                    </dl>
                </aside>
            </div>
        </main>

        @include('components.system-footer')
    </body>
</html>
