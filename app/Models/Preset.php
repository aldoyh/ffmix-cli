<?php

namespace App\Models;

use Database\Factories\PresetFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $category
 * @property string|null $description
 * @property bool $is_builtin
 * @property array<string, mixed> $config
 */
class Preset extends Model
{
    /** @use HasFactory<PresetFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'category',
        'description',
        'is_builtin',
        'config',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_builtin' => 'boolean',
            'config' => 'array',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function builtin(Builder $query): Builder
    {
        return $query->where('is_builtin', true);
    }

    /**
     * Ensure built-in presets from config are seeded into the database.
     */
    public static function syncBuiltins(): void
    {
        /** @var list<array{slug: string, name: string, category: string, description: string, config: array<string, mixed>}> $builtins */
        $builtins = config('ffmix.builtin_presets', []);

        foreach ($builtins as $item) {
            self::query()->updateOrCreate(
                ['slug' => $item['slug']],
                [
                    'name' => $item['name'],
                    'category' => $item['category'],
                    'description' => $item['description'],
                    'is_builtin' => true,
                    'config' => $item['config'],
                ],
            );
        }
    }
}
