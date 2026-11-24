<?php

return [
    /*
    |--------------------------------------------------------------------------
    | FFmpeg & FFprobe Binaries
    |--------------------------------------------------------------------------
    */
    'binaries' => [
        'ffmpeg' => env('FFMIX_FFMPEG_BIN', 'ffmpeg'),
        'ffprobe' => env('FFMIX_FFPROBE_BIN', 'ffprobe'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sample Media Catalog
    |--------------------------------------------------------------------------
    */
    'sample_media_root' => env('FFMIX_SAMPLE_MEDIA_ROOT', rtrim((string) getenv('HOME'), '/').'/Movies'),
    'sample_media' => [
        [
            'label' => 'Bahrain - Manama',
            'path' => 'Bahrain - Manama [vPwtOiUXxTw].mp4',
            'kind' => 'video',
        ],
        [
            'label' => 'Ours. Yours. Bahrain',
            'path' => 'Ours. Yours. Bahrain [15BefRzSQ6I].mp4',
            'kind' => 'video',
        ],
        [
            'label' => 'Bahrain Folklore Music 1',
            'path' => 'BahrainFolkloreMusic1.mp3',
            'kind' => 'audio',
        ],
        [
            'label' => 'Bahrain Folklore Music 2',
            'path' => 'BahrainFolkloreMusic2.mp3',
            'kind' => 'audio',
        ],
        [
            'label' => 'Bahrain National Anthem',
            'path' => 'Bahrain_National_Anthem_2023.mp3',
            'kind' => 'audio',
        ],
        [
            'label' => 'Saeed Sample Enhanced',
            'path' => 'SaeedSample-enhanced-v2.wav',
            'kind' => 'audio',
        ],
        [
            'label' => 'Bahrain TV Live',
            'url' => 'https://5c7b683162943.streamlock.net/live/ngrp:bahraintvmain_all/playlist.m3u8',
            'kind' => 'hls',
        ],
        [
            'label' => 'Radio 98.4 Live',
            'url' => 'https://5c7b683162943.streamlock.net/live/ngrp:radio-98-4_all/chunklist_w1027272218_b981072.m3u8',
            'kind' => 'audio-hls',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Concurrency & TUI Defaults
    |--------------------------------------------------------------------------
    */
    'max_workers' => (int) env('FFMIX_MAX_WORKERS', 8),
    'default_theme' => env('FFMIX_THEME', 'dark'),

    /*
    |--------------------------------------------------------------------------
    | Supported Video & Audio Codecs and Containers
    |--------------------------------------------------------------------------
    */
    'video_codecs' => [
        'h264' => ['label' => 'H.264 / AVC', 'sw' => 'libx264', 'hw' => 'h264_videotoolbox'],
        'h265' => ['label' => 'H.265 / HEVC', 'sw' => 'libx265', 'hw' => 'hevc_videotoolbox'],
        'h266' => ['label' => 'H.266 / VVC', 'sw' => 'libvvenc', 'hw' => null],
        'av1' => ['label' => 'AV1 (SVT-AV1)', 'sw' => 'libsvtav1', 'hw' => null],
        'prores' => ['label' => 'Apple ProRes', 'sw' => 'prores_ks', 'hw' => 'prores_videotoolbox'],
        'dnxhd' => ['label' => 'Avid DNxHD', 'sw' => 'dnxhd', 'hw' => null],
        'dvvideo' => ['label' => 'DV Video', 'sw' => 'dvvideo', 'hw' => null],
        'mpeg4' => ['label' => 'MPEG-4 Part 2', 'sw' => 'mpeg4', 'hw' => null],
        'vp8' => ['label' => 'VP8', 'sw' => 'libvpx', 'hw' => null],
        'vp9' => ['label' => 'VP9', 'sw' => 'libvpx-vp9', 'hw' => null],
        'theora' => ['label' => 'Theora', 'sw' => 'libtheora', 'hw' => null],
        'gif' => ['label' => 'Animated GIF', 'sw' => 'gif', 'hw' => null],
        'copy' => ['label' => 'Stream Passthru (Copy)', 'sw' => 'copy', 'hw' => null],
    ],

    'audio_codecs' => [
        'aac' => ['label' => 'AAC', 'sw' => 'aac', 'hw' => 'aac_at'],
        'alac' => ['label' => 'Apple Lossless (ALAC)', 'sw' => 'alac', 'hw' => 'alac_at'],
        'mp3' => ['label' => 'MP3 (LAME)', 'sw' => 'libmp3lame', 'hw' => null],
        'flac' => ['label' => 'FLAC', 'sw' => 'flac', 'hw' => null],
        'wav' => ['label' => 'PCM WAV (16-bit)', 'sw' => 'pcm_s16le', 'hw' => null],
        'vorbis' => ['label' => 'Vorbis (OGG)', 'sw' => 'libvorbis', 'hw' => null],
        'opus' => ['label' => 'Opus', 'sw' => 'libopus', 'hw' => null],
        'copy' => ['label' => 'Stream Passthru (Copy)', 'sw' => 'copy', 'hw' => null],
    ],

    'containers' => [
        'mp4', 'mkv', 'mov', 'webm', 'ts', 'mpg', 'avi', 'mxf', 'flv', 'ogg', 'vob', 'gif', 'mp3', 'wav', 'flac', 'm4a',
    ],

    'image_formats' => [
        'png', 'bmp', 'jpg', 'tiff', 'dpx', 'sgi', 'tga',
    ],

    'colorspaces' => [
        'auto' => ['label' => 'Preserve Source', 'filter' => null],
        'bt709' => ['label' => 'Rec.709 (HD)', 'filter' => 'colorspace=all=bt709:iall=bt709:fast=1'],
        'bt2020' => ['label' => 'Rec.2020 (UHD/HDR)', 'filter' => 'colorspace=all=bt2020:iall=bt709:fast=1'],
        'bt601' => ['label' => 'Rec.601 (SD)', 'filter' => 'colorspace=all=smpte170m:iall=bt709:fast=1'],
        'srgb' => ['label' => 'sRGB (Web)', 'filter' => 'colorspace=all=bt709:trc=iec61966-2-1:fast=1'],
    ],

    'resolutions' => [
        'source' => ['label' => 'Source', 'width' => null, 'height' => null],
        '720p' => ['label' => '720p HD', 'width' => 1280, 'height' => 720],
        '1080p' => ['label' => '1080p Full HD', 'width' => 1920, 'height' => 1080],
        '4k' => ['label' => '4K UHD', 'width' => 3840, 'height' => 2160],
        '5k' => ['label' => '5K Retina', 'width' => 5120, 'height' => 2880],
        '8k' => ['label' => '8K UHD', 'width' => 7680, 'height' => 4320],
    ],

    /*
    |--------------------------------------------------------------------------
    | Built-in Presets (matching ffWorks 5 target devices & platforms)
    |--------------------------------------------------------------------------
    */
    'builtin_presets' => [
        [
            'slug' => 'iphone',
            'name' => 'iPhone (HEVC HW)',
            'category' => 'Apple Devices',
            'description' => '1080p HEVC hardware accelerated via VideoToolbox + AAC stereo',
            'config' => [
                'container' => 'mp4',
                'video_codec' => 'h265',
                'audio_codec' => 'aac',
                'hw_accel' => true,
                'resolution' => '1080p',
                'crf' => 23,
                'audio_bitrate' => '192k',
            ],
        ],
        [
            'slug' => 'ipad',
            'name' => 'iPad (H.264 HW)',
            'category' => 'Apple Devices',
            'description' => '1080p H.264 VideoToolbox + AAC for universal iPad playback',
            'config' => [
                'container' => 'mp4',
                'video_codec' => 'h264',
                'audio_codec' => 'aac',
                'hw_accel' => true,
                'resolution' => '1080p',
                'crf' => 21,
                'audio_bitrate' => '192k',
            ],
        ],
        [
            'slug' => 'apple-tv',
            'name' => 'Apple TV 4K',
            'category' => 'Apple Devices',
            'description' => '4K UHD HEVC VideoToolbox Rec.709 + high-bitrate AAC',
            'config' => [
                'container' => 'mp4',
                'video_codec' => 'h265',
                'audio_codec' => 'aac',
                'hw_accel' => true,
                'resolution' => '4k',
                'crf' => 20,
                'audio_bitrate' => '256k',
                'colorspace' => 'bt709',
            ],
        ],
        [
            'slug' => 'desktop',
            'name' => 'Desktop Universal',
            'category' => 'General',
            'description' => 'Balanced H.264 MP4 with faststart for macOS/Windows/Linux',
            'config' => [
                'container' => 'mp4',
                'video_codec' => 'h264',
                'audio_codec' => 'aac',
                'hw_accel' => false,
                'resolution' => 'source',
                'crf' => 20,
                'audio_bitrate' => '192k',
            ],
        ],
        [
            'slug' => 'production',
            'name' => 'Production ProRes 422 HQ',
            'category' => 'Production',
            'description' => 'Apple ProRes 422 HQ in MOV container with uncompressed PCM WAV audio',
            'config' => [
                'container' => 'mov',
                'video_codec' => 'prores',
                'audio_codec' => 'wav',
                'hw_accel' => true,
                'resolution' => 'source',
                'prores_profile' => 3,
            ],
        ],
        [
            'slug' => 'youtube',
            'name' => 'YouTube 4K Mastering',
            'category' => 'Web & Social',
            'description' => '4K H.264 High Profile + EBU R128 -14 LUFS loudness normalization',
            'config' => [
                'container' => 'mp4',
                'video_codec' => 'h264',
                'audio_codec' => 'aac',
                'hw_accel' => false,
                'resolution' => '4k',
                'crf' => 18,
                'audio_bitrate' => '320k',
                'audio_filters' => ['loudnorm' => true],
            ],
        ],
        [
            'slug' => 'vimeo',
            'name' => 'Vimeo Staff Pick HQ',
            'category' => 'Web & Social',
            'description' => 'High-fidelity H.264 1080p/4K with Rec.709 color preservation',
            'config' => [
                'container' => 'mp4',
                'video_codec' => 'h264',
                'audio_codec' => 'aac',
                'hw_accel' => false,
                'resolution' => 'source',
                'crf' => 17,
                'audio_bitrate' => '320k',
                'colorspace' => 'bt709',
            ],
        ],
        [
            'slug' => 'tiktok',
            'name' => 'TikTok Vertical (1080p)',
            'category' => 'Web & Social',
            'description' => 'H.264 1080p MP4 normalized to -14 LUFS for short-form social feeds',
            'config' => [
                'container' => 'mp4',
                'video_codec' => 'h264',
                'audio_codec' => 'aac',
                'hw_accel' => true,
                'resolution' => '1080p',
                'crf' => 20,
                'audio_bitrate' => '192k',
                'audio_filters' => ['loudnorm' => true],
            ],
        ],
        [
            'slug' => 'whatsapp',
            'name' => 'WhatsApp Compact',
            'category' => 'Web & Social',
            'description' => '720p H.264 compact bitrate for fast mobile messaging delivery',
            'config' => [
                'container' => 'mp4',
                'video_codec' => 'h264',
                'audio_codec' => 'aac',
                'hw_accel' => false,
                'resolution' => '720p',
                'crf' => 26,
                'audio_bitrate' => '128k',
            ],
        ],
        [
            'slug' => 'facebook',
            'name' => 'Facebook HD',
            'category' => 'Web & Social',
            'description' => '1080p H.264 MP4 with stereo AAC 192k',
            'config' => [
                'container' => 'mp4',
                'video_codec' => 'h264',
                'audio_codec' => 'aac',
                'hw_accel' => false,
                'resolution' => '1080p',
                'crf' => 21,
                'audio_bitrate' => '192k',
            ],
        ],
        [
            'slug' => 'ps5',
            'name' => 'PlayStation 5',
            'category' => 'Consoles',
            'description' => '4K H.265 HEVC MKV/MP4 compatible with PS5 Media Gallery',
            'config' => [
                'container' => 'mp4',
                'video_codec' => 'h265',
                'audio_codec' => 'aac',
                'hw_accel' => true,
                'resolution' => '4k',
                'crf' => 20,
                'audio_bitrate' => '256k',
            ],
        ],
        [
            'slug' => 'xbox-one',
            'name' => 'Xbox One / Series',
            'category' => 'Consoles',
            'description' => '1080p H.264 MP4 tuned for Xbox media playback',
            'config' => [
                'container' => 'mp4',
                'video_codec' => 'h264',
                'audio_codec' => 'aac',
                'hw_accel' => false,
                'resolution' => '1080p',
                'crf' => 21,
                'audio_bitrate' => '192k',
            ],
        ],
        [
            'slug' => 'nintendo-switch',
            'name' => 'Nintendo Switch',
            'category' => 'Consoles',
            'description' => '720p H.264 Baseline/Main profile for portable console compatibility',
            'config' => [
                'container' => 'mp4',
                'video_codec' => 'h264',
                'audio_codec' => 'aac',
                'hw_accel' => false,
                'resolution' => '720p',
                'crf' => 22,
                'audio_bitrate' => '160k',
            ],
        ],
        [
            'slug' => 'android',
            'name' => 'Android Universal',
            'category' => 'Mobile',
            'description' => '1080p H.264 / VP9 friendly mobile export',
            'config' => [
                'container' => 'mp4',
                'video_codec' => 'h264',
                'audio_codec' => 'aac',
                'hw_accel' => false,
                'resolution' => '1080p',
                'crf' => 22,
                'audio_bitrate' => '192k',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Broadcast Transitions (Basic xfade transitions + Logo Sting)
    |--------------------------------------------------------------------------
    */
    'transitions' => [
        'cut' => ['label' => 'Hard Cut', 'xfade' => null, 'description' => 'Instant zero-frame cut between clips'],
        'fade' => ['label' => 'Crossfade / Dissolve', 'xfade' => 'fade', 'description' => 'Smooth opacity cross-dissolve'],
        'fadeblack' => ['label' => 'Dip to Black', 'xfade' => 'fadeblack', 'description' => 'Fade out to black then fade in'],
        'fadewhite' => ['label' => 'Dip to White', 'xfade' => 'fadewhite', 'description' => 'Fade out to white flash then fade in'],
        'wipeleft' => ['label' => 'Wipe Left', 'xfade' => 'wipeleft', 'description' => 'Horizontal wipe from right to left'],
        'wiperight' => ['label' => 'Wipe Right', 'xfade' => 'wiperight', 'description' => 'Horizontal wipe from left to right'],
        'wipeup' => ['label' => 'Wipe Up', 'xfade' => 'wipeup', 'description' => 'Vertical wipe upward'],
        'wipedown' => ['label' => 'Wipe Down', 'xfade' => 'wipedown', 'description' => 'Vertical wipe downward'],
        'slideleft' => ['label' => 'Slide Left', 'xfade' => 'slideleft', 'description' => 'Incoming clip pushes outgoing clip left'],
        'slideright' => ['label' => 'Slide Right', 'xfade' => 'slideright', 'description' => 'Incoming clip pushes outgoing clip right'],
        'slideup' => ['label' => 'Slide Up', 'xfade' => 'slideup', 'description' => 'Incoming clip slides upward'],
        'slidedown' => ['label' => 'Slide Down', 'xfade' => 'slidedown', 'description' => 'Incoming clip slides downward'],
        'smoothleft' => ['label' => 'Smooth Left', 'xfade' => 'smoothleft', 'description' => 'Feathered smooth edge wipe left'],
        'smoothright' => ['label' => 'Smooth Right', 'xfade' => 'smoothright', 'description' => 'Feathered smooth edge wipe right'],
        'circleopen' => ['label' => 'Circle Open (Iris)', 'xfade' => 'circleopen', 'description' => 'Circular iris reveal from center'],
        'circleclose' => ['label' => 'Circle Close (Iris)', 'xfade' => 'circleclose', 'description' => 'Circular iris close to center'],
        'radial' => ['label' => 'Radial Clock Wipe', 'xfade' => 'radial', 'description' => 'Clockwise radial sweep'],
        'pixelize' => ['label' => 'Pixelize', 'xfade' => 'pixelize', 'description' => 'Digital mosaic pixelize dissolve'],
        'dissolve' => ['label' => 'Random Dissolve', 'xfade' => 'dissolve', 'description' => 'Randomized pixel dissolve'],
        'zoomin' => ['label' => 'Zoom In', 'xfade' => 'zoomin', 'description' => 'Dynamic zoom-in transition'],
        'logo_sting' => ['label' => 'Logo Sting', 'xfade' => 'fade', 'description' => 'Animated Logo Sting bumper composited across the cut point'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Background Folder Watchdog & Media Normalization Defaults
    |--------------------------------------------------------------------------
    */
    'watchdog' => [
        'poll_seconds' => 3,
        'slate_duration' => 5.0,
        'target_width' => 1920,
        'target_height' => 1080,
        'target_fps' => 30,
        'video_extensions' => ['mp4', 'mov', 'mkv', 'avi', 'mxf', 'ts', 'webm', 'flv', 'm4v', 'mpg'],
        'image_extensions' => ['png', 'jpg', 'jpeg', 'bmp', 'tiff', 'tif', 'webp', 'tga'],
    ],

    /*
    |--------------------------------------------------------------------------
    | HTML Downstream Keyer (DSK) Defaults
    |--------------------------------------------------------------------------
    */
    'dsk' => [
        'default_duration' => 6.0,
        'default_fps' => 30,
        'default_width' => 1920,
        'default_height' => 1080,
    ],

    /*
    |--------------------------------------------------------------------------
    | Built-in Show Presets
    |--------------------------------------------------------------------------
    */
    'builtin_show_presets' => [
        [
            'slug' => 'main-broadcast',
            'name' => 'Main Broadcast Live',
            'description' => '1080p30 normalized MP4 ingest, Logo Sting transitions, and HTML lower-third DSK',
            'is_active' => true,
            'config' => [
                'show_name' => 'Main Broadcast Live',
                'normalize' => [
                    'width' => 1920,
                    'height' => 1080,
                    'fps' => 30,
                    'video_codec' => 'h264',
                    'audio_codec' => 'aac',
                    'audio_bitrate' => '192k',
                    'hw_accel' => false,
                    'slate_duration' => 5.0,
                ],
                'watchdog' => [
                    'enabled' => false,
                    'input_dir' => 'storage/app/ffmix/ingest',
                    'normalized_dir' => 'storage/app/ffmix/normalized',
                    'poll_seconds' => 3,
                ],
                'dsk' => [
                    'folder' => 'storage/app/ffmix/dsk',
                    'active_template' => 'sample-lower-third.html',
                    'title' => 'ffMix Live Broadcast',
                    'subtitle' => 'Normalized 1080p30 Feed • Live Studio',
                    'accent' => '#06b6d4',
                    'duration' => 6.0,
                    'start_at' => 1.0,
                ],
                'transition' => [
                    'type' => 'logo_sting',
                    'duration' => 1.0,
                    'sting_asset' => null,
                ],
                'pipeline' => [
                    'container' => 'mp4',
                    'video_codec' => 'h264',
                    'audio_codec' => 'aac',
                    'hw_accel' => false,
                    'resolution' => '1080p',
                    'crf' => 20,
                    'audio_bitrate' => '192k',
                ],
            ],
        ],
        [
            'slug' => 'keynote-4k-show',
            'name' => 'Keynote 4K Studio',
            'description' => '4K UHD Apple VideoToolbox workflow with Dip-to-Black & crisp presenter lower thirds',
            'is_active' => false,
            'config' => [
                'show_name' => 'Keynote 4K Studio',
                'normalize' => [
                    'width' => 3840,
                    'height' => 2160,
                    'fps' => 30,
                    'video_codec' => 'h264',
                    'audio_codec' => 'aac',
                    'audio_bitrate' => '256k',
                    'hw_accel' => true,
                    'slate_duration' => 5.0,
                ],
                'watchdog' => [
                    'enabled' => false,
                    'input_dir' => 'storage/app/ffmix/ingest-4k',
                    'normalized_dir' => 'storage/app/ffmix/normalized-4k',
                    'poll_seconds' => 3,
                ],
                'dsk' => [
                    'folder' => 'storage/app/ffmix/dsk',
                    'active_template' => 'sample-lower-third.html',
                    'title' => 'Keynote Presentation',
                    'subtitle' => 'Ultra HD 4K Production',
                    'accent' => '#3b82f6',
                    'duration' => 6.0,
                    'start_at' => 1.0,
                ],
                'transition' => [
                    'type' => 'fadeblack',
                    'duration' => 0.8,
                    'sting_asset' => null,
                ],
                'pipeline' => [
                    'container' => 'mp4',
                    'video_codec' => 'h265',
                    'audio_codec' => 'aac',
                    'hw_accel' => true,
                    'resolution' => '4k',
                    'crf' => 19,
                    'audio_bitrate' => '256k',
                ],
            ],
        ],
    ],
];
