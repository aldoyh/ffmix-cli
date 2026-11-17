<?php

namespace App\Enums;

enum SubtitleMode: string
{
    case None = 'none';
    case Soft = 'soft';
    case Hardburn = 'hardburn';

    public function label(): string
    {
        return match ($this) {
            self::None => 'None',
            self::Soft => 'Soft Subtitles (Embedded/External)',
            self::Hardburn => 'Hardburned Subtitles',
        };
    }
}
