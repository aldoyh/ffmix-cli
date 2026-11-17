<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\DropZoneFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $watch_path
 * @property string $output_path
 * @property string $preset_slug
 * @property list<string>|null $extensions
 * @property string $post_action
 * @property bool $is_active
 * @property int $processed_count
 * @property CarbonImmutable|null $last_scanned_at
 */
class DropZone extends Model
{
    /** @use HasFactory<DropZoneFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'watch_path',
        'output_path',
        'preset_slug',
        'extensions',
        'post_action',
        'is_active',
        'processed_count',
        'last_scanned_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'preset_slug' => 'desktop',
        'post_action' => 'keep',
        'is_active' => true,
        'processed_count' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'extensions' => 'array',
            'is_active' => 'boolean',
            'processed_count' => 'integer',
            'last_scanned_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
