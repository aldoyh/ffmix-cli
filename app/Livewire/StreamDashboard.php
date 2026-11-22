<?php

namespace App\Livewire;

use App\Models\MediaJob;
use App\Services\Ffmix\DownstreamKeyService;
use App\Services\Ffmix\HlsStreamService;
use App\Services\Ffmix\StreamService;
use Livewire\Component;

class StreamDashboard extends Component
{
    public $streamUrl = 'https://5c7b683162943.streamlock.net/live/ngrp:bahraintvmain_all/playlist.m3u8';

    public $streamType = 'hls';

    public $status = 'pending';

    public $duration = 0;

    public $outputPath = '';

    public $recordingActive = false;

    public $recordedOutput = '';

    public $recordedDuration = 0;

    public $dskTemplate = 'sample-lower-third';

    public $dskTitle = 'ffMix Live Broadcast';

    public $dskSubtitle = 'Normalized 1080p30 Feed • Live Studio';

    public $dskAccent = '#06b6d4';

    public $dskOverlayImage = '';

    public $selectedResolution = 0;

    public $availableQualities = [];

    public $resolutionOptions = [];

    public $dskTemplates = [];

    protected HlsStreamService $hls;

    protected StreamService $streamService;

    protected DownstreamKeyService $dsk;

    public function __construct()
    {
        $this->hls = app(HlsStreamService::class);
        $this->streamService = app(StreamService::class);
        $this->dsk = app(DownstreamKeyService::class);
    }

    public function mount()
    {
        $this->refreshJobStatus();
        $this->loadDskTemplates();
        $this->loadStreamQualities();
    }

    public function loadDskTemplates(): void
    {
        try {
            $templates = $this->dsk->listTemplates();
            $this->dskTemplates = collect($templates)->take(4)->map(static fn (array $template): array => [
                'value' => pathinfo((string) ($template['filename'] ?? ''), PATHINFO_FILENAME),
                'filename' => (string) ($template['filename'] ?? ''),
                'title' => (string) ($template['title'] ?? pathinfo((string) ($template['filename'] ?? ''), PATHINFO_FILENAME)),
                'description' => (string) ($template['description'] ?? ''),
            ])->values()->all();

            if ($this->dskTemplate === '' && $this->dskTemplates !== []) {
                $this->dskTemplate = (string) $this->dskTemplates[0]['value'];
            }
        } catch (\Throwable) {
            $this->dskTemplates = [];
        }
    }

    public function refreshJobStatus()
    {
        $latestJob = MediaJob::latest('id')->first();
        if ($latestJob) {
            $this->status = $latestJob->status ?? 'pending';
            $this->outputPath = $latestJob->output_path ?? '';
            $this->duration = $latestJob->duration_seconds ?? 0;
            $this->recordedOutput = $latestJob->output_path ?? '';
            $this->recordedDuration = $latestJob->duration_seconds ?? 0;
        }
    }

    public function loadStreamQualities()
    {
        if ($this->streamUrl === '') {
            $this->availableQualities = [];
            $this->resolutionOptions = [];

            return;
        }

        try {
            $qualities = $this->hls->probeStreamQualities($this->streamUrl, 15);
            $this->availableQualities = $qualities['qualities'] ?? [];

            // Build resolution options for the UI
            $this->resolutionOptions = [];
            foreach ($this->availableQualities as $idx => $q) {
                $resLabel = $q['resolution'] ?? 'Source';
                $bandwidth = $q['bandwidth'] ?? 0;
                $this->resolutionOptions[] = [
                    'index' => $idx + 1,
                    'label' => 'Quality '.($idx + 1).': '.$resLabel.' ('.number_format($bandwidth).' bps)',
                    'bandwidth' => $bandwidth,
                    'resolution' => $resLabel,
                    'url' => $q['url'],
                    'isMaster' => $q['bandwidth'] === 0,
                ];
            }

            // Select the best quality by default (highest bandwidth)
            if (! empty($this->availableQualities)) {
                $this->selectedResolution = 1; // First = best
            } else {
                $this->selectedResolution = 0;
            }
        } catch (\Exception $e) {
            $this->availableQualities = [];
            $this->resolutionOptions = [];
            $this->dispatch('error', title: 'Stream Probe Failed', message: $e->getMessage());
        }
    }

    public function startRecording()
    {
        // Validate stream URL
        if ($this->streamUrl === '') {
            $this->dispatch('error', title: 'Stream URL Required', message: 'Please enter an HLS stream URL.');

            return;
        }

        // Ensure qualities are loaded
        if (empty($this->availableQualities)) {
            $this->loadStreamQualities();
        }

        $this->recordingActive = true;
        $this->status = 'running';

        // Determine output path with timestamp
        $outputFileName = 'ffmix_web_'.date('Ymd_His').'.mp4';
        $outputPath = sys_get_temp_dir().'/'.$outputFileName;

        // Launch recording in background
        $this->dispatch(
            'start-recording',
            streamUrl: $this->streamUrl,
            outputPath: $outputPath,
            seconds: 30,
            resolution: $this->selectedResolution,
        );

        // Refresh status after short delay
        $this->refreshAfter(2000);
    }

    public function stopRecording()
    {
        $this->recordingActive = false;
        $this->dispatch('stop-recording');
        $this->refreshAfter(1000);
    }

    public function setStreamUrl(string $url)
    {
        $this->streamUrl = $url;
        $this->availableQualities = [];
        $this->resolutionOptions = [];
        $this->selectedResolution = 0;
        $this->dispatch('stream-url-changed', url: $url);
    }

    public function render(): mixed
    {
        // Refresh job data from DB
        $this->refreshJobStatus();

        return view('livewire.stream-dashboard', [
            'streamUrl' => $this->streamUrl,
            'streamType' => $this->streamType,
            'status' => $this->status,
            'duration' => $this->duration,
            'outputPath' => $this->outputPath,
            'recordingActive' => $this->recordingActive,
            'recordedOutput' => $this->recordedOutput,
            'recordedDuration' => $this->recordedDuration,
            'dskTemplate' => $this->dskTemplate,
            'dskTemplates' => $this->dskTemplates,
            'dskTitle' => $this->dskTitle,
            'dskSubtitle' => $this->dskSubtitle,
            'dskAccent' => $this->dskAccent,
            'dskOverlayImage' => $this->dskOverlayImage,
            'selectedResolution' => $this->selectedResolution,
            'resolutionOptions' => $this->resolutionOptions,
            'availableQualities' => $this->availableQualities,
        ]);
    }

    protected function refreshAfter(int $milliseconds): void
    {
        // Status refresh happens via component re-render
    }
}
