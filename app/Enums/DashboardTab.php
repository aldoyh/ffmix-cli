<?php

namespace App\Enums;

enum DashboardTab: string
{
    case Queue = 'queue';
    case Studio = 'studio';
    case VisualFx = 'fx';
    case Audio = 'audio';
    case Analyzer = 'analyzer';
    case Tools = 'tools';
    case DropZones = 'dropzones';
    case Monitor = 'monitor';
    case Show = 'show';

    public function number(): int
    {
        return match ($this) {
            self::Queue => 1,
            self::Studio => 2,
            self::VisualFx => 3,
            self::Audio => 4,
            self::Analyzer => 5,
            self::Tools => 6,
            self::DropZones => 7,
            self::Monitor => 8,
            self::Show => 9,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Queue => 'Queue',
            self::Studio => 'Studio',
            self::VisualFx => 'FX/LUT',
            self::Audio => 'Audio',
            self::Analyzer => 'Analyze',
            self::Tools => 'Tools',
            self::DropZones => 'DropZone',
            self::Monitor => 'Monitor',
            self::Show => 'Mixer/DSK',
        };
    }

    public static function fromNumber(int $number): self
    {
        return match ($number) {
            1 => self::Queue,
            2 => self::Studio,
            3 => self::VisualFx,
            4 => self::Audio,
            5 => self::Analyzer,
            6 => self::Tools,
            7 => self::DropZones,
            8 => self::Monitor,
            9 => self::Show,
            default => self::Queue,
        };
    }

    public function next(): self
    {
        $cases = self::cases();
        $index = array_search($this, $cases, true);
        $nextIndex = ($index === false) ? 0 : (($index + 1) % count($cases));

        return $cases[$nextIndex];
    }

    public function previous(): self
    {
        $cases = self::cases();
        $index = array_search($this, $cases, true);
        $prevIndex = ($index === false) ? 0 : (($index - 1 + count($cases)) % count($cases));

        return $cases[$prevIndex];
    }
}
