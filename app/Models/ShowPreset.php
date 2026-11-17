<?php

namespace App\Models;

use Database\Factories\ShowPresetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property array<string, mixed> $config
 */
class ShowPreset extends Model
{
    /** @use HasFactory<ShowPresetFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'is_active',
        'config',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'config' => 'array',
        ];
    }

    /**
     * @return HasMany<NormalizedAsset, $this>
     */
    public function normalizedAssets(): HasMany
    {
        return $this->hasMany(NormalizedAsset::class);
    }

    /**
     * Seed built-in show presets if none exist yet and return the active ShowPreset.
     */
    public static function activeOrDefault(): self
    {
        self::syncBuiltins();

        $active = self::query()->where('is_active', true)->first();
        if ($active !== null) {
            return $active;
        }

        $first = self::query()->orderBy('id')->firstOrFail();
        $first->update(['is_active' => true]);

        return $first;
    }

    public static function syncBuiltins(): void
    {
        if (self::query()->count() > 0) {
            return;
        }

        /** @var list<array{slug: string, name: string, description: string, is_active: bool, config: array<string, mixed>}> $builtins */
        $builtins = config('ffmix.builtin_show_presets', []);

        foreach ($builtins as $item) {
            self::query()->updateOrCreate(
                ['slug' => $item['slug']],
                [
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'is_active' => $item['is_active'],
                    'config' => $item['config'],
                ],
            );
        }
    }

    public function activate(): void
    {
        self::query()->where('id', '!=', $this->id)->update(['is_active' => false]);
        $this->update(['is_active' => true]);
    }
}
