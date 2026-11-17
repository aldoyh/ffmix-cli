# ffMix CLI — Broadcast & Media Workstation TUI

```text
╭─ ffMix Broadcast & Media Studio ────────────────────────────────────────────────────────────╮
│ ▄▀▀ ▄▀▀ █▄ ▄█ ▀ ▀▄▀ │ SHOW: Main Broadcast Live          FFmpeg 9.0.2 │ VT:ON │ Workers:0/8 │
│ █▀  █▀  █ ▀ █ █  █  │ Preset: main-broadcast (1080p30)   Watchdog:IDLE (0 MP4) │ Theme:Dark │
│ ▀   ▀   ▀   ▀ ▀ ▄▀▄ │ DSK: sample-lower-third.html │ Trans: logo_sting │ [S] Shows [W] Save │
╰─────────────────────────────────────────────────────────────────────────────────────────────╯
```

**ffMix** is a Laravel 13 / PHP 8.5 CLI and interactive Terminal UI (TUI) workstation built around local `ffmpeg` and `ffprobe`, implementing the full **ff·Works 5** feature set alongside live show presets, background MP4 normalization watchdog, broadcast transitions (including **Logo Sting**), and **animated HTML Downstream Key (DSK)** lower thirds.

See [docs/PRD.md](docs/PRD.md) for the complete Product Requirements Document.

---

## Quick Start

```bash
# Launch the interactive TUI Dashboard (with optional demo data or light theme)
./ffmix
./ffmix --demo
./ffmix --theme=light

# Render a non-interactive snapshot of any tab
./ffmix --snapshot --tab=show --demo
```

---

## TUI Dashboard Tabs & Shortcuts

| Key | Tab / Action | Capabilities |
| --- | --- | --- |
| `1` | **Queue (8x)** | Concurrent batch queue up to 8 workers (`W1..W8`), live FPS/speed/bitrate/progress |
| `2` | **Studio** | Codecs (H.264/H.265/H.266/AV1/ProRes/DNxHD/VP9/GIF), 4K/5K/8K, VideoToolbox HW, trim, subtitles, 14 presets |
| `3` | **FX / LUT** | Exposure, contrast, saturation, gamma, vibrance, RGB/temp, colorspace, 84 Pro LUTs (`l`), Auto-Crop (`c`), Unlimited Overlays (`o`), 64 Video Filters |
| `4` | **Audio** | Volume, fade in/out, earwax, tempo, Bauer stereo (`bs2b`), channel map, `dynaudnorm`, EBU R128 `loudnorm`, exciter |
| `5` | **Analyzer** | Quality Metrics (**VMAF, PSNR, SSIM, CAMBI**) (`v`) & Packet **Bitrate Analyser** with sparklines (`b`) |
| `6` | **Tools** | Contact Sheet creator, Merge Media (`passthru`/`complex`), Transitions + Logo Sting (`T`), Movie <-> Images |
| `7` | **DropZones** | Unlimited watch-folder Drop Zones (`n` create, `s` scan, `Space` toggle) |
| `8` | **Monitor** | Live FFmpeg worker output log tail + `ffprobe` stream inspector (`i`) |
| `9` | **Show/DSK** | Show Name, Watchdog normalization (`w` daemon, `z` scan now), Transitions (`T`), HTML DSK templates |

### Global Hotkeys
- `t`: Toggle **Dark / Light** theme
- `S` / `W` / `I` / `E`: **Select** / **Save** / **Import JSON** / **Export JSON** entire Show Presets
- `w` / `z`: Start/Stop **Background Folder Watchdog** / Run immediate scan & MP4 normalization
- `T`: Pick Transition (`cut`, `fade`, `fadeblack`, `fadewhite`, wipes, slides, iris, radial, `logo_sting`)
- `p` / `h` / `/`: Preset picker / Toggle Apple VideoToolbox HW / Search **+450 Advanced FFmpeg Options**
- `?` / `q`: Help modal / Quit cleanly

---

## CLI Subcommands

All features are also scriptable headlessly via `./ffmix <subcommand>` or `php artisan ffmix:<subcommand>`:

### 1. Show Presets (Save / Import / Export / Select)
```bash
./ffmix show list
./ffmix show select main-broadcast
./ffmix show save "Evening News Live"
./ffmix show export storage/app/ffmix/shows/evening-news.json
./ffmix show import storage/app/ffmix/shows/evening-news.json
```

### 2. Background Folder Watchdog (Videos + Images/Slates -> Uniform MP4)
Watches the active Show's ingest directory and normalizes every incoming video and image/slate (`.png`, `.jpg`, `.bmp`, `.tiff`, etc.) into uniform H.264 + AAC 48kHz stereo MP4s so transitions and merges never fail due to mismatched codecs, resolutions, frame rates, or missing audio tracks.
```bash
./ffmix watchdog scan             # One-shot scan & normalize
./ffmix watchdog start            # Start background daemon (writes PID & logs)
./ffmix watchdog status           # Check background daemon status
./ffmix watchdog stop             # Stop background daemon
./ffmix watchdog list             # List normalized MP4 assets
```

### 3. Animated HTML Downstream Key (DSK) Lower Thirds
Scans a folder of `.html` overlays (seeded with `resources/dsk/sample-lower-third.html` featuring smooth In/Hold/Out animation and transparent alpha background) and composites them onto video using headless Chrome CDP + FFmpeg ProRes 4444 alpha overlay.
```bash
./ffmix dsk init-sample
./ffmix dsk list
./ffmix dsk render --template=sample-lower-third.html --title="Jane Doe" --subtitle="Keynote Speaker" --output=lower_third.mov
./ffmix dsk apply --video=normalized.mp4 --output=with_dsk.mp4 --title="Live Update" --start=1.5 --duration=6.0
```

### 4. Transitions & Logo Sting
Supports 20 standard `xfade` transitions (`cut`, `fade`, `fadeblack`, `fadewhite`, `wipeleft`, `wiperight`, `wipeup`, `wipedown`, `slideleft`, `slideright`, `slideup`, `slidedown`, `smoothleft`, `smoothright`, `circleopen`, `circleclose`, `radial`, `pixelize`, `dissolve`, `zoomin`) plus **`logo_sting`**.
```bash
./ffmix transition --list
./ffmix transition clip1.mp4 clip2.mp4 --transition=logo_sting --duration=1.2 --sting=logo.png --output=stitched.mp4
```

### 5. Transcoding, Probing, Quality & Utilities
```bash
./ffmix probe input.mov --json
./ffmix convert input.mov out.mp4 --preset=youtube --lut=teal-and-orange --autocrop --hw
./ffmix bitrate input.mp4
./ffmix quality reference.mp4 encoded.mp4
./ffmix contact-sheet input.mp4 sheet.png --cols=4 --rows=4
./ffmix merge part1.mp4 part2.mp4 --mode=passthru --output=merged.mp4
./ffmix images to-images movie.mp4 frames/f_%04d.png --format=png --fps=24
./ffmix images to-movie frames/f_%04d.png out.mp4 --fps=24
./ffmix catalog options --search=videotoolbox
```

---

## Testing & Quality Checks

```bash
composer test   # Runs Laravel Pint, PHPStan (level 7), and Pest test suite
```
