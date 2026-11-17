<?php

namespace App\Tui;

class TerminalScreen
{
    private ?string $savedStty = null;

    private bool $active = false;

    public function enter(): void
    {
        if ($this->active) {
            return;
        }

        if (function_exists('posix_isatty') && posix_isatty(STDIN)) {
            $state = shell_exec('stty -g 2>/dev/null');
            if (is_string($state) && trim($state) !== '') {
                $this->savedStty = trim($state);
            }
            @system('stty -icanon -echo -isig min 0 time 0 2>/dev/null');
        }

        $this->active = true;
        register_shutdown_function(function (): void {
            $this->restore();
        });

        // Alternate screen buffer + hide cursor
        fwrite(STDOUT, "\e[?1049h\e[?25l\e[2J\e[H");
        fflush(STDOUT);
    }

    public function restore(): void
    {
        if (! $this->active) {
            return;
        }

        $this->active = false;
        fwrite(STDOUT, "\e[0m\e[?25h\e[?1049l");
        fflush(STDOUT);

        if ($this->savedStty !== null) {
            @system('stty '.escapeshellarg($this->savedStty).' 2>/dev/null');
            $this->savedStty = null;
        }
    }

    /**
     * @return array{width: int, height: int}
     */
    public function size(): array
    {
        $sttySize = shell_exec('stty size 2>/dev/null');
        if (is_string($sttySize) && preg_match('/^(\d+)\s+(\d+)$/', trim($sttySize), $m) === 1) {
            return [
                'width' => max(80, (int) $m[2]),
                'height' => max(24, (int) $m[1]),
            ];
        }

        $cols = (int) (getenv('COLUMNS') ?: 120);
        $lines = (int) (getenv('LINES') ?: 36);

        return [
            'width' => max(80, $cols),
            'height' => max(24, $lines),
        ];
    }

    public function drawFrame(string $frame): void
    {
        fwrite(STDOUT, "\e[H".$frame);
        fflush(STDOUT);
    }

    public function readKey(int $timeoutUs = 80000): ?string
    {
        $read = [STDIN];
        $write = null;
        $except = null;

        $changed = @stream_select($read, $write, $except, 0, $timeoutUs);
        if ($changed === false || $changed === 0) {
            return null;
        }

        $bytes = fread(STDIN, 16);
        if ($bytes === false || $bytes === '') {
            return null;
        }

        return match ($bytes) {
            "\e[A", "\eOA" => 'UP',
            "\e[B", "\eOB" => 'DOWN',
            "\e[C", "\eOC" => 'RIGHT',
            "\e[D", "\eOD" => 'LEFT',
            "\e[Z" => 'SHIFT_TAB',
            "\t" => 'TAB',
            "\n", "\r" => 'ENTER',
            "\x7f", "\x08" => 'BACKSPACE',
            "\x03" => 'CTRL_C',
            "\e" => 'ESC',
            default => $bytes,
        };
    }
}
