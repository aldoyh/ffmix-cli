<?php

use App\Services\Ffmix\HlsStreamService;
use App\Services\Ffmix\StreamService;
use Illuminate\Support\Facades\Process;

const STREAM_PLAYLIST_URL = 'https://5c7b683162943.streamlock.net/live/ngrp:bahraintvmain_all/playlist.m3u8';

it('detects ffmpeg availability and HLS stream probe requires real ffmpeg', function (): void {
    $ffmpeg = (string) config('ffmix.binaries.ffmpeg', 'ffmpeg');
    if (! Process::run([$ffmpeg, '-version'])->successful()) {
        $this->markTestSkipped('ffmpeg binary not available.');
    }
    expect(Process::run([$ffmpeg, '-version'])->successful())->toBeTrue();
});

it('parses the real HLS playlist master variants and quality ladder', function (): void {
    $ffmpeg = (string) config('ffmix.binaries.ffmpeg', 'ffmpeg');
    if (! Process::run([$ffmpeg, '-version'])->successful()) {
        $this->markTestSkipped('ffmpeg binary not available.');
    }

    $hls = app(HlsStreamService::class);
    $playlist = $hls->probePlaylist(STREAM_PLAYLIST_URL, 15);

    expect($playlist['stream_url'])->toBe(STREAM_PLAYLIST_URL)
        ->and(count($playlist['qualities']))->toBeGreaterThanOrEqual(1)
        ->and($playlist['qualities'][0]['bandwidth'])->toBeGreaterThan(0)
        ->and($playlist['qualities'][0]['url'])->toStartWith('https://');
});

it('records a real HLS stream to MP4 without overlay from CLI', function (): void {
    $ffmpeg = (string) config('ffmix.binaries.ffmpeg', 'ffmpeg');
    if (! Process::run([$ffmpeg, '-version'])->successful()) {
        $this->markTestSkipped('ffmpeg binary not available.');
    }

    $tmpDir = sys_get_temp_dir().'/ffmix_stream_pipeline_'.uniqid();
    mkdir($tmpDir, 0775, true);
    $outputPath = $tmpDir.'/live_no_overlay.mp4';

    $stream = app(StreamService::class);
    $result = $stream->runStreamRecord([
        'stream_url' => STREAM_PLAYLIST_URL,
        'output_path' => $outputPath,
        'record_seconds' => 8,
        'resolution_index' => null,
        'dsk' => [],
    ]);

    expect(is_file($result['output_path']))->toBeTrue()
        ->and($result['duration'])->toBe(8)
        ->and(filesize($result['output_path']))->toBeGreaterThan(1000);

    @unlink($outputPath);
    @rmdir($tmpDir);
});

it('keeps an HLS recording successful when DSK rendering fails', function (): void {
    $ffmpeg = (string) config('ffmix.binaries.ffmpeg', 'ffmpeg');
    if (! Process::run([$ffmpeg, '-version'])->successful()) {
        $this->markTestSkipped('ffmpeg binary not available.');
    }

    $tmpDir = sys_get_temp_dir().'/ffmix_stream_dsk_fallback_'.uniqid();
    mkdir($tmpDir, 0775, true);
    $outputPath = $tmpDir.'/live_raw_fallback.mp4';

    $stream = app(StreamService::class);
    $result = $stream->runStreamRecord([
        'stream_url' => STREAM_PLAYLIST_URL,
        'output_path' => $outputPath,
        'record_seconds' => 3,
        'resolution_index' => null,
        'dsk' => [
            'template_path' => $tmpDir.'/missing-template.html',
        ],
    ]);

    expect(is_file($result['output_path']))->toBeTrue()
        ->and($result['dsk_frames'])->toBe(0)
        ->and($result['dsk_error'])->toContain('template');

    @unlink($outputPath);
    @rmdir($tmpDir);
});

it('records a real HLS stream and applies DSK overlay from CLI', function (): void {
    $ffmpeg = (string) config('ffmix.binaries.ffmpeg', 'ffmpeg');
    $node = 'node';
    $chromePath = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';

    if (! Process::run([$ffmpeg, '-version'])->successful()) {
        $this->markTestSkipped('ffmpeg binary not available.');
    }
    if (! file_exists($chromePath)) {
        $this->markTestSkipped('Headless Chrome not found; DSK overlay requires Chrome.');
    }

    $tmpDir = sys_get_temp_dir().'/ffmix_stream_dsk_'.uniqid();
    mkdir($tmpDir, 0775, true);

    // Copy sample DSK template to temp dir
    $templateSource = resource_path('dsk/sample-lower-third.html');
    $templatePath = $tmpDir.'/sample-lower-third.html';
    copy($templateSource, $templatePath);

    $outputPath = $tmpDir.'/live_with_dsk.mp4';

    $stream = app(StreamService::class);
    $result = $stream->runStreamRecord([
        'stream_url' => STREAM_PLAYLIST_URL,
        'output_path' => $outputPath,
        'record_seconds' => 10,
        'resolution_index' => null,
        'dsk' => [
            'template_path' => $templatePath,
            'title' => 'Bahrain TV Live',
            'subtitle' => 'ffMix HLS Stream Overlay Test',
            'accent' => '#06b6d4',
            'start' => 1.0,
            'duration' => 6.0,
            'width' => 1280,
            'height' => 720,
            'fps' => 30,
        ],
    ]);

    expect(is_file($result['output_path']))->toBeTrue()
        ->and($result['duration'])->toBe(10)
        ->and(isset($result['dsk_frames']))->toBeTrue()
        ->and($result['dsk_frames'])->toBeGreaterThan(0)
        ->and(filesize($result['output_path']))->toBeGreaterThan(1000);

    // Verify output is a valid MP4 with video and audio streams
    $ffprobe = (string) config('ffmix.binaries.ffprobe', 'ffprobe');
    $probeResult = Process::run([
        $ffprobe, '-v', 'error', '-show_streams', '-of', 'json', $result['output_path'],
    ]);
    $streams = json_decode((string) $probeResult->output(), true)['streams'] ?? [];
    $videoStream = null;
    $audioStream = null;
    foreach ($streams as $s) {
        if ($s['codec_type'] ?? null === 'video') {
            $videoStream = $s;
        }
        if ($s['codec_type'] ?? null === 'audio') {
            $audioStream = $s;
        }
    }

    expect($videoStream)->not->toBeNull()
        ->and($audioStream)->not->toBeNull();

    @unlink($templatePath);
    @unlink($outputPath);
    @rmdir($tmpDir);
});

it('builds continuous Mixer stream and record commands for local and live sources', function (): void {
    $stream = app(StreamService::class);

    $streamArgs = $stream->buildLiveStreamArgs(
        '/Users/aldoyh/Movies/BahrainFolkloreMusic1.mp3',
        '/tmp/ffmix-mixer-hls',
    );
    expect($streamArgs)
        ->toContain('-f')
        ->toContain('hls')
        ->toContain('-vn')
        ->toContain('/tmp/ffmix-mixer-hls/stream.m3u8');

    $recordArgs = $stream->buildLiveRecordArgs(
        '/Users/aldoyh/Movies/BahrainFolkloreMusic1.mp3',
        '/tmp/ffmix-mixer-record.mp4',
    );
    expect($recordArgs)
        ->toContain('-re')
        ->toContain('-vn')
        ->toContain('/tmp/ffmix-mixer-record.mp4');
});

it('tracks stream jobs in the database', function (): void {
    $stream = app(StreamService::class);

    $job = $stream->createJob(
        'stream_test_'.uniqid(),
        STREAM_PLAYLIST_URL,
        [
            'stream_url' => STREAM_PLAYLIST_URL,
            'output_path' => '/tmp/test.mp4',
            'record_seconds' => 10,
            'stream_type' => 'hls',
        ]
    );

    expect($job->id)->not->toBeNull()
        ->and($job->status->value)->toBe('pending')
        ->and($job->has_overlay)->toBeFalse();

    $job = $stream->markJobStarted($job);
    expect($job->status->value)->toBe('running');

    $job = $stream->markJobFinished($job, '/tmp/test_output.mp4');
    expect($job->status->value)->toBe('completed')
        ->and($job->output_path)->toBe('/tmp/test_output.mp4');

    $job->delete();
});
