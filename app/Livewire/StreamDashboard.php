<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\MediaJob;
use App\Services\Ffmix\HlsStreamService;
use App\Services\Ffmix\StreamService;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class StreamDashboard extends Component
{
    public $streamUrl = '';
    public $streamType = 'hls';
    public $status = 'pending';
    public $duration = 0;
    public $outputPath = '';
    public $recordingActive = false;
    public $recordedOutput = '';
    public $recordedDuration = 0;
    public $dskTemplate = '';
    public $dskTitle = '';
    public $dskSubtitle = '';
    public $dskAccent = '#06b6d4';
    public $dskOverlayImage = '';

    protected HlsStreamService $hls;
    protected StreamService $streamService;

    public function __construct()
    {
        $this->hls = app(HlsStreamService::class);
        $this->streamService = app(StreamService::class);
    }

    public function mount()
    {
        $this->refreshJobStatus();
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

    public function startRecording()
    {
        // Validate stream URL
        if ($this->streamUrl === '') {
            $this->dispatch('error', title: 'Stream URL Required', message: 'Please enter an HLS stream URL.');
            return;
        }

        $this->recordingActive = true;
        $this->status = 'running';

        // Launch recording in background via artisan command
        $this->dispatch(
            'start-recording',
            streamUrl: $this->streamUrl,
            outputPath: sys_get_temp_dir().'/ffmix_web_'.uniqid('.mp4'),
            seconds: 30,
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
            'dskTitle' => $this->dskTitle,
            'dskSubtitle' => $this->dskSubtitle,
            'dskAccent' => $this->dskAccent,
            'dskOverlayImage' => $this->dskOverlayImage,
        ]);
    }

    protected function refreshAfter(int $milliseconds): void
    {
        // This dispables a JS refresh after the given milliseconds
        // The actual status refresh will happen via component re-render
    }
}