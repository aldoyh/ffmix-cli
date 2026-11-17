<?php

namespace App\Tui;

use App\Enums\ThemeMode;

class Theme
{
    public function __construct(public readonly ThemeMode $mode = ThemeMode::Dark) {}

    public function reset(): string
    {
        return "\e[0m";
    }

    public function bold(string $text): string
    {
        return "\e[1m{$text}\e[22m";
    }

    public function dim(string $text): string
    {
        return "\e[2m{$text}\e[22m";
    }

    public function headerBar(string $text): string
    {
        if ($this->mode === ThemeMode::Light) {
            return "\e[48;5;25m\e[38;5;255m\e[1m{$text}\e[0m";
        }

        return "\e[48;5;24m\e[38;5;255m\e[1m{$text}\e[0m";
    }

    public function footerBar(string $text): string
    {
        if ($this->mode === ThemeMode::Light) {
            return "\e[48;5;254m\e[38;5;236m{$text}\e[0m";
        }

        return "\e[48;5;236m\e[38;5;252m{$text}\e[0m";
    }

    public function activeTab(string $text): string
    {
        if ($this->mode === ThemeMode::Light) {
            return "\e[48;5;33m\e[38;5;255m\e[1m{$text}\e[0m";
        }

        return "\e[48;5;39m\e[38;5;16m\e[1m{$text}\e[0m";
    }

    public function inactiveTab(string $text): string
    {
        if ($this->mode === ThemeMode::Light) {
            return "\e[48;5;252m\e[38;5;240m{$text}\e[0m";
        }

        return "\e[48;5;237m\e[38;5;250m{$text}\e[0m";
    }

    public function border(string $text, bool $focused = false): string
    {
        if ($focused) {
            $code = $this->mode === ThemeMode::Light ? '38;5;27' : '38;5;45';

            return "\e[{$code}m{$text}\e[0m";
        }

        $code = $this->mode === ThemeMode::Light ? '38;5;246' : '38;5;240';

        return "\e[{$code}m{$text}\e[0m";
    }

    public function title(string $text, bool $focused = false): string
    {
        if ($focused) {
            $code = $this->mode === ThemeMode::Light ? '38;5;25' : '38;5;51';

            return "\e[{$code};1m{$text}\e[0m";
        }

        $code = $this->mode === ThemeMode::Light ? '38;5;236' : '38;5;252';

        return "\e[{$code};1m{$text}\e[0m";
    }

    public function text(string $text): string
    {
        $code = $this->mode === ThemeMode::Light ? '38;5;234' : '38;5;252';

        return "\e[{$code}m{$text}\e[0m";
    }

    public function muted(string $text): string
    {
        $code = $this->mode === ThemeMode::Light ? '38;5;244' : '38;5;244';

        return "\e[{$code}m{$text}\e[0m";
    }

    public function accent(string $text): string
    {
        $code = $this->mode === ThemeMode::Light ? '38;5;27' : '38;5;45';

        return "\e[{$code};1m{$text}\e[0m";
    }

    public function success(string $text): string
    {
        $code = $this->mode === ThemeMode::Light ? '38;5;28' : '38;5;42';

        return "\e[{$code};1m{$text}\e[0m";
    }

    public function warning(string $text): string
    {
        $code = $this->mode === ThemeMode::Light ? '38;5;166' : '38;5;214';

        return "\e[{$code};1m{$text}\e[0m";
    }

    public function danger(string $text): string
    {
        $code = $this->mode === ThemeMode::Light ? '38;5;160' : '38;5;203';

        return "\e[{$code};1m{$text}\e[0m";
    }

    public function selectedRow(string $text): string
    {
        if ($this->mode === ThemeMode::Light) {
            return "\e[48;5;153m\e[38;5;16m\e[1m{$text}\e[0m";
        }

        return "\e[48;5;24m\e[38;5;255m\e[1m{$text}\e[0m";
    }
}
