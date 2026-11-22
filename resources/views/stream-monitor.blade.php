<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta http-equiv="refresh" content="5" />
    <title>ffMix · Multi-Window Monitor</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { margin: 0; background: #0a0a0a; color: #e5e5e5; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .screen { min-height: 100vh; padding: 16px; box-sizing: border-box; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding: 12px 16px; background: #141414; border: 1px solid #262626; border-radius: 12px; }
        .title { font-size: 20px; font-weight: 600; }
        .title span { color: #06b6d4; }
        .meta { font-size: 12px; color: #a3a3a3; text-align: right; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .window { background: #141414; border: 1px solid #262626; border-radius: 12px; overflow: hidden; min-height: 280px; }
        .window-bar { display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: #1b1b1b; border-bottom: 1px solid #262626; font-size: 13px; font-weight: 600; }
        .dot { width: 10px; height: 10px; border-radius: 999px; background: #22c55e; display: inline-block; margin-right: 8px; box-shadow: 0 0 10px rgba(34,197,94,.45); }
        .window-body { padding: 14px; font-size: 13px; line-height: 1.5; }
        .kv { display: grid; grid-template-columns: 160px 1fr; gap: 8px 12px; }
        .k { color: #a3a3a3; }
        .v { color: #f5f5f5; word-break: break-word; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; background: #1f2937; color: #d1d5db; }
        .badge.running { background: rgba(34,197,94,.15); color: #4ade80; }
        .badge.failed { background: rgba(239,68,68,.15); color: #f87171; }
        .badge.completed { background: rgba(6,182,212,.15); color: #67e8f9; }
        .list { margin: 0; padding: 0; list-style: none; }
        .list li { padding: 8px 0; border-bottom: 1px solid #262626; }
        .list li:last-child { border-bottom: none; }
        .muted { color: #a3a3a3; }
        .active { color: #67e8f9; font-weight: 600; }
        @media (max-width: 1100px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="screen">
        <header class="header">
            <div>
                <div class="title">ffMix <span>Multi-Window Monitor</span></div>
                <div class="muted" style="font-size:12px; margin-top:4px;">Read-only monitoring screen · auto-refresh every 5s · no interaction required</div>
            </div>
            <div class="meta">
                <div><span class="dot"></span>Monitoring active</div>
                <div>{{ now()->toDateTimeString() }}</div>
            </div>
        </header>

        <div class="grid">
            <section class="window">
                <div class="window-bar"><span>Window 1 · Stream Input</span><span class="badge running">LIVE SOURCE</span></div>
                <div class="window-body">
                    <div class="kv">
                        <div class="k">Default HLS Stream</div><div class="v">{{ $defaultStreamUrl }}</div>
                        <div class="k">Stream Type</div><div class="v">HLS</div>
                        <div class="k">Mode</div><div class="v">Multi-window monitoring / no interaction</div>
                        <div class="k">Dashboard</div><div class="v">{{ rtrim(config('app.url') ?: 'http://127.0.0.1:8000', '/') }}/stream</div>
                        <div class="k">Monitor Command</div><div class="v"><code>./ffmix stream monitor</code></div>
                    </div>
                </div>
            </section>

            <section class="window">
                <div class="window-bar"><span>Window 2 · DSK Keys</span><span class="badge">4 SAMPLES</span></div>
                <div class="window-body">
                    <ul class="list">
                        @forelse ($dskTemplates as $index => $template)
                            <li>
                                <div class="{{ $index === 0 ? 'active' : '' }}">
                                    {{ $template['title'] ?? $template['filename'] }}
                                    @if ($index === 0)
                                        <span class="badge completed">ACTIVE</span>
                                    @endif
                                </div>
                                <div class="muted">{{ $template['filename'] ?? '' }} — {{ $template['description'] ?? 'DSK sample' }}</div>
                            </li>
                        @empty
                            <li class="muted">No DSK templates found.</li>
                        @endforelse
                    </ul>
                </div>
            </section>

            <section class="window">
                <div class="window-bar"><span>Window 3 · Recent Jobs</span><span class="badge">{{ $jobs->count() }} JOBS</span></div>
                <div class="window-body">
                    <ul class="list">
                        @forelse ($jobs as $job)
                            @php
                                $status = is_object($job->status) ? $job->status->value : (string) $job->status;
                            @endphp
                            <li>
                                <div>
                                    <strong>#{{ $job->id }}</strong>
                                    <span class="badge {{ $status }}">{{ strtoupper($status) }}</span>
                                </div>
                                <div class="muted">{{ \Illuminate\Support\Str::limit((string) ($job->source_url ?? 'n/a'), 72) }}</div>
                                <div class="muted">Output: {{ \Illuminate\Support\Str::limit((string) ($job->output_path ?? 'n/a'), 72) }}</div>
                            </li>
                        @empty
                            <li class="muted">No stream jobs recorded yet.</li>
                        @endforelse
                    </ul>
                </div>
            </section>

            <section class="window">
                <div class="window-bar"><span>Window 4 · Preliminary Inputs</span><span class="badge completed">PRESET</span></div>
                <div class="window-body">
                    <div class="kv">
                        <div class="k">HLS Stream URL</div><div class="v">{{ $defaultStreamUrl }}</div>
                        <div class="k">Quality Selection</div><div class="v">Auto (best master quality)</div>
                        <div class="k">DSK Samples</div><div class="v">4 loaded</div>
                        <div class="k">Active DSK Key</div><div class="v">{{ $dskTemplates[0]['title'] ?? 'Sample Lower Third' }}</div>
                        <div class="k">Interaction</div><div class="v">Disabled by design; monitor-only screen</div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</body>
</html>
