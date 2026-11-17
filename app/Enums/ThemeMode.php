<?php

namespace App\Enums;

enum ThemeMode: string
{
    case Dark = 'dark';
    case Light = 'light';

    public function toggle(): self
    {
        return $this === self::Dark ? self::Light : self::Dark;
    }

    public function label(): string
    {
        return match ($this) {
            self::Dark => 'Dark',
            self::Light => 'Light',
        };
    }
}
