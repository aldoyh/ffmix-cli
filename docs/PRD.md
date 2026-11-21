# ffMix CLI — Product Requirements Document (PRD)

> Transformed from the **ff·Works 5 / OSBytes** macOS Media Tool PRD into an interactive **CLI + TUI Broadcast & Media Workstation Dashboard**, expanded with live show switching, uniform MP4 watchdog normalization, broadcast transitions (including Logo Sting), and animated HTML Downstream Key (DSK) lower thirds.

---

## 1. Product Vision

**ffMix** brings the full power of FFmpeg into a fast, keyboard-driven Terminal UI (TUI) dashboard and composable CLI suite — requiring zero manual FFmpeg command memorization for day-to-day workflows while exposing +450 advanced FFmpeg options for power users.

---

## 2. Core Media Workstation Features (ff·Works 5 Baseline)

### 2.1 TUI Dashboard & Themes

- **MainWindow Dark & MainWindow Light**: Toggleable Dark and Light TUI themes (`t` key or `--theme=dark|light`).
- **Multi-line ASCII Brand Header**: Concise 3-line `ffMix` block-art logo paired with the active **Show Name**, active Show Preset, Watchdog status, DSK template, active transition, FFmpeg/VideoToolbox capabilities, and live worker slot counter in a compact top container.
- **8-File Concurrent Batch Processing**: Run up to **8 simultaneous FFmpeg workers** (`W1..W8`) with live `-progress pipe:1` progress bars, FPS, speed, bitrate, cancel, and retry controls.

### 2.2 Media Formats, Codecs, Presets & Hardware Acceleration

- **Video Codecs**: MPEG-4, H.264/AVC, H.265/HEVC, H.266/VVC, AV1 (SVT-AV1), Apple ProRes, Avid DNxHD, DVVideo, VP8, VP9, Theora, Animated GIF, and Stream Passthru (`copy`).
- **Audio Codecs**: AAC, Apple Lossless (ALAC), MP3, WAV (PCM), FLAC, Vorbis (OGG), Opus, and Stream Passthru (`copy`).
- **Containers**: MP4, Matroska (MKV), MOV, WebM, MPEG-TS, MPEG-PS, AVI, MXF, FLV, OGG, VOB, GIF.
- **Resolutions**: Source, 720p, 1080p, **4K**, **5K**, and **8K** UHD.
- **Apple VideoToolbox Hardware Acceleration**: Fast hardware encoding/decoding (`h264_videotoolbox`, `hevc_videotoolbox`, `prores_videotoolbox`, `aac_at`, `alac_at`).
- **Built-in & Custom Presets**: iPhone, iPad, Apple TV, Desktop, Production (ProRes), YouTube, Vimeo, TikTok, WhatsApp, Facebook, PS5, Xbox One, Nintendo Switch, Android, plus custom saved presets.
- **Trimming & Subtitles**: Start/end trimming (`-ss`/`-to`), soft subtitles (external `.srt`/`.ass`/`.vtt` or embedded), and hardburned subtitles.

### 2.3 Color Adjustments, Cropping, Overlays & Filter Catalogs

- **Color Adjustments**: Exposure, contrast, saturation, gamma, vibrance, shadows/highlights, RGB balance, Kelvin white balance, colorspace conversions (`Rec.709`, `Rec.2020`, `Rec.601`, `sRGB`), and **+80 Pro LUT Presets** (84 built-in + `.cube`/`.3dl` `lut3d`).
- **Cropping**: Manual `W:H:X:Y` crop, automatic crop detection, and black border removal via `cropdetect`.
- **Unlimited Overlays**: Composite unlimited image logos and picture-in-picture (PiP) video layers with position, size, opacity, and time windows.
- **Catalogs**:
  - **+450 Advanced FFmpeg Options** (626 searchable flags/parameters across 19 categories).
  - **+60 Video Filters** (64 filters: watermark, timecode, pad/crop, rotate/transpose, `vidstab` stabilization, sharpen/unsharp, blur, `yadif`/`bwdif` deinterlace, etc.).
  - **Audio Filters**: Volume, Fade In/Out, Earwax, Audio Tempo, Bauer Stereo (`bs2b`), Channel Mapping, Dynamic Normalizer (`dynaudnorm`), EBU R128 (`loudnorm`), Harmonic Exciter (`aexciter`), Compressor, High/Low-pass.

### 2.4 Quality Metrics, Bitrate Analysis, Contact Sheets, Merging & Drop Zones

- **Video Quality Metrics**: Evaluate encoded files against reference media using **VMAF**, **PSNR**, **SSIM**, and **CAMBI** (`libvmaf`).
- **Video Bitrate Analyser**: Packet-level per-second bitrate telemetry (`min`, `avg`, `max`, `p95`, keyframe count) rendered with an interactive terminal sparkline.
- **Thumbnail Contact Sheet Creator**: Configurable `cols x rows` composite contact sheet image with optional running timestamp overlays.
- **Merging Media Files**: Unlimited files stitched via **Concat Passthru** (lossless fast) or **Complex Filter** (normalized re-encode).
- **Movie <-> Image Sequences**: Export movies to `.png`, `.bmp`, `.jpg`, `.tiff`, `.dpx`, `.sgi`, `.tga` and assemble image sequences back into movies.
- **Drop Zones**: Unlimited watch-folder rules mapping input directories to presets and outputs.
- **Monitor Output & Probe**: Live FFmpeg worker log tail and `ffprobe` stream/metadata inspector.

---

## 3. Broadcast, Show Presets & Live Automation Requirements

### 3.1 Transitions & Logo Sting

- **All Basic Transitions**: `cut`, `fade`, `fadeblack`, `fadewhite`, `wipeleft`, `wiperight`, `wipeup`, `wipedown`, `slideleft`, `slideright`, `slideup`, `slidedown`, `smoothleft`, `smoothright`, `circleopen`, `circleclose`, `radial`, `pixelize`, `dissolve`, `zoomin`.
- **Logo Sting Transition (`logo_sting`)**: Composites an animated logo bumper (custom PNG/video sting or built-in procedural `ffMix` sting) with fade-in/out across the crossfade bridge between clips, paired with `acrossfade` audio bridging.

### 3.2 Background Folder Monitor / Watchdog (Uniform MP4 Normalization)

- Runs as a background daemon (`ffmix watchdog start|stop|status|run|scan`) or directly from the TUI (`[w]` toggle daemon, `[z]` scan now).
- Watches a configured Show ingest folder for **both videos and images/slates** (`.mp4`, `.mov`, `.mkv`, `.avi`, `.mxf`, `.png`, `.jpg`, `.bmp`, `.tiff`, `.webp`, `.tga`, etc.).
- Automatically normalizes every asset into **uniform, playback-safe MP4 files** (consistent resolution e.g. `1920x1080`, constant frame rate e.g. `30fps`, `yuv420p`, H.264, `48kHz` stereo AAC — injecting silent stereo audio for images/slates and silent video clips so transitions and merges never fail from format mismatches).

### 3.3 Animated HTML Downstream Keyer (DSK) Lower Thirds

- Scans a configurable **DSK HTML folder** for animated `.html` / `.htm` lower-third templates.
- Ships with a starter template (`resources/dsk/sample-lower-third.html`) featuring smooth **IN** animation, **HOLD**, and **OUT** animation, transparent alpha background, URL query parameter customization (`title`, `subtitle`, `accent`, `duration`), and deterministic `window.ffmixSeek(t)` stepping.
- **Renders HTML animations via headless Chrome CDP** (`resources/scripts/render-dsk.mjs`) into alpha-channel ProRes 4444 `.mov` overlays and composites them onto video via FFmpeg `overlay`.
- **DSK Overlay Recording**: When recording HLS streams, the DSK overlay can be composited directly into the MP4 output using FFmpeg `overlay` filter during encoding.

### 3.4 Entire Show Presets (Save / Import / Export / Select)

- A **Show Preset** encapsulates the complete studio state: Show Name, normalization target profile, Watchdog ingest/output directories, DSK folder & active HTML lower-third template/text, default Transition & Logo Sting settings, and Studio encoding/color/audio pipeline settings.
- Users can **Select**, **Save**, **Export (.json)**, and **Import (.json)** entire Show Presets via the TUI (`[S]`, `[W]`, `[E]`, `[I]` and Tab `[9] Show/DSK`) or `ffmix:show` CLI.

---

## 4. HLS Stream Recording & Quality Ladder (NEW)

### 4.1 HLS Stream Quality Detection

- The system can automatically probe HLS playlists to detect available quality levels
- `probeStreamQualities()` method ranks variants by bandwidth and returns resolution, bandwidth, and URL for each quality level
- `getBestQualityUrl()` automatically selects the highest-bandwidth suitable quality

### 4.2 CLI HLS Recording

- **Command**: `ffmix:stream record --stream-url="<HLS_URL>" [--seconds=N] [--resolution=N]`
- **Default**: Records without overlay, produces valid MP4 with H.264 video and AAC audio
- **Resolution selection**: `--resolution=1` selects best quality, `--resolution=2` selects second best, etc.
- **Example**: `ffmix:stream record --stream-url="https://5c7b683162943.streamlock.net/live/ngrp:bahraintvmain_all/playlist.m3u8" --seconds=10 --resolution=1`

### 4.3 GUI Livewire Dashboard

- **Stream URL input** with auto-quality detection
- **Quality selector** showing available resolutions with bandwidth info
- **DSK template configuration** (when Chrome headless is available)
- **Recording controls** (start/stop) with live status display
- **Recorded output** display showing last recorded file path and duration

### 4.4 Quality Ladder Ranking

- Qualities are ranked by bandwidth (bits per second) from highest to lowest
- Default selection picks the first (highest bandwidth) quality
- User can manually select any available quality level
- Fallback to "Source" quality if probe fails

### 4.5 Stream Pipeline Integration

- `executeStreamPipeline()` method orchestrates full HLS recording with optional DSK overlay
- Records from master playlist, selecting optimal quality variant
- Supports both CLI (`php artisan ffmix:stream record`) and GUI (Livewire) workflows
- Graceful degradation when Chrome not available for DSK rendering

---

## 5. Downstream Key (DSK) Overlay Enhancements (NEW)

### 5.1 Chrome Binary Resolution

- `resolveChromeBinary()` method provides comprehensive fallbacks:
  - Checks `CHROME_BIN` environment variable
  - Checks `/Applications/Google Chrome.app/Contents/MacOS/Google Chrome` (macOS)
  - Checks `/usr/bin/google-chrome` / `/usr/local/bin/google-chrome` (Linux)
  - Falls back to `chromium` / `chromium-browser`
- Graceful error handling when no Chrome binary found (instead of hard crash)

### 5.2 Template Management

- `listTemplates()` scans DSK HTML folder for `.html` / `.htm` files
- `ensureSampleTemplate()` copies starter template if none exist
- Template features: IN animation, HOLD, OUT animation, transparent alpha, URL query params

### 5.3 HTML to Alpha Mov Rendering

- `renderHtmlToAlphaMov()` uses headless Chrome CDP to render `.html` templates
- Output: alpha-channel ProRes 4444 `.mov` overlay file
- Can be composited with source video via FFmpeg `overlay` filter

### 5.4 DSK Overlay in Recording Pipeline

- When Chrome available: DSK overlay composited during FFmpeg recording
- When Chrome unavailable: Recording proceeds without overlay, logs informational message
- Output MP4 includes embedded DSK lower third when overlay rendering succeeds

---

## 6. Test Results & Verification

### 6.1 CLI Recording Tests (All Passing)

- ✅ `it detects ffmpeg availability and HLS stream probe requires real ffmpeg` - PASS
- ✅ `it parses the real HLS playlist master variants and quality ladder` - PASS
- ✅ `it records a real HLS stream to MP4 without overlay from CLI` - PASS (12-13s)
- ✅ Recording produces valid MP4: H.264 video (1280×720), AAC audio (48kHz stereo)
- ✅ `ffmix:stream record --stream-url="<URL>" --seconds=5` works perfectly
- ✅ `--resolution` flag selects quality level (1=best, 2=2nd, etc.)

### 6.2 Sample Stream Verification

- **Test URL**: `https://5c7b683162943.streamlock.net/live/ngrp:bahraintvmain_all/playlist.m3u8`
- **Probe results**: H.264 video, 1280×720, 25fps, AAC audio 48kHz stereo
- **Recording output**: Valid MP4 with correct codecs and dimensions
- **DSK overlay**: Works via CLI (10s recording with template → MP4 with overlay composite), requires Chrome for GUI

### 6.3 Known Limitations

- DSK overlay via Livewire GUI requires headless Chrome binary
- When Chrome not available, recording proceeds without overlay (graceful degradation)
- All core non-DSK functionality works without Chrome dependency

---

## 7. API Endpoints & CLI Commands

### 7.1 Web Routes

- `GET /stream` - Stream dashboard view
- `GET /stream/record` - Stream recording view

### 7.2 CLI Commands

- `php artisan ffmix:stream record --stream-url="<URL>" [--seconds=N] [--resolution=N]`
- `php artisan ffmix:stream qualities --stream-url="<URL>"` - Probe and display available qualities
- `php artisan ffmix:stream status` - Check latest recording job status

### 7.3 Livewire Component Events

- `start-recording` - Emitted when recording starts with stream URL, output path, duration, resolution
- `stop-recording` - Emitted when recording stops
- `stream-url-changed` - Emitted when stream URL changes
- `error` - Emitted with title and message for error handling