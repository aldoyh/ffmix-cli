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
- Renders HTML animations via headless Chrome CDP (`resources/scripts/render-dsk.mjs`) into alpha-channel ProRes 4444 `.mov` overlays and composites them onto video via FFmpeg `overlay`.

### 3.4 Entire Show Presets (Save / Import / Export / Select)
- A **Show Preset** encapsulates the complete studio state: Show Name, normalization target profile, Watchdog ingest/output directories, DSK folder & active HTML lower-third template/text, default Transition & Logo Sting settings, and Studio encoding/color/audio pipeline settings.
- Users can **Select**, **Save**, **Export (.json)**, and **Import (.json)** entire Show Presets via the TUI (`[S]`, `[W]`, `[E]`, `[I]` and Tab `[9] Show/DSK`) or `ffmix:show` CLI.
