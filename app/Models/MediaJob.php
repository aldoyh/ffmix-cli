<?php

namespace App\Models;

use App\Enums\JobStatus;
use Carbon\CarbonImmutable;
use Database\Factories\MediaJobFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $input_path
 * @property string $output_path
 * @property string|null $preset_slug
 * @property array<string, mixed> $pipeline_config
 * @property string|null $command_preview
 * @property JobStatus $status
 * @property int|null $worker_slot
 * @property float $progress_percent
 * @property float $fps
 * @property string|null $speed
 * @property string|null $bitrate
 * @property float $duration_seconds
 * @property float $processed_seconds
 * @property int|null $exit_code
 * @property string|null $error_message
 * @property string|null $log_tail
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 */
class MediaJob extends Model
{
    /** @use HasFactory<MediaJobFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'input_path',
        'output_path',
        'source_url',
        'session_key',
        'stream_type',
        'preset_slug',
        'pipeline_config',
        'command_preview',
        'record_seconds',
        'dsk_template',
        'dsk_title',
        'dsk_subtitle',
        'dsk_accent',
        'dsk_start',
        'dsk_duration',
        'has_overlay',
        'live_output',
        'status',
        'worker_slot',
        'progress_percent',
        'fps',
        'speed',
        'bitrate',
        'duration_seconds',
        'processed_seconds',
        'exit_code',
        'error_message',
        'log_tail',
        'started_at',
        'completed_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'progress_percent' => 0.0,
        'fps' => 0.0,
        'duration_seconds' => 0.0,
        'processed_seconds' => 0.0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pipeline_config' => 'array',
            'status' => JobStatus::class,
            'worker_slot' => 'integer',
            'progress_percent' => 'float',
            'fps' => 'float',
            'duration_seconds' => 'float',
            'processed_seconds' => 'float',
            'exit_code' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function pending(Builder $query): Builder
    {
        return $query->where('status', JobStatus::Pending->value);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function running(Builder $query): Builder
    {
        return $query->where('status', JobStatus::Running->value);
    }
}
