<?php

namespace App\Enums;

enum JobStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Running => 'Running',
            self::Completed => 'Done',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => '○ WAIT',
            self::Running => '▶ RUN ',
            self::Completed => '✓ DONE',
            self::Failed => '✗ FAIL',
            self::Cancelled => '■ STOP',
        };
    }
}
