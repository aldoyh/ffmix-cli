<?php

namespace App\Models;

use Database\Factories\AnalysisReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $kind
 * @property string $source_path
 * @property string|null $reference_path
 * @property array<string, mixed> $summary
 * @property list<mixed>|null $series
 * @property string|null $raw_output
 */
class AnalysisReport extends Model
{
    /** @use HasFactory<AnalysisReportFactory> */
    use HasFactory;

    protected $fillable = [
        'kind',
        'source_path',
        'reference_path',
        'summary',
        'series',
        'raw_output',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'series' => 'array',
        ];
    }
}
