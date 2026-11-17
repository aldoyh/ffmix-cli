<?php

namespace App\Services\Ffmix;

use App\Enums\JobStatus;
use App\Models\MediaJob;
use Symfony\Component\Process\Process as SymfonyProcess;

class BatchQueueRunner
{
    /**
     * Active worker processes keyed by worker slot (1..8).
     *
     * @var array<int, array{job: MediaJob, process: SymfonyProcess, buffer: string}>
     */
    private array $activeWorkers = [];

    /**
     * Ring buffer of monitor log lines.
     *
     * @var list<string>
     */
    private array $monitorLogs = [];

    public function __construct(
        private FfmpegCommandBuilder $builder,
        private MediaProbeService $probe,
    ) {}

    public function maxWorkers(): int
    {
        return max(1, min(8, (int) config('ffmix.max_workers', 8)));
    }

    public function activeCount(): int
    {
        return count($this->activeWorkers);
    }

    /**
     * @return list<string>
     */
    public function monitorLogs(int $limit = 25): array
    {
        return array_slice($this->monitorLogs, -$limit);
    }

    public function pushLog(string $line): void
    {
        $timestamp = now()->format('H:i:s');
        $this->monitorLogs[] = "[{$timestamp}] {$line}";
        if (count($this->monitorLogs) > 200) {
            $this->monitorLogs = array_slice($this->monitorLogs, -200);
        }
    }

    /**
     * Poll running processes and fill free worker slots (up to 8) from pending jobs.
     */
    public function tick(): void
    {
        // 1. Poll existing workers
        foreach ($this->activeWorkers as $slot => &$worker) {
            $proc = $worker['process'];
            $job = $worker['job'];

            $stdout = $proc->getIncrementalOutput();
            $stderr = $proc->getIncrementalErrorOutput();

            if ($stdout !== '') {
                $worker['buffer'] .= $stdout;
                $this->parseProgressBuffer($job, $worker['buffer']);
            }

            if ($stderr !== '') {
                $lines = array_filter(explode("\n", trim($stderr)), static fn (string $l): bool => $l !== '');
                foreach (array_slice($lines, -3) as $line) {
                    $this->pushLog("W{$slot}#{$job->id}: {$line}");
                }
                $job->log_tail = mb_substr(($job->log_tail ?? '')."\n".$stderr, -2000);
            }

            if (! $proc->isRunning()) {
                $exitCode = $proc->getExitCode() ?? 1;
                $job->exit_code = $exitCode;
                $job->completed_at = now();
                $job->worker_slot = null;

                if ($exitCode === 0) {
                    $job->status = JobStatus::Completed;
                    $job->progress_percent = 100.0;
                    $this->pushLog("W{$slot}#{$job->id} COMPLETED -> {$job->output_path}");
                } else {
                    $job->status = JobStatus::Failed;
                    $job->error_message = mb_substr(trim($proc->getErrorOutput()), -500);
                    $this->pushLog("W{$slot}#{$job->id} FAILED (exit {$exitCode})");
                }

                $job->save();
                unset($this->activeWorkers[$slot]);
            } else {
                $job->save();
            }
        }
        unset($worker);

        // 2. Dispatch pending jobs into free slots up to maxWorkers (8)
        $max = $this->maxWorkers();
        while (count($this->activeWorkers) < $max) {
            $slot = $this->nextAvailableSlot($max);
            if ($slot === null) {
                break;
            }

            $nextJob = MediaJob::query()
                ->where('status', JobStatus::Pending->value)
                ->orderBy('id')
                ->first();

            if ($nextJob === null) {
                break;
            }

            $this->startJobInSlot($nextJob, $slot);
        }
    }

    /**
     * Cancel a running or pending job cleanly.
     */
    public function cancelJob(int $jobId): bool
    {
        foreach ($this->activeWorkers as $slot => $worker) {
            if ($worker['job']->id === $jobId) {
                $worker['process']->stop(1);
                $worker['job']->status = JobStatus::Cancelled;
                $worker['job']->worker_slot = null;
                $worker['job']->completed_at = now();
                $worker['job']->save();
                unset($this->activeWorkers[$slot]);
                $this->pushLog("W{$slot}#{$jobId} CANCELLED by user");

                return true;
            }
        }

        $job = MediaJob::query()->find($jobId);
        if ($job !== null && $job->status === JobStatus::Pending) {
            $job->status = JobStatus::Cancelled;
            $job->save();
            $this->pushLog("Job #{$jobId} cancelled before start");

            return true;
        }

        return false;
    }

    public function stopAll(): void
    {
        foreach ($this->activeWorkers as $slot => $worker) {
            $worker['process']->stop(1);
            $worker['job']->status = JobStatus::Cancelled;
            $worker['job']->worker_slot = null;
            $worker['job']->save();
        }
        $this->activeWorkers = [];
    }

    private function nextAvailableSlot(int $max): ?int
    {
        for ($i = 1; $i <= $max; $i++) {
            if (! isset($this->activeWorkers[$i])) {
                return $i;
            }
        }

        return null;
    }

    private function startJobInSlot(MediaJob $job, int $slot): void
    {
        $config = PipelineConfig::fromArray($job->pipeline_config);

        if ($job->duration_seconds <= 0.0 && is_file($job->input_path)) {
            try {
                $info = $this->probe->probe($job->input_path);
                $job->duration_seconds = max(0.1, $info['duration']);
            } catch (\Throwable) {
                // Keep 0 if probe fails; progress still reports time & fps
            }
        }

        $outputDir = dirname($job->output_path);
        if (! is_dir($outputDir)) {
            @mkdir($outputDir, 0775, true);
        }

        $args = $this->builder->build($job->input_path, $job->output_path, $config, true);
        $job->command_preview = $this->builder->toShellString($args);
        $job->status = JobStatus::Running;
        $job->worker_slot = $slot;
        $job->started_at = now();
        $job->progress_percent = 0.0;
        $job->save();

        $process = new SymfonyProcess($args);
        $process->setTimeout(null);
        $process->start();

        $this->activeWorkers[$slot] = [
            'job' => $job,
            'process' => $process,
            'buffer' => '',
        ];

        $this->pushLog("W{$slot}#{$job->id} STARTED: {$job->name}");
    }

    private function parseProgressBuffer(MediaJob $job, string &$buffer): void
    {
        $lines = explode("\n", $buffer);
        $buffer = array_pop($lines);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            match ($key) {
                'fps' => $job->fps = (float) $value,
                'bitrate' => $job->bitrate = $value !== 'N/A' ? $value : $job->bitrate,
                'speed' => $job->speed = $value !== 'N/A' ? $value : $job->speed,
                'out_time_us' => $this->updateProgressSeconds($job, ((float) $value) / 1_000_000.0),
                'progress' => $value === 'end' ? ($job->progress_percent = 100.0) : null,
                default => null,
            };
        }
    }

    private function updateProgressSeconds(MediaJob $job, float $seconds): void
    {
        $job->processed_seconds = max(0.0, $seconds);
        if ($job->duration_seconds > 0.0) {
            $job->progress_percent = min(99.9, round(($seconds / $job->duration_seconds) * 100.0, 1));
        }
    }
}
