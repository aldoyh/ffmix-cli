<?php

namespace App\Enums;

enum MergeMode: string
{
    case Passthru = 'passthru';
    case Complex = 'complex';

    public function label(): string
    {
        return match ($this) {
            self::Passthru => 'Concat Passthru (Lossless Fast)',
            self::Complex => 'Complex Filter (Re-encode & Normalize)',
        };
    }
}
