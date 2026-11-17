<?php

namespace App\Tui;

class Ansi
{
    public static function strip(string $text): string
    {
        return (string) preg_replace('/\e\[[0-9;?]*[A-Za-z]/', '', $text);
    }

    public static function width(string $text): int
    {
        return mb_strwidth(self::strip($text), 'UTF-8');
    }

    public static function pad(string $text, int $width): string
    {
        $visible = self::width($text);
        if ($visible >= $width) {
            return $text;
        }

        return $text.str_repeat(' ', $width - $visible);
    }

    public static function truncate(string $text, int $width): string
    {
        if ($width <= 0) {
            return '';
        }

        if (self::width($text) <= $width) {
            return $text;
        }

        if ($width === 1) {
            return mb_strimwidth(self::strip($text), 0, 1, '', 'UTF-8');
        }

        $targetWidth = $width - 1;
        $tokens = preg_split('/(\e\[[0-9;?]*[A-Za-z])/', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if ($tokens === false) {
            return mb_strimwidth(self::strip($text), 0, $targetWidth, '', 'UTF-8').'…';
        }

        $out = '';
        $used = 0;
        $hasAnsi = false;

        foreach ($tokens as $token) {
            if (str_starts_with($token, "\e[")) {
                $out .= $token;
                $hasAnsi = true;

                continue;
            }

            $chars = mb_str_split($token, 1, 'UTF-8');
            foreach ($chars as $ch) {
                $cw = mb_strwidth($ch, 'UTF-8');
                if ($used + $cw > $targetWidth) {
                    $out .= '…';
                    if ($hasAnsi) {
                        $out .= "\e[0m";
                    }

                    return $out;
                }
                $out .= $ch;
                $used += $cw;
            }
        }

        if ($hasAnsi) {
            $out .= "\e[0m";
        }

        return $out;
    }

    public static function fit(string $text, int $width): string
    {
        if (self::width($text) > $width) {
            $text = self::truncate($text, $width);
        }

        return self::pad($text, $width);
    }

    public static function progressBar(float $percent, int $width, Theme $theme): string
    {
        $width = max(4, $width);
        $clamped = max(0.0, min(100.0, $percent));
        $filled = (int) round(($clamped / 100.0) * $width);
        $empty = max(0, $width - $filled);

        $bar = str_repeat('█', $filled).str_repeat('░', $empty);

        if ($clamped >= 100.0) {
            return $theme->success($bar);
        }
        if ($clamped > 0.0) {
            return $theme->accent($bar);
        }

        return $theme->muted($bar);
    }

    /**
     * Render a rounded UTF-8 box of exact $width x $height lines.
     *
     * @param  list<string>  $lines
     * @return list<string>
     */
    public static function box(
        string $title,
        array $lines,
        int $width,
        int $height,
        Theme $theme,
        bool $focused = false,
    ): array {
        $width = max(12, $width);
        $height = max(4, $height);
        $innerWidth = $width - 4;

        $titleText = $title !== '' ? " {$title} " : '';
        $titlePlainLen = mb_strwidth(self::strip($titleText), 'UTF-8');
        $dashFill = max(0, $width - 3 - $titlePlainLen);

        $top = $theme->border('╭─', $focused)
            .$theme->title($titleText, $focused)
            .$theme->border(str_repeat('─', $dashFill).'╮', $focused);

        $bodyHeight = $height - 2;
        $output = [$top];

        for ($i = 0; $i < $bodyHeight; $i++) {
            $rawLine = $lines[$i] ?? '';
            $fitted = self::fit($rawLine, $innerWidth);
            $output[] = $theme->border('│ ', $focused).$fitted.$theme->border(' │', $focused);
        }

        $bottom = $theme->border('╰'.str_repeat('─', $width - 2).'╯', $focused);
        $output[] = $bottom;

        return $output;
    }

    /**
     * Join two vertical line arrays side-by-side.
     *
     * @param  list<string>  $left
     * @param  list<string>  $right
     * @return list<string>
     */
    public static function joinColumns(array $left, array $right, string $gap = ' '): array
    {
        $count = max(count($left), count($right));
        $leftWidth = $left !== [] ? self::width($left[0]) : 0;
        $joined = [];

        for ($i = 0; $i < $count; $i++) {
            $l = $left[$i] ?? str_repeat(' ', $leftWidth);
            $r = $right[$i] ?? '';
            $joined[] = $l.$gap.$r;
        }

        return $joined;
    }
}
