<?php

namespace App\Services\Ffmix;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class TransitionService
{
    public function __construct(private MediaProbeService $probe) {}

    /**
     * List all available transitions (basic + Logo Sting).
     *
     * @return array<string, array{label: string, xfade: ?string, description: string}>
     */
    public function all(): array
    {
        /** @var array<string, array{label: string, xfade: ?string, description: string}> $transitions */
        $transitions = config('ffmix.transitions', []);

        return $transitions;
    }

    /**
     * Build FFmpeg command to join two or more clips with a transition (including Logo Sting).
     *
     * @param  list<string>  $inputs
     * @param  list<float>|null  $durationsSeconds
     * @return list<string>
     */
    public function buildCommand(
        array $inputs,
        string $outputPath,
        string $transition = 'fade',
        float $duration = 1.0,
        int $width = 1920,
        int $height = 1080,
        int $fps = 30,
        ?string $stingAssetPath = null,
        ?array $durationsSeconds = null,
    ): array {
        if (count($inputs) < 2) {
            throw new RuntimeException('At least 2 clips are required to apply a transition.');
        }

        $catalog = $this->all();
        if (! isset($catalog[$transition])) {
            throw new RuntimeException("Unknown transition '{$transition}'.");
        }

        $ffmpegBin = (string) config('ffmix.binaries.ffmpeg', 'ffmpeg');
        $args = [$ffmpegBin, '-hide_banner', '-y'];

        foreach ($inputs as $path) {
            $args[] = '-i';
            $args[] = $path;
        }

        $isLogoSting = $transition === 'logo_sting';
        $hasCustomSting = $isLogoSting && $stingAssetPath !== null && $stingAssetPath !== '';
        $stingInputIndex = count($inputs);

        if ($hasCustomSting) {
            $ext = strtolower(pathinfo((string) $stingAssetPath, PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'bmp'], true)) {
                $args[] = '-loop';
                $args[] = '1';
                $args[] = '-t';
                $args[] = (string) max(0.5, $duration);
            }
            $args[] = '-i';
            $args[] = (string) $stingAssetPath;
        }

        $clipDurations = [];
        foreach ($inputs as $idx => $path) {
            if ($durationsSeconds !== null && isset($durationsSeconds[$idx])) {
                $clipDurations[] = max(0.5, $durationsSeconds[$idx]);
            } else {
                $clipDurations[] = $this->probeDuration($path);
            }
        }

        $filterParts = [];
        foreach ($inputs as $idx => $unused) {
            $filterParts[] = "[{$idx}:v]scale={$width}:{$height}:force_original_aspect_ratio=decrease,pad={$width}:{$height}:(ow-iw)/2:(oh-ih)/2,setsar=1,fps={$fps},settb=AVTB,format=yuv420p[v{$idx}]";
            $filterParts[] = "[{$idx}:a]aformat=sample_rates=48000:channel_layouts=stereo[a{$idx}]";
        }

        if ($transition === 'cut') {
            $concatIn = '';
            foreach ($inputs as $idx => $unused) {
                $concatIn .= "[v{$idx}][a{$idx}]";
            }
            $n = count($inputs);
            $filterParts[] = "{$concatIn}concat=n={$n}:v=1:a=1[vout][aout]";
        } else {
            $xfadeType = $catalog[$transition]['xfade'] ?? 'fade';
            $safeDuration = max(0.2, min($duration, min($clipDurations) * 0.45));

            $currentV = 'v0';
            $currentA = 'a0';
            $cumulativeDuration = $clipDurations[0];
            $stingWindows = [];

            for ($i = 1; $i < count($inputs); $i++) {
                $offset = max(0.0, round($cumulativeDuration - $safeDuration, 3));
                $stingWindows[] = ['start' => $offset, 'end' => round($offset + $safeDuration, 3)];

                $isLast = ($i === count($inputs) - 1);
                $nextV = ($isLast && ! $isLogoSting) ? 'vout' : "vx{$i}";
                $nextA = $isLast ? 'aout' : "ax{$i}";

                $filterParts[] = "[{$currentV}][v{$i}]xfade=transition={$xfadeType}:duration={$safeDuration}:offset={$offset}[{$nextV}]";
                $filterParts[] = "[{$currentA}][a{$i}]acrossfade=d={$safeDuration}[{$nextA}]";

                $currentV = $nextV;
                $currentA = $nextA;
                $cumulativeDuration = $offset + $clipDurations[$i];
            }

            if ($isLogoSting) {
                if ($hasCustomSting) {
                    $fadeSlice = round($safeDuration * 0.28, 3);
                    $fadeOutStart = round(max(0.1, $safeDuration - $fadeSlice), 3);
                    $stingW = (int) round($width * 0.32);

                    $filterParts[] = "[{$stingInputIndex}:v]format=rgba,scale={$stingW}:-1,fade=t=in:st=0:d={$fadeSlice}:alpha=1,fade=t=out:st={$fadeOutStart}:d={$fadeSlice}:alpha=1[sting]";

                    foreach ($stingWindows as $wIdx => $win) {
                        $outLabel = ($wIdx === count($stingWindows) - 1) ? 'vout' : "vs{$wIdx}";
                        $filterParts[] = "[{$currentV}][sting]overlay=(W-w)/2:(H-h)/2:enable='between(t,{$win['start']},{$win['end']})'[{$outLabel}]";
                        $currentV = $outLabel;
                    }
                } else {
                    // Built-in procedural ffMix Logo Sting banner when no external sting image/clip is given
                    $stingFilter = $currentV;
                    foreach ($stingWindows as $wIdx => $win) {
                        $outLabel = ($wIdx === count($stingWindows) - 1) ? 'vout' : "vs{$wIdx}";
                        $boxH = (int) round($height * 0.14);
                        $boxY = (int) round(($height - $boxH) / 2);
                        $fontSize = max(28, (int) round($height * 0.055));
                        $enable = "enable='between(t,{$win['start']},{$win['end']})'";

                        $filterParts[] = "[{$stingFilter}]drawbox=x=0:y={$boxY}:w=iw:h={$boxH}:color=0x0f172a@0.86:t=fill:{$enable},drawtext=text='ffMix':x=(w-text_w)/2:y=(h-text_h)/2:fontsize={$fontSize}:fontcolor=0x38bdf8:{$enable}[{$outLabel}]";
                        $stingFilter = $outLabel;
                    }
                }
            }
        }

        $args[] = '-filter_complex';
        $args[] = implode(';', $filterParts);
        $args[] = '-map';
        $args[] = '[vout]';
        $args[] = '-map';
        $args[] = '[aout]';
        $args[] = '-c:v';
        $args[] = 'libx264';
        $args[] = '-pix_fmt';
        $args[] = 'yuv420p';
        $args[] = '-crf';
        $args[] = '20';
        $args[] = '-c:a';
        $args[] = 'aac';
        $args[] = '-b:a';
        $args[] = '192k';
        $args[] = '-movflags';
        $args[] = '+faststart';
        $args[] = $outputPath;

        return $args;
    }

    /**
     * @param  list<string>  $inputs
     * @return array{output: string, transition: string, duration: float, command: string}
     */
    public function apply(
        array $inputs,
        string $outputPath,
        string $transition = 'fade',
        float $duration = 1.0,
        ?string $stingAssetPath = null,
    ): array {
        $args = $this->buildCommand($inputs, $outputPath, $transition, $duration, stingAssetPath: $stingAssetPath);
        $result = Process::timeout(600)->run($args);

        if (! $result->successful()) {
            throw new RuntimeException('Transition render failed: '.trim($result->errorOutput() ?: $result->output()));
        }

        return [
            'output' => $outputPath,
            'transition' => $transition,
            'duration' => $duration,
            'command' => implode(' ', array_map('escapeshellarg', $args)),
        ];
    }

    private function probeDuration(string $path): float
    {
        if (! is_file($path)) {
            return 5.0;
        }

        try {
            $info = $this->probe->probe($path);

            return max(1.0, $info['duration']);
        } catch (\Throwable) {
            return 5.0;
        }
    }
}
