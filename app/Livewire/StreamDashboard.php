<?php

namespace App\Livewire;

use App\Models\MediaJob;
use App\Services\Ffmix\DownstreamKeyService;
use App\Services\Ffmix\HlsStreamService;
use App\Services\Ffmix\MediaSampleService;
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

    public $sampleSources = [];

    public $selectedSample = '';

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
        $this->loadSampleSources();
        $this->loadStreamQualities();
    }

    public function loadSampleSources(): void
    {
        $this->sampleSources = app(MediaSampleService::class)->all();
    }

    public function selectSample(string $source): void
    {
        $sample = collect($this->sampleSources)->firstWhere('source', $source);
        if (! is_array($sample)) {
            $this->dispatch('error', title: 'Sample Media', message: 'The selected sample is no longer available.');

            return;
        }

        $this->selectedSample = $source;
        $this->streamUrl = $source;
        $this->streamType = in_array($sample['kind'], ['hls', 'audio-hls'], true) ? 'hls' : 'file';
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
        } catch (\Throwable $e) {
            $this->dskTemplates = [];
            $this->dispatch('error', title: 'DSK Templates', message: $e->getMessage());
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

        if (is_file($this->streamUrl)) {
            try {
                $info = $this->hls->probeMediaStream($this->streamUrl, 15);
                $resolution = ($info['width'] ?? null) && ($info['height'] ?? null)
                    ? $info['width'].'x'.$info['height']
                    : 'Audio';
                $codec = $info['codec'] ?? $info['audio_codec'] ?? 'source';
                $this->availableQualities = [[
                    'bandwidth' => 0,
                    'resolution' => $resolution,
                    'codec' => $codec,
                    'url' => $this->streamUrl,
                ]];
                $this->resolutionOptions = [[
                    'index' => 1,
                    'label' => 'Source: '.$resolution.' ('.$codec.')',
                    'bandwidth' => 0,
                    'resolution' => $resolution,
                    'url' => $this->streamUrl,
                    'isMaster' => true,
                ]];
                $this->selectedResolution = 1;
            } catch (\Exception $e) {
                $this->availableQualities = [];
                $this->resolutionOptions = [];
                $this->dispatch('error', title: 'Local Media Probe Failed', message: $e->getMessage());
            }

            return;
        }

        try {
            $qualities = $this->streamService->probeStreamQualities($this->streamUrl, 15);
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

        if (empty($this->availableQualities)) {
            $this->loadStreamQualities();
        }

        $outputFileName = 'ffmix_web_'.date('Ymd_His').'.mp4';
        $outputPath = sys_get_temp_dir().'/'.$outputFileName;
        $job = $this->streamService->createJob('stream_'.uniqid(), $this->streamUrl, [
            'stream_url' => $this->streamUrl,
            'stream_type' => $this->streamType,
            'output_path' => $outputPath,
            'record_seconds' => 30,
            'resolution_index' => $this->selectedResolution,
        ]);
        $job = $this->streamService->markJobStarted($job);

        $logFile = storage_path('logs/ffmix-web-record-'.$job->id.'.log');
        $command = sprintf(
            'nohup %s %s ffmix:stream record --stream-url=%s --seconds=30 --resolution=%d --output=%s --job-id=%d > %s 2>&1 & echo $!',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(base_path('artisan')),
            escapeshellarg($this->streamUrl),
            max(0, (int) $this->selectedResolution),
            escapeshellarg($outputPath),
            $job->id,
            escapeshellarg($logFile),
        );
        $pid = (int) trim((string) shell_exec($command));

        if ($pid <= 0) {
            $this->streamService->markJobFailed($job, 'The background recording process could not be started.');
            $this->recordingActive = false;
            $this->status = 'failed';
            $this->dispatch('error', title: 'Recording Start Failed', message: 'The background recording process could not be started.');

            return;
        }

        $pipelineConfig = $job->pipeline_config;
        $pipelineConfig['record_pid'] = $pid;
        $pipelineConfig['record_log'] = $logFile;
        $job->update(['pipeline_config' => $pipelineConfig]);

        $this->recordingActive = true;
        $this->status = 'running';
        $this->dispatch('start-recording', streamUrl: $this->streamUrl, outputPath: $outputPath, seconds: 30, resolution: $this->selectedResolution);
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
            'sampleSources' => $this->sampleSources,
            'selectedSample' => $this->selectedSample,
        ]);
    }

    protected function refreshAfter(int $milliseconds): void
    {
        // Status refresh happens via component re-render
    }
}
