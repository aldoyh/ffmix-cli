<div x-data x-init="$wire.on('error', e => { console.error(e.title, e.message) })" wire:ignore>
    {{-- Stream Configuration --}}
    <div class="stream-config rounded-xl bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-neutral-900 dark:text-white mb-4">Stream Configuration</h2>
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-neutral-600 dark:text-neutral-300 mb-1">Stream URL</label>
                <input type="text" wire:model="streamUrl" class="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary dark:focus:ring-primary" placeholder="https://example.com/live/stream.m3u8" />
                @if ($streamUrl !== '')
                    <button wire:click="setStreamUrl('')" class="mt-2 inline-flex items-center px-3 py-1 text-xs border border-red-300 rounded-md text-red-600 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-500">
                        Clear
                    </button>
                @endif
            </div>
            <div>
                <label class="block text-sm font-medium text-neutral-600 dark:text-neutral-300 mb-1">Stream Type</label>
                <select wire:model="streamType" class="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary">
                    <option value="hls">HLS</option>
                    <option value="rtmp">RTMP</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-neutral-600 dark:text-neutral-300 mb-1">Quality Level</label>
                <select wire:model="selectedResolution" class="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary">
                    <option value="0">Auto (best master quality)</option>
                    @foreach ($resolutionOptions as $opt)
                        <option value="{{ $opt['index'] }}" {{ $opt['index'] == $selectedResolution ? 'selected' : '' }}>
                            {{ $opt['label'] }}
                        </option>
                    @endforeach
                </select>
                @if (count($availableQualities) > 0)
                    <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-1">
                        Available qualities: @foreach ($availableQualities as $q) {{ $q['resolution'] ?? 'Source' }}{@unless ($loop->last), } @endforeach
                    </p>
                @endif
            </div>
        </div>
    </div>

    {{-- Recording Controls --}}
    <div class="mt-6 rounded-xl bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-neutral-900 dark:text-white mb-4">Recording Controls</h2>
        @if ($recordingActive)
            <div class="flex items-center justify-between">
                <span class="text-red-600 dark:text-red-400 font-medium">Recording in progress...</span>
                <span class="text-sm text-neutral-500 dark:text-neutral-400">Duration: {{ number_format($duration, 1) }}s</span>
            </div>
            <button wire:click="stopRecording" wire:key="stop-btn" class="mt-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                Stop Recording
            </button>
        @else
            <button wire:click="startRecording" wire:key="start-btn" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-primary hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                Start Recording (30s)
            </button>
        @endif
    </div>

    {{-- DSK Overlay --}}
    <div class="mt-6 rounded-xl bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-neutral-900 dark:text-white mb-4">DSK Overlay</h2>
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-neutral-600 dark:text-neutral-300 mb-1">Template</label>
                <select wire:model="dskTemplate" class="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary">
                    <option value="">None</option>
                    <option value="sample-lower-third">Sample Lower Third</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-neutral-600 dark:text-neutral-300 mb-1">Title</label>
                    <input type="text" wire:model="dskTitle" class="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Test Feed" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-neutral-600 dark:text-neutral-300 mb-1">Subtitle</label>
                    <input type="text" wire:model="dskSubtitle" class="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Live Feed" />
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-neutral-600 dark:text-neutral-300 mb-1">Accent Color</label>
                <input type="color" wire:model="dskAccent" class="w-16 h-10 rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700" value="#06b6d4" />
            </div>
        </div>
    </div>

    {{-- Recorded Output --}}
    @if ($recordedOutput)
        <div class="mt-6 rounded-xl bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-neutral-900 dark:text-white mb-4">Recorded Output</h2>
            <div class="space-y-2">
                <p class="text-sm text-neutral-500 dark:text-neutral-400">File: <strong>{{ basename($recordedOutput) }}</strong></p>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Duration: {{ number_format($recordedDuration, 1) }}s</p>
            </div>
        </div>
    @endif
</div>