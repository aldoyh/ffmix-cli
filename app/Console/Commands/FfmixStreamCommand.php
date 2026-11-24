<?php

namespace App\Console\Commands;

use App\Models\MediaJob;
use App\Services\Ffmix\DownstreamKeyService;
use App\Services\Ffmix\HlsStreamService;
use App\Services\Ffmix\StreamService;
use Illuminate\Console\Command;

class FfmixStreamCommand extends Command
{
    protected $signature = 'ffmix:stream
        {action=record : Action (record|qualities|hls|list|info|jobs|monitor)}
        {--stream-url= : HLS master playlist URL}
        {--monitor-url= : Override monitor dashboard URL}
        {--output= : Output MP4 file path}
        {--seconds=60 : Recording duration in seconds}
        {--resolution= : Quality level index (1=best, 2=2nd, etc.)}
        {--overlay-template= : DSK HTML template path}
        {--overlay-title= : DSK title text}
        {--overlay-subtitle= : DSK subtitle text}
        {--overlay-accent= : DSK accent color hex}
        {--overlay-start=1.0 : DSK start time in seconds}
        {--overlay-duration=6.0 : DSK overlay duration in seconds}
        {--job-id= : Job ID to manage}
        {--list : List all active stream jobs}
        {--info : Show job info}
        {--list-type=all : List jobs type (pending|running|completed|failed|all)}';

    protected $description = 'Live stream management: record HLS streams with optional DSK overlays, generate HLS output, list jobs';

    public function handle(HlsStreamService $hls, StreamService $stream, DownstreamKeyService $dsk): int
    {
        $action = (string) $this->argument('action');

        if ($action === 'list') {
            $type = (string) ($this->option('list-type') ?? 'all');

            $query = MediaJob::query();
            if ($type === 'pending') {
                $query->where('status', 'pending');
            } elseif ($type === 'running') {
                $query->where('status', 'running');
            } elseif ($type === 'completed') {
                $query->where('status', 'completed');
            } elseif ($type === 'failed') {
                $query->where('status', 'failed');
            }

            $jobs = $query->orderByDesc('id')->get(['id', 'session_key', 'source_url', 'stream_type', 'status', 'output_path', 'started_at', 'completed_at']);

            if ($jobs->isEmpty()) {
                $this->info('No stream jobs found.');

                return self::SUCCESS;
            }

            $this->table(
                ['ID', 'Session Key', 'Source URL', 'Type', 'Status', 'Output Path', 'Started', 'Finished'],
                $jobs->map(static fn (MediaJob $j): array => [
                    $j->id,
                    substr($j->session_key ?? 'n/a', 0, 12),
                    substr($j->source_url ?? 'n/a', 0, 60),
                    $j->stream_type ?? 'n/a',
                    $j->status->value,
                    substr($j->output_path ?? 'n/a', 0, 50),
                    $j->started_at?->toDateTimeString() ?? 'never',
                    $j->completed_at?->toDateTimeString() ?? 'never',
                ])
            );

            return self::SUCCESS;
        }

        if ($action === 'info') {
            $jobId = (int) ($this->option('job-id') ?? 0);
            $job = MediaJob::find($jobId);

            if ($job === null) {
                $this->error("Job #{$jobId} not found.");

                return self::FAILURE;
            }

            $this->info('Job #'.$job->id);
            $this->info('  Session Key: '.$job->session_key);
            $this->info('  Source URL: '.$job->source_url);
            $this->info('  Stream Type: '.$job->stream_type);
            $this->info('  Status: '.$job->status->value ?? $job->status);
            $this->info('  Output Path: '.$job->output_path);
            $this->info('  Started: '.$job->started_at?->toDateTimeString());
            $this->info('  Finished: '.$job->completed_at?->toDateTimeString());
            if ($job->error_message) {
                $this->error('  Error: '.$job->error_message);
            }

            return self::SUCCESS;
        }

        if ($action === 'record') {
            return $this->handleRecord($hls, $stream, $dsk);
        }

        if ($action === 'hls') {
            return $this->handleHls($hls);
        }

        if ($action === 'qualities') {
            return $this->handleHls($hls);
        }

        if ($action === 'monitor') {
            return $this->handleMonitor();
        }

        $this->error("Unknown action '{$action}'. Use: record, hls, list, info, monitor");

        return self::FAILURE;
    }

    private function handleRecord(HlsStreamService $hls, StreamService $stream, DownstreamKeyService $dsk): int
    {
        $streamUrl = (string) $this->option('stream-url');
        if ($streamUrl === '') {
            $this->error('HLS stream URL is required. Use --stream-url.');

            return self::FAILURE;
        }

        $outputPath = (string) $this->option('output');
        if ($outputPath === '') {
            $outputPath = sys_get_temp_dir().'/ffmix_record_'.uniqid('', true).'.mp4';
        }

        $recordSeconds = (int) $this->option('seconds', 60);
        $resolutionIndex = (int) ($this->option('resolution') ?? 0);
        $jobId = (int) $this->option('job-id');

        // Create or update job
        if ($jobId > 0) {
            $job = MediaJob::find($jobId);
            if ($job === null) {
                $this->error("Job #{$jobId} not found.");

                return self::FAILURE;
            }
        } else {
            $job = $stream->createJob('stream_'.uniqid(), $streamUrl, [
                'stream_url' => $streamUrl,
                'output_path' => $outputPath,
                'record_seconds' => $recordSeconds,
                'resolution_index' => $resolutionIndex,
            ]);
            $job = $stream->markJobStarted($job);
        }

        // Build DSK config if overlay template provided
        $dskConfig = [];
        $overlayTemplate = (string) $this->option('overlay-template');
        $overlayTitle = (string) $this->option('overlay-title');
        $overlaySubtitle = (string) $this->option('overlay-subtitle');
        $overlayAccent = (string) $this->option('overlay-accent');
        $overlayStart = (float) $this->option('overlay-start', 1.0);
        $overlayDuration = (float) $this->option('overlay-duration', 6.0);

        if ($overlayTemplate !== '') {
            if (! is_file($overlayTemplate)) {
                $this->error("DSK template file not found: {$overlayTemplate}");

                return self::FAILURE;
            }

            $dskConfig = [
                'template_path' => $overlayTemplate,
                'title' => $overlayTitle !== '' ? $overlayTitle : 'ffMix Live Broadcast',
                'subtitle' => $overlaySubtitle !== '' ? $overlaySubtitle : 'Normalized 1080p30 Feed',
                'accent' => $overlayAccent !== '' ? $overlayAccent : '#06b6d4',
                'start' => $overlayStart,
                'duration' => $overlayDuration,
            ];
        }

        // Run the full stream record + DSK overlay pipeline
        try {
            $result = $stream->runStreamRecord(array_merge([
                'stream_url' => $streamUrl,
                'output_path' => $outputPath,
                'record_seconds' => $recordSeconds,
                'resolution_index' => $resolutionIndex,
            ], $dskConfig));

            $job = $stream->markJobFinished($job, $result['output_path']);

            $this->info('Recording complete!');
            $this->info('  Output: '.$result['output_path']);
            $this->info('  Duration: '.$result['duration'].' seconds');
            if (isset($result['dsk_frames'])) {
                $this->info('  DSK Frames: '.$result['dsk_frames']);
            }
            if ($job->session_key) {
                $this->info('  Session Key: '.$job->session_key);
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $job = MediaJob::find($job->id ?? 0) ?? $job;
            if ($job !== null) {
                $job = $stream->markJobFailed($job, $e->getMessage());
            }

            $this->error('Recording failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function handleHls(HlsStreamService $hls): int
    {
        $streamUrl = (string) $this->option('stream-url');
        if ($streamUrl === '') {
            $this->error('HLS stream URL is required. Use --stream-url.');

            return self::FAILURE;
        }

        // Probe available qualities
        $playlist = $hls->probePlaylist($streamUrl, 15);
        $qualities = $playlist['qualities'];

        $this->info('HLS Stream Qualities:');
        foreach ($qualities as $idx => $q) {
            $res = $q['resolution'] ?? 'source';
            $bw = $q['bandwidth'] ?? 0;
            $codec = $q['codec'] ?? 'unknown';
            $this->info("  [{$idx}] Bandwidth: ".($bw > 0 ? number_format($bw) : 'auto').' | Resolution: '.$res.' | Codecs: '.$codec);
        }

        // Probe first quality's stream info
        if (count($qualities) > 0) {
            $info = $hls->probeMediaStream($qualities[0]['url'] ?? $streamUrl, 15);
            $this->info('Selected quality info:');
            $this->info('  Codec: '.$info['codec'] ?? 'unknown');
            $this->info('  Resolution: '.($info['width'] ?? 'x').'x'.($info['height'] ?? 'unknown'));
            $this->info('  Frame Rate: '.$info['frame_rate']);
            $this->info('  Duration: '.($info['duration'] ?? 'unknown').' seconds');
        }

        return self::SUCCESS;
    }
}
