<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\NormalizedAssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $show_preset_id
 * @property string $source_path
 * @property string $normalized_path
 * @property string $asset_type
 * @property int $source_mtime
 * @property int $source_size
 * @property string $status
 * @property float $duration_seconds
 * @property string|null $error_message
 * @property CarbonImmutable|null $normalized_at
 */
class NormalizedAsset extends Model
{
    /** @use HasFactory<NormalizedAssetFactory> */
    use HasFactory;

    protected $fillable = [
        'show_preset_id',
        'source_path',
        'normalized_path',
        'asset_type',
        'source_mtime',
        'source_size',
        'status',
        'duration_seconds',
        'error_message',
        'normalized_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_mtime' => 'integer',
            'source_size' => 'integer',
            'duration_seconds' => 'float',
            'normalized_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ShowPreset, $this>
     */
    public function showPreset(): BelongsTo
    {
        return $this->belongsTo(ShowPreset::class);
    }
}
