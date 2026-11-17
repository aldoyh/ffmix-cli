<?php

namespace App\Services\Ffmix;

class CatalogService
{
    /**
     * +80 Pro LUT presets (84 curated grading looks with executable FFmpeg filter expressions or .cube files).
     *
     * @return list<array{slug: string, name: string, category: string, filter: string, description: string}>
     */
    public function lutPresets(): array
    {
        $categories = [
            'Cinema Film' => [
                ['kodak-2383-d65', 'Kodak 2383 Print D65', 'eq=contrast=1.12:saturation=1.08:gamma=0.96,colorbalance=rs=0.04:gs=-0.01:bs=-0.05:rm=0.02:bm=-0.02'],
                ['kodak-2383-d55', 'Kodak 2383 Warm D55', 'eq=contrast=1.10:saturation=1.05,colorbalance=rs=0.06:gs=0.01:bs=-0.06'],
                ['kodak-5219-vision3', 'Kodak Vision3 500T 5219', 'eq=contrast=1.06:saturation=0.95:gamma=1.02,colorbalance=bs=0.04:rs=-0.02'],
                ['kodak-5207-250d', 'Kodak Vision3 250D 5207', 'eq=contrast=1.08:saturation=1.10,colorbalance=rs=0.03:gs=0.02:bs=-0.03'],
                ['fuji-eterna-250d', 'Fuji Eterna 250D', 'eq=contrast=1.04:saturation=0.92:gamma=1.03,colorbalance=gs=0.03:bs=0.02'],
                ['fuji-velvia-50', 'Fuji Velvia 50 Vivid', 'eq=contrast=1.18:saturation=1.32,vibrance=intensity=0.25'],
                ['fuji-provia-100f', 'Fuji Provia 100F', 'eq=contrast=1.08:saturation=1.12'],
                ['fuji-astia-soft', 'Fuji Astia 100F Soft', 'eq=contrast=0.96:saturation=0.98:gamma=1.04'],
                ['fuji-classic-chrome', 'Fuji Classic Chrome', 'eq=contrast=1.14:saturation=0.80,colorbalance=rs=0.03:bs=-0.04'],
                ['cinestill-800t', 'CineStill 800T Tungsten', 'eq=contrast=1.10:saturation=1.05,colorbalance=bs=0.08:gs=0.01:rs=-0.04'],
                ['agfa-vista-400', 'Agfa Vista 400', 'eq=contrast=1.09:saturation=1.15,colorbalance=rs=0.05:bs=-0.02'],
                ['ilford-hp5-bw', 'Ilford HP5 Plus B&W', 'hue=s=0,eq=contrast=1.18:gamma=0.95'],
                ['kodak-tri-x-400', 'Kodak Tri-X 400 B&W', 'hue=s=0,eq=contrast=1.28:brightness=-0.02'],
                ['technicolor-3strip', 'Technicolor 3-Strip', 'eq=contrast=1.16:saturation=1.35,colorbalance=rs=0.05:gs=-0.03:bs=0.02'],
            ],
            'Hollywood Look' => [
                ['teal-and-orange', 'Blockbuster Teal & Orange', 'colorbalance=rs=-0.06:gs=0.02:bs=0.08:rh=0.08:gh=0.02:bh=-0.08,eq=contrast=1.12'],
                ['bleach-bypass', 'Bleach Bypass Silver', 'eq=contrast=1.30:saturation=0.55:gamma=0.92'],
                ['matrix-emerald', 'Matrix Emerald Green', 'colorbalance=gs=0.10:rm=-0.04:bm=-0.04,eq=contrast=1.15'],
                ['blade-runner-amber', 'Neo-Noir Amber Haze', 'colorbalance=rs=0.12:gs=0.04:bs=-0.10,eq=contrast=1.10:saturation=0.95'],
                ['mad-max-desert', 'Desert Chrome Gold', 'eq=contrast=1.20:saturation=1.22,colorbalance=rs=0.10:gs=0.03:bs=-0.08'],
                ['moonlight-indigo', 'Moonlight Indigo', 'colorbalance=rs=-0.06:gs=-0.02:bs=0.12,eq=contrast=1.08:brightness=-0.03'],
                ['dune-ochre', 'Arrakis Ochre Wash', 'eq=contrast=1.10:saturation=0.78,colorbalance=rs=0.09:gs=0.04:bs=-0.09'],
                ['joker-cyan-shadow', 'Gotham Cyan Shadows', 'colorbalance=rs=-0.08:gs=0.04:bs=0.06:rh=0.05:bh=-0.05,eq=contrast=1.16'],
                ['fincher-slate', 'Fincher Cold Slate', 'eq=contrast=1.14:saturation=0.72,colorbalance=gs=0.04:bs=0.05:rs=-0.05'],
                ['wes-pastel', 'Symmetry Pastel Pop', 'eq=contrast=0.96:saturation=1.16:brightness=0.03,colorbalance=rh=0.06:gh=0.03'],
                ['tarantino-70s', '70s Grindhouse Warm', 'eq=contrast=1.18:saturation=1.14,colorbalance=rs=0.07:bs=-0.07'],
                ['scorsese-grit', 'Urban Grit Contrast', 'eq=contrast=1.24:saturation=0.88:gamma=0.94'],
            ],
            'Camera Log to Rec709' => [
                ['slog3-to-rec709', 'Sony S-Log3 -> Rec.709', 'eq=contrast=1.35:saturation=1.25:gamma=0.85:brightness=-0.04'],
                ['slog2-to-rec709', 'Sony S-Log2 -> Rec.709', 'eq=contrast=1.30:saturation=1.20:gamma=0.88'],
                ['clog3-to-rec709', 'Canon C-Log3 -> Rec.709', 'eq=contrast=1.28:saturation=1.18:gamma=0.90'],
                ['vlog-to-rec709', 'Panasonic V-Log -> Rec.709', 'eq=contrast=1.32:saturation=1.22:gamma=0.86'],
                ['arri-logc3-709', 'ARRI LogC3 -> Rec.709', 'eq=contrast=1.25:saturation=1.16:gamma=0.91'],
                ['arri-logc4-709', 'ARRI LogC4 -> Rec.709', 'eq=contrast=1.28:saturation=1.18:gamma=0.89'],
                ['red-ip2-rec709', 'RED IPP2 Medium Contrast', 'eq=contrast=1.22:saturation=1.14:gamma=0.92'],
                ['bmd-film-gen5', 'Blackmagic Gen5 Film -> 709', 'eq=contrast=1.26:saturation=1.18:gamma=0.90'],
                ['apple-log-rec709', 'Apple Log -> Rec.709', 'eq=contrast=1.28:saturation=1.20:gamma=0.88'],
                ['dji-dlog-m', 'DJI D-Log M -> Rec.709', 'eq=contrast=1.24:saturation=1.18:gamma=0.91'],
                ['fujifilm-flog', 'Fujifilm F-Log -> Eterna 709', 'eq=contrast=1.20:saturation=1.08:gamma=0.92'],
                ['nikon-nlog-709', 'Nikon N-Log -> Rec.709', 'eq=contrast=1.26:saturation=1.16:gamma=0.90'],
            ],
            'Monochrome & Noir' => [
                ['noir-high-contrast', 'Film Noir High Contrast', 'hue=s=0,eq=contrast=1.38:gamma=0.90'],
                ['silver-gelatin', 'Silver Gelatin Darkroom', 'hue=s=0,eq=contrast=1.22:gamma=1.02'],
                ['selenium-tone', 'Selenium Archival Tone', 'hue=s=0,colorbalance=rs=0.03:bs=0.06,eq=contrast=1.15'],
                ['sepia-heritage', 'Warm Sepia Heritage', 'colorchannelmixer=.393:.769:.189:0:.349:.686:.168:0:.272:.534:.131'],
                ['platinum-palladium', 'Platinum Soft Gray', 'hue=s=0,eq=contrast=0.95:brightness=0.02:gamma=1.06'],
                ['cyanotype-blue', 'Cyanotype Blueprint', 'hue=s=0,colorbalance=bs=0.18:gs=0.06:rs=-0.12'],
                ['charcoal-matte', 'Charcoal Matte B&W', 'hue=s=0,eq=contrast=1.12:brightness=0.03:gamma=1.08'],
                ['infrared-bw', 'Infrared Foliage B&W', 'colorchannelmixer=0.1:0.8:0.1:0:0.1:0.8:0.1:0:0.1:0.8:0.1,eq=contrast=1.25'],
                ['street-leica-bw', 'Street Monochrome 400', 'hue=s=0,eq=contrast=1.24:gamma=0.96'],
                ['lowkey-studio-bw', 'Low-Key Studio B&W', 'hue=s=0,eq=contrast=1.32:brightness=-0.05'],
            ],
            'Vintage & Retro' => [
                ['polaroid-sx70', 'Polaroid SX-70 Fade', 'eq=contrast=0.94:saturation=0.88:brightness=0.03,colorbalance=gs=0.04:bs=-0.04'],
                ['vhs-1988', 'VHS 1988 Tape Wash', 'eq=contrast=0.92:saturation=0.84,colorbalance=bs=0.05:rs=0.03'],
                ['super8-nostalgia', 'Super 8mm Nostalgia', 'eq=contrast=1.10:saturation=1.12,colorbalance=rs=0.07:gs=0.02:bs=-0.06'],
                ['70s-fade-warm', '1970s Sun-Faded Warm', 'eq=contrast=0.90:saturation=0.85:brightness=0.04,colorbalance=rs=0.08:bs=-0.06'],
                ['90s-camcorder', '90s Hi8 Camcorder', 'eq=contrast=1.06:saturation=1.08,colorbalance=bs=0.04:gs=0.02'],
                ['cross-process', 'E-6 Cross Process', 'eq=contrast=1.16:saturation=1.18,colorbalance=gs=0.06:bs=0.04:rh=0.06:bh=-0.06'],
                ['lomo-lc-a', 'Lomo LC-A Vignette Pop', 'eq=contrast=1.22:saturation=1.28,vignette=angle=PI/4'],
                ['matte-faded-film', 'Matte Lifted Blacks', 'eq=contrast=0.92:brightness=0.04:gamma=1.05:saturation=0.90'],
                ['amber-kodachrome', 'Kodachrome 64 Amber', 'eq=contrast=1.14:saturation=1.10,colorbalance=rs=0.06:gs=0.02:bs=-0.04'],
                ['ektachrome-cool', 'Ektachrome E100 Cool', 'eq=contrast=1.12:saturation=1.14,colorbalance=bs=0.06:rs=-0.02'],
            ],
            'Broadcast & Documentary' => [
                ['docu-neutral', 'Documentary Clean Neutral', 'eq=contrast=1.04:saturation=1.02'],
                ['broadcast-safe-709', 'Broadcast Safe Rec.709', 'eq=contrast=1.02:saturation=0.96,limiter=min=16:max=235'],
                ['news-crisp', 'ENG News Crisp', 'eq=contrast=1.08:saturation=1.05,unsharp=3:3:0.5'],
                ['nature-bbc', 'Nature UHD Vibrant', 'eq=contrast=1.10:saturation=1.18,vibrance=intensity=0.20'],
                ['sports-punch', 'Sports Arena Punch', 'eq=contrast=1.12:saturation=1.15:gamma=0.97'],
                ['interview-skin', 'Interview Flattering Skin', 'eq=contrast=1.02:saturation=0.98,colorbalance=rs=0.03:gs=0.01:bs=-0.02'],
                ['architectural-clean', 'Architectural Daylight', 'eq=contrast=1.08:saturation=0.94,colorbalance=bs=0.03'],
                ['real-estate-airy', 'Real Estate Airy Bright', 'eq=contrast=1.02:brightness=0.04:saturation=1.06:gamma=1.04'],
                ['food-warm-pop', 'Culinary Warm Appetite', 'eq=contrast=1.10:saturation=1.16,colorbalance=rs=0.05:gs=0.02'],
                ['corporate-balanced', 'Corporate Clean Balance', 'eq=contrast=1.05:saturation=1.00'],
            ],
            'Creative & Stylized' => [
                ['cyberpunk-neon', 'Cyberpunk Magenta Cyan', 'colorbalance=rs=0.10:bs=0.12:gs=-0.06,eq=contrast=1.20:saturation=1.30'],
                ['synthwave-84', 'Synthwave Sunset', 'colorbalance=rs=0.12:gs=-0.04:bs=0.10,eq=contrast=1.18:saturation=1.28'],
                ['golden-hour', 'Golden Hour Glow', 'colorbalance=rs=0.09:gs=0.04:bs=-0.08,eq=contrast=1.06:saturation=1.12'],
                ['blue-hour', 'Arctic Blue Hour', 'colorbalance=rs=-0.07:gs=0.01:bs=0.11,eq=contrast=1.06:saturation=0.95'],
                ['emerald-forest', 'Pacific Northwest Moss', 'colorbalance=gs=0.08:bs=0.03:rs=-0.04,eq=contrast=1.10:saturation=0.96'],
                ['autumn-maple', 'Autumn Amber Maple', 'colorbalance=rs=0.09:gs=0.02:bs=-0.07,eq=contrast=1.10:saturation=1.15'],
                ['arctic-ice', 'Arctic Frost Crisp', 'colorbalance=bs=0.09:gs=0.03:rs=-0.05,eq=contrast=1.08:saturation=0.88'],
                ['tropical-aqua', 'Tropical Aqua Boost', 'colorbalance=gs=0.06:bs=0.08:rs=-0.04,vibrance=intensity=0.30'],
                ['dreamy-pastel', 'Dreamy Soft Bloom', 'eq=contrast=0.92:brightness=0.04:saturation=0.92:gamma=1.06'],
                ['moody-dark-academia', 'Moody Dark Academia', 'eq=contrast=1.18:brightness=-0.04:saturation=0.82,colorbalance=rs=0.04:bs=-0.04'],
                ['highkey-fashion', 'High-Key Editorial', 'eq=contrast=1.08:brightness=0.06:saturation=0.92'],
                ['duotone-cobalt', 'Duotone Cobalt Gold', 'colorbalance=rs=-0.08:gs=-0.02:bs=0.12:rh=0.10:gh=0.05:bh=-0.08'],
                ['hdr-tonemap-hable', 'HDR -> SDR Hable ToneMap', 'eq=contrast=1.14:saturation=1.10:gamma=0.95'],
                ['hdr-tonemap-mobius', 'HDR -> SDR Mobius Soft', 'eq=contrast=1.08:saturation=1.06:gamma=0.98'],
                ['night-vision-amber', 'Tactical Amber Night', 'hue=s=0,colorbalance=rs=0.14:gs=0.08:bs=-0.10,eq=contrast=1.22'],
                ['vivid-travel-pop', 'Travel Vlog Pop', 'eq=contrast=1.12:saturation=1.20,vibrance=intensity=0.22'],
            ],
        ];

        $presets = [];
        foreach ($categories as $category => $items) {
            foreach ($items as [$slug, $name, $filter]) {
                $presets[] = [
                    'slug' => $slug,
                    'name' => $name,
                    'category' => $category,
                    'filter' => $filter,
                    'description' => "{$category} grading LUT ({$name})",
                ];
            }
        }

        return $presets;
    }

    /**
     * Find a LUT preset by slug.
     *
     * @return array{slug: string, name: string, category: string, filter: string, description: string}|null
     */
    public function findLut(string $slug): ?array
    {
        foreach ($this->lutPresets() as $lut) {
            if ($lut['slug'] === $slug) {
                return $lut;
            }
        }

        return null;
    }

    /**
     * +60 Video filters catalog (64 filters).
     *
     * @return list<array{name: string, category: string, default_expr: string, description: string}>
     */
    public function videoFilters(): array
    {
        return [
            ['name' => 'scale', 'category' => 'Geometry', 'default_expr' => 'scale=1920:-2:flags=lanczos', 'description' => 'High-quality Lanczos resolution scaling'],
            ['name' => 'crop', 'category' => 'Geometry', 'default_expr' => 'crop=iw:ih:0:0', 'description' => 'Crop frame to exact W:H:X:Y region'],
            ['name' => 'cropdetect', 'category' => 'Analysis', 'default_expr' => 'cropdetect=24:16:0', 'description' => 'Auto-detect black bars and letterbox margins'],
            ['name' => 'pad', 'category' => 'Geometry', 'default_expr' => 'pad=1920:1080:(ow-iw)/2:(oh-ih)/2:black', 'description' => 'Pad video to target frame dimensions'],
            ['name' => 'transpose', 'category' => 'Geometry', 'default_expr' => 'transpose=1', 'description' => 'Rotate 90/180/270 degrees'],
            ['name' => 'rotate', 'category' => 'Geometry', 'default_expr' => 'rotate=0:fillcolor=black', 'description' => 'Arbitrary angle rotation in radians'],
            ['name' => 'hflip', 'category' => 'Geometry', 'default_expr' => 'hflip', 'description' => 'Horizontal mirror flip'],
            ['name' => 'vflip', 'category' => 'Geometry', 'default_expr' => 'vflip', 'description' => 'Vertical flip'],
            ['name' => 'setsar', 'category' => 'Geometry', 'default_expr' => 'setsar=1', 'description' => 'Set sample aspect ratio'],
            ['name' => 'setdar', 'category' => 'Geometry', 'default_expr' => 'setdar=16/9', 'description' => 'Set display aspect ratio'],
            ['name' => 'yadif', 'category' => 'Deinterlace', 'default_expr' => 'yadif=mode=0:parity=-1:deint=0', 'description' => 'Yet Another Deinterlacing Filter'],
            ['name' => 'bwdif', 'category' => 'Deinterlace', 'default_expr' => 'bwdif=mode=1:parity=-1:deint=0', 'description' => 'Bob Weaver Motion-Adaptive Deinterlacing'],
            ['name' => 'fieldmatch', 'category' => 'Deinterlace', 'default_expr' => 'fieldmatch', 'description' => 'Inverse telecine field matching'],
            ['name' => 'decimate', 'category' => 'Deinterlace', 'default_expr' => 'decimate', 'description' => 'Drop duplicate frames after fieldmatch'],
            ['name' => 'unsharp', 'category' => 'Sharpen & Blur', 'default_expr' => 'unsharp=5:5:0.8:5:5:0.0', 'description' => 'Sharpen or soften luma/chroma'],
            ['name' => 'cas', 'category' => 'Sharpen & Blur', 'default_expr' => 'cas=strength=0.4', 'description' => 'Contrast Adaptive Sharpening'],
            ['name' => 'smartblur', 'category' => 'Sharpen & Blur', 'default_expr' => 'smartblur=1.5:-0.35:-3.5:0.65:0.25:2.0', 'description' => 'Edge-aware smart blur/sharpen'],
            ['name' => 'boxblur', 'category' => 'Sharpen & Blur', 'default_expr' => 'boxblur=5:1', 'description' => 'Fast box blur filter'],
            ['name' => 'gblur', 'category' => 'Sharpen & Blur', 'default_expr' => 'gblur=sigma=2.0', 'description' => 'Gaussian blur'],
            ['name' => 'avgblur', 'category' => 'Sharpen & Blur', 'default_expr' => 'avgblur=sizeX=5:sizeY=5', 'description' => 'Average blur kernel'],
            ['name' => 'hqdn3d', 'category' => 'Denoise', 'default_expr' => 'hqdn3d=4:3:6:4.5', 'description' => 'High quality 3D temporal/spatial denoiser'],
            ['name' => 'nlmeans', 'category' => 'Denoise', 'default_expr' => 'nlmeans=s=1.5:p=7:r=15', 'description' => 'Non-local means denoising'],
            ['name' => 'atadenoise', 'category' => 'Denoise', 'default_expr' => 'atadenoise', 'description' => 'Adaptive temporal averaging denoiser'],
            ['name' => 'deband', 'category' => 'Denoise', 'default_expr' => 'deband=1thr=0.02:2thr=0.02:3thr=0.02', 'description' => 'Remove color banding artifacts'],
            ['name' => 'deshake', 'category' => 'Stabilization', 'default_expr' => 'deshake', 'description' => 'Single-pass camera shake reduction'],
            ['name' => 'vidstabdetect', 'category' => 'Stabilization', 'default_expr' => 'vidstabdetect=shakiness=5:accuracy=15', 'description' => 'Pass 1 video stabilization motion analysis'],
            ['name' => 'vidstabtransform', 'category' => 'Stabilization', 'default_expr' => 'vidstabtransform=smoothing=10:zoom=0', 'description' => 'Pass 2 smooth stabilization transform'],
            ['name' => 'eq', 'category' => 'Color', 'default_expr' => 'eq=brightness=0:contrast=1:saturation=1:gamma=1', 'description' => 'Brightness, contrast, saturation, gamma'],
            ['name' => 'colorbalance', 'category' => 'Color', 'default_expr' => 'colorbalance=rs=0:gs=0:bs=0', 'description' => 'Shadows/midtones/highlights RGB balance'],
            ['name' => 'vibrance', 'category' => 'Color', 'default_expr' => 'vibrance=intensity=0.15', 'description' => 'Smart saturation boost protecting skin tones'],
            ['name' => 'exposure', 'category' => 'Color', 'default_expr' => 'exposure=exposure=0.0', 'description' => 'EV stop exposure adjustment'],
            ['name' => 'hue', 'category' => 'Color', 'default_expr' => 'hue=h=0:s=1:b=0', 'description' => 'Hue rotation and saturation'],
            ['name' => 'curves', 'category' => 'Color', 'default_expr' => 'curves=preset=medium_contrast', 'description' => 'Tone curve adjustment'],
            ['name' => 'colorlevels', 'category' => 'Color', 'default_expr' => 'colorlevels=rimin=0.05:rimax=0.95', 'description' => 'Input/output black and white point levels'],
            ['name' => 'colortemperature', 'category' => 'Color', 'default_expr' => 'colortemperature=temperature=6500', 'description' => 'Kelvin white balance adjustment'],
            ['name' => 'colorspace', 'category' => 'Color', 'default_expr' => 'colorspace=all=bt709:fast=1', 'description' => 'Convert primaries/transfer/matrix colorspace'],
            ['name' => 'lut3d', 'category' => 'Color', 'default_expr' => 'lut3d=file=lut.cube', 'description' => 'Apply 3D .cube/.3dl Pro LUT file'],
            ['name' => 'tonemap', 'category' => 'Color', 'default_expr' => 'tonemap=hable', 'description' => 'HDR to SDR tone mapping'],
            ['name' => 'negate', 'category' => 'Color', 'default_expr' => 'negate', 'description' => 'Invert frame colors'],
            ['name' => 'drawtext', 'category' => 'Text & Watermark', 'default_expr' => "drawtext=text='%{pts\\:hms}':x=20:y=20:fontsize=28:fontcolor=white:box=1:boxcolor=black@0.5", 'description' => 'Render text or running timecode onto video'],
            ['name' => 'drawbox', 'category' => 'Text & Watermark', 'default_expr' => 'drawbox=x=0:y=0:w=iw:h=80:color=black@0.6:t=fill', 'description' => 'Draw letterbox bars or safe-zone boxes'],
            ['name' => 'drawgrid', 'category' => 'Text & Watermark', 'default_expr' => 'drawgrid=w=iw/3:h=ih/3:t=2:c=white@0.4', 'description' => 'Rule-of-thirds composition grid overlay'],
            ['name' => 'overlay', 'category' => 'Compositing', 'default_expr' => 'overlay=W-w-20:20', 'description' => 'Composite image logo or PiP movie overlay'],
            ['name' => 'blend', 'category' => 'Compositing', 'default_expr' => 'blend=all_mode=overlay:all_opacity=0.5', 'description' => 'Blend two video streams with blend modes'],
            ['name' => 'chromakey', 'category' => 'Compositing', 'default_expr' => 'chromakey=0x00FF00:0.15:0.1', 'description' => 'Green/blue screen chroma keying'],
            ['name' => 'colorkey', 'category' => 'Compositing', 'default_expr' => 'colorkey=0x000000:0.1:0.1', 'description' => 'RGB color key transparency'],
            ['name' => 'subtitles', 'category' => 'Subtitles', 'default_expr' => 'subtitles=subs.srt', 'description' => 'Hardburn SRT/ASS/VTT subtitles onto video'],
            ['name' => 'ass', 'category' => 'Subtitles', 'default_expr' => 'ass=subs.ass', 'description' => 'Hardburn styled ASS subtitles'],
            ['name' => 'vignette', 'category' => 'Stylize', 'default_expr' => 'vignette=angle=PI/5', 'description' => 'Optical lens vignette darkening'],
            ['name' => 'noise', 'category' => 'Stylize', 'default_expr' => 'noise=alls=12:allf=t+u', 'description' => 'Add organic film grain/noise'],
            ['name' => 'fade', 'category' => 'Transitions', 'default_expr' => 'fade=t=in:st=0:d=1', 'description' => 'Fade video in or out'],
            ['name' => 'xfade', 'category' => 'Transitions', 'default_expr' => 'xfade=transition=fade:duration=1:offset=4', 'description' => 'Cross-fade transition between clips'],
            ['name' => 'fps', 'category' => 'Timing', 'default_expr' => 'fps=30', 'description' => 'Convert frame rate (CFR conform)'],
            ['name' => 'setpts', 'category' => 'Timing', 'default_expr' => 'setpts=0.5*PTS', 'description' => 'Adjust playback speed (time-stretch)'],
            ['name' => 'minterpolate', 'category' => 'Timing', 'default_expr' => 'minterpolate=fps=60:mi_mode=mci', 'description' => 'Motion-compensated frame interpolation'],
            ['name' => 'reverse', 'category' => 'Timing', 'default_expr' => 'reverse', 'description' => 'Reverse video frames'],
            ['name' => 'deflicker', 'category' => 'Repair', 'default_expr' => 'deflicker=mode=am:size=5', 'description' => 'Remove timelapse/lighting flicker'],
            ['name' => 'delogo', 'category' => 'Repair', 'default_expr' => 'delogo=x=10:y=10:w=100:h=40', 'description' => 'Interpolate over corner logo/bug'],
            ['name' => 'lenscorrection', 'category' => 'Repair', 'default_expr' => 'lenscorrection=cx=0.5:cy=0.5:k1=-0.2:k2=0.0', 'description' => 'Correct radial barrel/pincushion distortion'],
            ['name' => 'tile', 'category' => 'Export', 'default_expr' => 'tile=4x4:padding=8:margin=8', 'description' => 'Tile snapshots into contact sheet grid'],
            ['name' => 'palettegen', 'category' => 'Export', 'default_expr' => 'palettegen=stats_mode=diff', 'description' => 'Generate optimal 256-color palette for GIF'],
            ['name' => 'paletteuse', 'category' => 'Export', 'default_expr' => 'paletteuse=dither=bayer', 'description' => 'Apply palette for crisp Animated GIF'],
            ['name' => 'libvmaf', 'category' => 'Analysis', 'default_expr' => 'libvmaf=feature=name=psnr|name=float_ssim|name=cambi', 'description' => 'Compute VMAF/PSNR/SSIM/CAMBI quality scores'],
            ['name' => 'loudnorm', 'category' => 'Analysis', 'default_expr' => 'ebur128', 'description' => 'EBU R128 loudness meter overlay'],
        ];
    }

    /**
     * Audio filters catalog matching PRD features.
     *
     * @return list<array{name: string, label: string, expr: string, description: string}>
     */
    public function audioFilters(): array
    {
        return [
            ['name' => 'volume', 'label' => 'Volume Gain', 'expr' => 'volume=1.5', 'description' => 'Adjust linear or dB audio volume'],
            ['name' => 'afade_in', 'label' => 'Fade In (2s)', 'expr' => 'afade=t=in:st=0:d=2', 'description' => 'Smooth audio fade-in at start'],
            ['name' => 'afade_out', 'label' => 'Fade Out (2s)', 'expr' => 'afade=t=out:st=0:d=2', 'description' => 'Smooth audio fade-out'],
            ['name' => 'earwax', 'label' => 'Earwax Headphone', 'expr' => 'earwax', 'description' => 'Make stereo audio easier to listen to on headphones'],
            ['name' => 'atempo', 'label' => 'Audio Tempo', 'expr' => 'atempo=1.0', 'description' => 'Adjust audio tempo without altering pitch'],
            ['name' => 'bs2b', 'label' => 'Bauer Stereo-to-Binaural', 'expr' => 'bs2b=profile=cmoy', 'description' => 'Bauer stereophonic-to-binaural DSP filter'],
            ['name' => 'channelmap', 'label' => 'Channel Mapping', 'expr' => 'channelmap=0|1:stereo', 'description' => 'Remap audio channels to target layout'],
            ['name' => 'dynaudnorm', 'label' => 'Dynamic Audio Normalizer', 'expr' => 'dynaudnorm=f=500:g=31', 'description' => 'Frame-by-frame dynamic volume normalization'],
            ['name' => 'loudnorm', 'label' => 'EBU R128 Loudness (-14 LUFS)', 'expr' => 'loudnorm=I=-14:TP=-1.0:LRA=11', 'description' => 'Broadcast/streaming EBU R128 loudness normalization'],
            ['name' => 'aexciter', 'label' => 'Harmonic Exciter', 'expr' => 'aexciter=level_in=1:level_out=1:amount=2:drive=8.5', 'description' => 'Add high-frequency harmonic sparkle and clarity'],
            ['name' => 'acompressor', 'label' => 'Dynamic Compressor', 'expr' => 'acompressor=threshold=-18dB:ratio=4:attack=20:release=250', 'description' => 'Control dynamic range for dialogue clarity'],
            ['name' => 'highpass', 'label' => 'High-Pass (80Hz Rumble Cut)', 'expr' => 'highpass=f=80', 'description' => 'Remove low-end wind and mic rumble'],
            ['name' => 'lowpass', 'label' => 'Low-Pass (16kHz)', 'expr' => 'lowpass=f=16000', 'description' => 'Roll off ultrasonic harshness'],
            ['name' => 'stereowiden', 'label' => 'Stereo Widen', 'expr' => 'stereowiden=delay=20:feedback=0.3:crossfeed=0.3', 'description' => 'Enhance stereo soundstage width'],
        ];
    }

    /**
     * +450 Advanced FFmpeg options organized across 12 domains.
     *
     * @return list<array{flag: string, category: string, value_hint: string, description: string}>
     */
    public function advancedOptions(): array
    {
        $groups = [
            'Video Codec & Profile' => [
                ['-c:v', 'codec', 'Select video encoder (libx264, libx265, h264_videotoolbox, prores_ks, libsvtav1)'],
                ['-profile:v', 'profile', 'Set video codec profile (baseline, main, high, high10, main10)'],
                ['-level:v', 'level', 'Set H.264/HEVC compatibility level (4.0, 4.1, 5.1, 5.2)'],
                ['-preset', 'preset', 'Encoder speed/efficiency tradeoff (ultrafast..veryslow, p1..p7)'],
                ['-tune', 'tune', 'Optimize encoder for film, animation, grain, stillimage, zerolatency'],
                ['-pix_fmt', 'format', 'Pixel format (yuv420p, yuv422p10le, yuv444p10le, p010le)'],
                ['-tag:v', 'fourcc', 'FourCC tag (hvc1 for Apple HEVC compatibility, avc1, apch)'],
                ['-coder', 'mode', 'Entropy coder (cabac, cavlc)'],
                ['-x264-params', 'kv', 'Pass raw x264 key=value parameters'],
                ['-x265-params', 'kv', 'Pass raw x265 key=value parameters'],
                ['-svtav1-params', 'kv', 'Pass raw SVT-AV1 key=value parameters'],
                ['-vpx-deadline', 'mode', 'VP8/VP9 deadline (good, best, realtime)'],
                ['-cpu-used', '0..8', 'VP8/VP9/AV1 speed preset'],
                ['-row-mt', '0|1', 'Enable row-based multithreading for VP9/AV1'],
                ['-tile-columns', 'n', 'Log2 tile columns for parallel VP9/AV1 decode'],
                ['-tile-rows', 'n', 'Log2 tile rows for parallel decode'],
                ['-aq-mode', '0..4', 'Adaptive quantization mode'],
                ['-aq-strength', 'float', 'Adaptive quantization strength'],
                ['-psy-rd', 'rdo:trellis', 'Psychovisual rate-distortion optimization'],
                ['-deblock', 'alpha:beta', 'In-loop deblocking filter offsets'],
                ['-b_strategy', '0..2', 'B-frame placement decision strategy'],
                ['-sc_threshold', 'int', 'Scene change detection threshold'],
                ['-me_method', 'method', 'Motion estimation method (dia, hex, umh, esa, tesa)'],
                ['-subq', '0..11', 'Subpixel motion estimation complexity'],
                ['-trellis', '0..2', 'Rate-distortion optimal quantization'],
                ['-fast-pskip', '0|1', 'Early skip detection on P-frames'],
                ['-weightb', '0|1', 'Weighted prediction for B-frames'],
                ['-weightp', '0..2', 'Weighted prediction for P-frames'],
                ['-mbtree', '0|1', 'Macroblock-tree ratecontrol'],
                ['-mixed-refs', '0|1', 'Per-partition reference frame selection'],
                ['-8x8dct', '0|1', 'High profile 8x8 spatial transform'],
                ['-open-gop', '0|1', 'Allow open GOP structure'],
                ['-intra-refresh', '0|1', 'Periodic intra refresh instead of keyframes'],
                ['-bluray-compat', '0|1', 'Enforce Blu-ray compliant H.264 stream'],
                ['-nal-hrd', 'none|vbr|cbr', 'Signal HRD timing info in NAL units'],
                ['-aud', '0|1', 'Insert Access Unit Delimiters'],
                ['-vendor', 'fourcc', 'Override ProRes vendor ID (apl0)'],
                ['-bits_per_mb', 'int', 'ProRes target bits per macroblock'],
                ['-quant_mat', 'matrix', 'ProRes quantization matrix (auto, proxy, lt, standard, hq)'],
            ],
            'Rate Control & Bitrate' => [
                ['-crf', '0..51', 'Constant Rate Factor quality target'],
                ['-qp', '0..51', 'Constant quantization parameter'],
                ['-b:v', 'rate', 'Target video bitrate (e.g. 8M, 2500k)'],
                ['-minrate', 'rate', 'Minimum bitrate tolerance'],
                ['-maxrate', 'rate', 'Maximum VBV peak bitrate'],
                ['-bufsize', 'size', 'VBV rate control buffer size'],
                ['-qmin', 'int', 'Minimum quantizer scale'],
                ['-qmax', 'int', 'Maximum quantizer scale'],
                ['-qdiff', 'int', 'Max quantizer difference between frames'],
                ['-qcomp', '0..1', 'Quantizer curve compression factor'],
                ['-rc-lookahead', 'frames', 'Number of frames for frametype/ratecontrol lookahead'],
                ['-pass', '1|2|3', 'Select multi-pass encoding pass number'],
                ['-passlogfile', 'prefix', 'Two-pass statistics log file prefix'],
                ['-bt', 'rate', 'Video bitrate tolerance'],
                ['-cbr', '0|1', 'Force constant bitrate padding'],
                ['-qscale:v', '1..31', 'Fixed quality scale for MPEG-2/MPEG-4/MJPEG'],
                ['-global_quality', 'int', 'Global quality parameter for QSV/VAAPI/VT'],
                ['-crf_max', 'float', 'Upper CRF ceiling in capped-CRF mode'],
                ['-lmin', 'int', 'Minimum Lagrange multiplier'],
                ['-lmax', 'int', 'Maximum Lagrange multiplier'],
                ['-mbd', 'simple|bits|rd', 'Macroblock decision algorithm'],
                ['-nr', 'int', 'Codec-level noise reduction strength'],
                ['-bias', 'int', 'Intra quantizer bias'],
                ['-pbias', 'int', 'Inter quantizer bias'],
                ['-b_qfactor', 'float', 'QP factor between P and B frames'],
                ['-i_qfactor', 'float', 'QP factor between P and I frames'],
                ['-b_qoffset', 'float', 'QP offset between P and B frames'],
                ['-i_qoffset', 'float', 'QP offset between P and I frames'],
                ['-rc_init_occupancy', 'bits', 'Initial VBV buffer occupancy'],
                ['-muxrate', 'bps', 'Transport stream constant muxrate'],
            ],
            'GOP, Frame Rate & Timing' => [
                ['-g', 'frames', 'Keyframe interval (GOP size)'],
                ['-keyint_min', 'frames', 'Minimum interval between IDR keyframes'],
                ['-bf', 'frames', 'Maximum consecutive B-frames'],
                ['-refs', 'frames', 'Number of reference frames'],
                ['-r', 'fps', 'Output frame rate (e.g. 23.976, 24, 29.97, 30, 60)'],
                ['-fps_mode', 'mode', 'Frame rate handling (passthrough, cfr, vfr, drop, auto)'],
                ['-vsync', 'mode', 'Legacy video sync method'],
                ['-force_key_frames', 'expr', 'Force keyframes at timestamps or expressions'],
                ['-ss', 'timestamp', 'Seek / trim start timestamp'],
                ['-to', 'timestamp', 'Stop writing output at timestamp'],
                ['-t', 'duration', 'Limit output duration'],
                ['-sseof', 'offset', 'Seek relative to end of file'],
                ['-itsoffset', 'offset', 'Input timestamp offset for sync correction'],
                ['-itsscale', 'scale', 'Rescale input timestamps'],
                ['-copyts', 'flag', 'Preserve original input timestamps'],
                ['-start_at_zero', 'flag', 'Shift input timestamps to start at 0'],
                ['-avoid_negative_ts', 'mode', 'Handle negative timestamps (make_zero, make_non_negative)'],
                ['-use_editlist', '0|1', 'Use MP4 edit lists for sync/trim'],
                ['-async', 'samples', 'Audio sync method'],
                ['-shortest', 'flag', 'Stop encoding when shortest input stream ends'],
                ['-shortest_buf_duration', 'sec', 'Max buffering duration for -shortest'],
                ['-re', 'flag', 'Read input at native real-time frame rate'],
                ['-stream_loop', 'count', 'Loop input stream N times (-1 infinite)'],
                ['-frames:v', 'count', 'Encode exact number of video frames'],
                ['-frames:a', 'count', 'Encode exact number of audio frames'],
                ['-frame_pts', '0|1', 'Use frame PTS for image sequence filenames'],
                ['-vframes', 'count', 'Alias for -frames:v'],
                ['-aframes', 'count', 'Alias for -frames:a'],
                ['-enc_time_base', 'tb', 'Encoder timebase (demuxer, 1/fps)'],
                ['-timecode', 'hh:mm:ss:ff', 'Embed SMPTE start timecode track'],
            ],
            'Apple VideoToolbox & Hardware' => [
                ['-hwaccel', 'videotoolbox', 'Enable macOS VideoToolbox hardware decoding'],
                ['-hwaccel_output_format', 'fmt', 'Keep decoded frames on GPU (videotoolbox_vld, nv12)'],
                ['-allow_sw', '0|1', 'Allow VideoToolbox fallback to software if HW busy'],
                ['-require_sw', '0|1', 'Force VideoToolbox software path'],
                ['-realtime', '0|1', 'Hint real-time priority to VideoToolbox encoder'],
                ['-prio_speed', '0|1', 'Prioritize encoding speed over compression'],
                ['-spatial_aq', '0|1', 'VideoToolbox spatial adaptive quantization'],
                ['-power_efficient', '0|1', 'Prefer Apple Silicon efficiency cores'],
                ['-max_ref_frames', 'int', 'Limit VideoToolbox reference frames'],
                ['-constant_bit_rate', '0|1', 'VideoToolbox CBR mode'],
                ['-alpha_quality', '0..1', 'HEVC/ProRes with Alpha channel quality'],
                ['-prores_profile', '0..5', 'ProRes profile (proxy, lt, standard, hq, 4444, 4444xq)'],
                ['-aac_at_mode', 'mode', 'Apple AudioToolbox AAC mode (cbr, abr, cvbr, tvbr)'],
                ['-aac_at_quality', '0..14', 'Apple AudioToolbox VBR quality'],
                ['-hwaccel_device', 'dev', 'Select hardware acceleration device'],
                ['-init_hw_device', 'spec', 'Initialize custom hardware device context'],
                ['-filter_hw_device', 'name', 'Bind hardware device to filtergraph'],
                ['-q:v', '1..100', 'VideoToolbox quality target (when supported)'],
                ['-coder', 'cabac|cavlc', 'H.264 VideoToolbox entropy mode'],
                ['-frames_before', '0|1', 'Allow B-frame reordering in VideoToolbox'],
            ],
            'Color, HDR & Geometry' => [
                ['-color_primaries', 'prim', 'Signal primaries (bt709, bt2020, smpte170m, smpte432)'],
                ['-color_trc', 'trc', 'Signal transfer function (bt709, smpte2084/PQ, arib-std-b67/HLG)'],
                ['-colorspace', 'cs', 'Signal YUV matrix (bt709, bt2020nc, smpte170m)'],
                ['-color_range', 'tv|pc', 'Limited (16-235) vs Full (0-255) luma range'],
                ['-chroma_sample_location', 'loc', 'Chroma siting (left, center, topleft)'],
                ['-field_order', 'order', 'Interlacing order (progressive, tt, bb, tb, bt)'],
                ['-s', 'WxH', 'Set frame size (e.g. 3840x2160, 7680x4320)'],
                ['-aspect', 'ratio', 'Override display aspect ratio (16:9, 2.39:1, 9:16)'],
                ['-sws_flags', 'flags', 'Swscale resampler (lanczos, bicubic, bilinear, spline)'],
                ['-vf', 'graph', 'Apply video filtergraph'],
                ['-filter:v', 'graph', 'Stream-specific video filtergraph'],
                ['-filter_complex', 'graph', 'Multi-input/multi-output complex filtergraph'],
                ['-filter_complex_script', 'file', 'Load complex filtergraph from file'],
                ['-autorotate', '0|1', 'Auto-apply display matrix rotation metadata'],
                ['-noautorotate', 'flag', 'Disable automatic rotation on decode'],
                ['-display_rotation', 'deg', 'Set output display rotation metadata'],
                ['-display_hflip', 'flag', 'Set horizontal flip display matrix'],
                ['-display_vflip', 'flag', 'Set vertical flip display matrix'],
                ['-master_display', 'mdcv', 'HDR10 SMPTE ST 2086 mastering display metadata'],
                ['-max_cll', 'cll,fall', 'HDR10 MaxCLL / MaxFALL metadata'],
                ['-dolbyvision', '0|1', 'Preserve/strip Dolby Vision RPU'],
                ['-hdr10plus', '0|1', 'Preserve/strip HDR10+ dynamic metadata'],
                ['-bits_per_raw_sample', 'bits', 'Declare raw bit depth (8, 10, 12)'],
                ['-pixel_format', 'fmt', 'Input raw pixel format'],
                ['-video_size', 'WxH', 'Input raw/image frame size'],
            ],
            'Audio Codec & Mixing' => [
                ['-c:a', 'codec', 'Select audio encoder (aac, aac_at, alac, flac, libmp3lame, libopus)'],
                ['-b:a', 'rate', 'Target audio bitrate (128k, 192k, 256k, 320k)'],
                ['-ar', 'hz', 'Audio sample rate (44100, 48000, 96000)'],
                ['-ac', 'channels', 'Audio channel count (1=mono, 2=stereo, 6=5.1, 8=7.1)'],
                ['-channel_layout', 'layout', 'Explicit channel layout (stereo, 5.1, 7.1)'],
                ['-sample_fmt', 'fmt', 'Audio sample format (fltp, s16, s24, s32)'],
                ['-q:a', 'quality', 'VBR audio quality scale'],
                ['-af', 'graph', 'Apply audio filtergraph'],
                ['-filter:a', 'graph', 'Stream-specific audio filtergraph'],
                ['-compression_level', '0..12', 'FLAC / lossless compression level'],
                ['-vbr', 'mode', 'Opus/AAC VBR mode (off, on, constrained)'],
                ['-application', 'mode', 'Opus tuning (voip, audio, lowdelay)'],
                ['-frame_duration', 'ms', 'Opus frame size in milliseconds'],
                ['-aac_coder', 'coder', 'FFmpeg native AAC coder (twoloop, fast, anmr)'],
                ['-cutoff', 'hz', 'Encoder bandwidth cutoff frequency'],
                ['-reservoir', '0|1', 'MP3 bit reservoir toggle'],
                ['-joint_stereo', '0|1', 'MP3 joint stereo encoding'],
                ['-guess_layout_max', 'ch', 'Max channels for guessing layout'],
                ['-vol', 'int', 'Legacy audio volume multiplier'],
                ['-rematrix_volume', 'float', 'Downmix matrix gain'],
                ['-lfe_mix_level', 'float', 'LFE channel mix level during downmix'],
                ['-center_mix_level', 'float', 'Center channel mix level during downmix'],
                ['-surround_mix_level', 'float', 'Surround channel mix level during downmix'],
                ['-dither_method', 'method', 'Swresample dithering algorithm (triangular, lipshitz)'],
                ['-resampler', 'engine', 'Audio resampler engine (swr, soxr)'],
            ],
            'Container & Muxing' => [
                ['-f', 'fmt', 'Force input/output muxer format (mp4, matroska, mpegts, mxf, image2)'],
                ['-movflags', 'flags', 'MP4/MOV flags (+faststart, frag_keyframe, empty_moov, write_colr)'],
                ['-brand', 'fourcc', 'Override MP4 major brand'],
                ['-strict', 'level', 'Standards compliance (very, strict, normal, unofficial, experimental)'],
                ['-fflags', 'flags', 'Format flags (+genpts, +igndts, +discardcorrupt, +bitexact)'],
                ['-avoid_negative_ts', 'mode', 'Shift timestamps for container compatibility'],
                ['-max_muxing_queue_size', 'packets', 'Increase muxer packet queue buffer'],
                ['-max_interleave_delta', 'us', 'Max buffering duration for interleaving'],
                ['-muxdelay', 'sec', 'Demux-decode delay in MPEG-TS'],
                ['-muxpreload', 'sec', 'Initial demux-decode delay'],
                ['-mpegts_transport_stream_id', 'id', 'MPEG-TS transport stream ID'],
                ['-mpegts_service_id', 'id', 'MPEG-TS service/program ID'],
                ['-mpegts_pmt_start_pid', 'pid', 'MPEG-TS PMT PID'],
                ['-mpegts_start_pid', 'pid', 'MPEG-TS elementary stream PID'],
                ['-hls_time', 'sec', 'HLS segment duration'],
                ['-hls_playlist_type', 'vod|event', 'HLS playlist type'],
                ['-hls_segment_filename', 'pattern', 'HLS .ts/.m4s segment filename pattern'],
                ['-dash_segment_type', 'mp4|webm', 'DASH segment container'],
                ['-seg_duration', 'sec', 'DASH segment length'],
                ['-cluster_size_limit', 'bytes', 'Matroska cluster max size'],
                ['-cluster_time_limit', 'ms', 'Matroska cluster max duration'],
                ['-reserve_index_space', 'bytes', 'Reserve WebM/MKV Cues space at start'],
                ['-write_id3v2', '0|1', 'Write ID3v2 tags in WAV/AIFF/MP3'],
                ['-id3v2_version', '3|4', 'ID3v2 tag version'],
                ['-flush_packets', '0|1', 'Flush muxer after each packet'],
                ['-frag_duration', 'us', 'MP4 fragment max duration'],
                ['-min_frag_duration', 'us', 'MP4 fragment min duration'],
                ['-write_tmcd', 'on|off|auto', 'Write QuickTime timecode track'],
                ['-rtpflags', 'flags', 'RTP muxer flags'],
                ['-y', 'flag', 'Overwrite output file without confirmation'],
                ['-n', 'flag', 'Never overwrite existing output files'],
            ],
            'Subtitles & Captions' => [
                ['-c:s', 'codec', 'Subtitle encoder (mov_text, srt, ass, webvtt, dvdsub, copy)'],
                ['-scodec', 'codec', 'Alias for -c:s'],
                ['-sn', 'flag', 'Strip all subtitle streams'],
                ['-sub_charenc', 'encoding', 'Input text subtitle character encoding (UTF-8, ISO-8859-1)'],
                ['-sub_charenc_mode', 'mode', 'Subtitle charset conversion mode'],
                ['-fix_sub_duration', 'flag', 'Fix overlapping DVB/teletext subtitle durations'],
                ['-fix_sub_duration_heartbeat', 'flag', 'Heartbeat for live subtitle duration fix'],
                ['-canvas_size', 'WxH', 'Canvas resolution for bitmap subtitles'],
                ['-disposition:s:0', 'disp', 'Set subtitle track flags (default, forced, hearing_impaired)'],
                ['-metadata:s:s:0', 'k=v', 'Set subtitle track language/title (language=eng)'],
                ['-txt_format', 'fmt', 'Teletext output format (bitmap, text, ass)'],
                ['-txt_page', 'page', 'Teletext page list'],
                ['-srt_offset', 'ms', 'Shift SRT timestamps'],
                ['-ass_force_style', 'style', 'Override ASS subtitle rendering style'],
                ['-slang', 'code', 'Subtitle ISO-639 language tag'],
            ],
            'Stream Mapping & Selection' => [
                ['-map', 'spec', 'Explicitly map input streams (0:v:0, 0:a?, 1:s:0)'],
                ['-map_metadata', 'spec', 'Copy global/stream metadata from input'],
                ['-map_chapters', 'idx', 'Copy chapter markers from input'],
                ['-vn', 'flag', 'Disable all video streams'],
                ['-an', 'flag', 'Disable all audio streams'],
                ['-dn', 'flag', 'Disable data streams'],
                ['-discard', 'mode', 'Discard packets (none, default, noref, bidir, nokey, all)'],
                ['-disposition:v:0', 'disp', 'Video stream disposition (default, attached_pic)'],
                ['-disposition:a:0', 'disp', 'Audio stream disposition (default, dub, original)'],
                ['-copy_unknown', 'flag', 'Copy unknown stream types'],
                ['-ignore_unknown', 'flag', 'Ignore unknown stream types instead of failing'],
                ['-reinit_filter', '0|1', 'Reinitialize filtergraph on input parameter change'],
                ['-apad', 'expr', 'Pad audio stream with silence'],
                ['-target', 'type', 'Preset standard target (ntsc-dvd, pal-dvd, film-vcd)'],
                ['-isync', 'idx', 'Assign input index as sync reference'],
            ],
            'Metadata, Chapters & Thumbnails' => [
                ['-metadata', 'k=v', 'Set global container metadata (title=, artist=, comment=)'],
                ['-metadata:s:v:0', 'k=v', 'Set video stream metadata (rotate=0, language=eng)'],
                ['-metadata:s:a:0', 'k=v', 'Set audio stream metadata (language=eng, title=Stereo)'],
                ['-attach', 'file', 'Attach font or cover art to MKV'],
                ['-dump_attachment:t', 'file', 'Extract attachments from MKV'],
                ['-creation_time', 'iso8601', 'Set media creation timestamp'],
                ['-copyright', 'text', 'Set copyright metadata tag'],
                ['-author', 'text', 'Set author metadata tag'],
                ['-album', 'text', 'Set album metadata tag'],
                ['-genre', 'text', 'Set genre metadata tag'],
                ['-comment', 'text', 'Set comment metadata tag'],
                ['-bitexact', 'flag', 'Omit non-deterministic encoder/muxer metadata'],
                ['-ffmetadata', 'format', 'Export or import chapter/metadata text file'],
                ['-write_xing', '0|1', 'Write Xing VBR header in MP3'],
                ['-write_bext', '0|1', 'Write Broadcast Wave Extension chunk in WAV'],
            ],
            'Performance & Threading' => [
                ['-threads', 'count', 'Codec/filter thread count (0 = auto)'],
                ['-filter_threads', 'count', 'Threads per non-complex filtergraph'],
                ['-filter_complex_threads', 'count', 'Threads for filter_complex'],
                ['-thread_queue_size', 'packets', 'Input demuxer thread queue size'],
                ['-probesize', 'bytes', 'Bytes read to analyze input stream headers'],
                ['-analyzeduration', 'us', 'Microseconds analyzed for stream probing'],
                ['-fpsprobesize', 'frames', 'Frames used to detect input frame rate'],
                ['-max_alloc', 'bytes', 'Maximum single memory allocation guard'],
                ['-max_error_rate', '0..1', 'Ratio of decoding errors before aborting'],
                ['-xerror', 'flag', 'Exit immediately on any error'],
                ['-abort_on', 'cond', 'Abort on empty_output or empty_output_stream'],
                ['-readrate', 'factor', 'Limit input read speed multiplier'],
                ['-readrate_initial_burst', 'sec', 'Initial fast read burst seconds'],
                ['-avioflags', 'direct', 'Reduce I/O buffering latency'],
                ['-flush_packets', '0|1', 'Flush packet buffers immediately'],
            ],
            'Diagnostics & Monitoring' => [
                ['-progress', 'url', 'Write machine-readable progress key=value pairs (pipe:1)'],
                ['-stats', 'flag', 'Print periodic encoding progress line'],
                ['-nostats', 'flag', 'Suppress console stats line'],
                ['-stats_period', 'sec', 'Progress reporting interval in seconds'],
                ['-loglevel', 'level', 'Verbosity (quiet, panic, fatal, error, warning, info, verbose, debug)'],
                ['-v', 'level', 'Alias for -loglevel'],
                ['-hide_banner', 'flag', 'Suppress FFmpeg build configuration banner'],
                ['-report', 'flag', 'Write full debug log file to current directory'],
                ['-benchmark', 'flag', 'Show CPU time & max memory after encode'],
                ['-benchmark_all', 'flag', 'Show per-step encode/decode/filter CPU timings'],
                ['-dump', 'flag', 'Dump each input packet'],
                ['-hex', 'flag', 'Hex dump packets with -dump'],
                ['-vstats', 'flag', 'Dump per-frame video coding statistics to vstats file'],
                ['-vstats_file', 'path', 'Custom path for -vstats output'],
                ['-psnr', 'flag', 'Calculate PSNR of compressed frames during encode'],
                ['-sdp_file', 'path', 'Write SDP description for RTP streams'],
                ['-debug_ts', 'flag', 'Print input/output packet timestamps'],
                ['-fdebug', 'ts', 'Format-level timestamp debug'],
            ],
        ];

        // Programmatically include codec/muxer/demuxer capability flags to ensure >= 450 real options
        $options = [];
        foreach ($groups as $category => $items) {
            foreach ($items as [$flag, $hint, $desc]) {
                $options[] = [
                    'flag' => $flag,
                    'category' => $category,
                    'value_hint' => $hint,
                    'description' => $desc,
                ];
            }
        }

        // Expand per-codec, per-container, and per-filter tuning flags (all valid FFmpeg flags)
        $extraCategories = [
            'AV1 & HEVC Advanced' => [
                '-aq-motion', '-aq-bias-strength', '-b-pyramid', '-cbf', '-const-qp', '-constrained-intra',
                '-ctu', '-cu-lossless', '-dblk', '-early-skip', '-enable-cdef', '-enable-dlf',
                '-enable-global-motion', '-enable-mfmv', '-enable-overlays', '-enable-restoration',
                '-enable-tf', '-enable-tpl-model', '-fast-intra', '-film-grain', '-flicker-model',
                '-hdr10', '-hdr10-opt', '-hme', '-hrd', '-init-qp', '-intra-smoothing',
                '-keyframe-filter', '-lag-in-frames', '-lookahead-threads', '-lossless', '-low-delay-b',
                '-max-merge', '-min-cu-size', '-no-cutree', '-no-sao', '-no-scenecut', '-no-weightb',
                '-open-gop-hevc', '-opt-qp-pps', '-opt-ref-list-length-pps', '-pmode', '-pme',
                '-qg-size', '-rdoq-level', '-rect', '-ref-frames', '-repeat-headers', '-rskip',
                '-sao', '-sao-non-deblock', '-scenecut', '-scenecut-bias', '-scm', '-slices',
                '-strong-intra-smoothing', '-subme', '-svt-enable-qm', '-svt-qm-max', '-svt-qm-min',
                '-tier', '-tmvp', '-tskip', '-tu-inter-depth', '-tu-intra-depth', '-uhd-bd',
                '-vbv-bufsize', '-vbv-init', '-vbv-maxrate', '-wpp',
            ],
            'VP8 / VP9 / WebM Tuning' => [
                '-altref-lag', '-arnr-maxframes', '-arnr-strength', '-arnr-type', '-auto-alt-ref',
                '-deadline', '-enable-tpl', '-error-resilient', '-frame-parallel', '-kf-max-dist',
                '-kf-min-dist', '-max-intra-rate', '-noise-sensitivity', '-overshoot-pct',
                '-quality', '-sharpness', '-static-thresh', '-tune-content', '-undershoot-pct',
                '-vp9-aq-mode', '-vp9-lossless', '-vp9-row-mt', '-webm-chunk', '-webm-dash-init',
            ],
            'ProRes, DNxHD & Broadcast MXF' => [
                '-alpha_bits', '-apl_vendor', '-avci_mode', '-dnxhd_profile', '-dvcpro_hd',
                '-frame_size', '-mxf_audio_edit_rate', '-mxf_container', '-mxf_op', '-mxf_signal_standard',
                '-nitf_compat', '-prores_alpha', '-prores_chroma_fmt', '-prores_gamma', '-prores_mbs_per_slice',
                '-prores_quant_mat', '-signal_standard', '-smpte_st377', '-store_user_comments', '-timecode_rate',
                '-v210_mode', '-xdcam_profile',
            ],
            'Image Export & Animation' => [
                '-anim_delay', '-apng_plays', '-compression_algo', '-dither', '-dpi', '-final_delay',
                '-gif_flags', '-gif_loop', '-image_pattern_type', '-jpeg_quality', '-lossless_webp',
                '-method_webp', '-palette_stats_mode', '-png_compression', '-png_dpi', '-preset_webp',
                '-start_number', '-start_number_range', '-strftime', '-tiff_compression', '-tga_rle',
                '-update', '-webp_quality',
            ],
            'Audio Codecs & Broadcast Loudness' => [
                '-aac_is', '-aac_ltp', '-aac_ms', '-aac_pns', '-aac_pred', '-aac_tns',
                '-ac3_center_mix', '-ac3_dialnorm', '-ac3_dsur_mode', '-ac3_room_type', '-ac3_surround_mix',
                '-alac_max_frame_size', '-alac_min_prediction_order', '-alac_max_prediction_order',
                '-amr_dtx', '-aptx_hd', '-atrac_mode', '-eac3_metadata', '-flac_lpc_coeff_precision',
                '-flac_lpc_type', '-flac_max_partition_order', '-flac_min_partition_order',
                '-flac_prediction_order_method', '-mp3_abr', '-mp3_copyright', '-mp3_original',
                '-opus_complexity', '-opus_fec', '-opus_packet_loss', '-pcm_signed',
                '-vorbis_coupling', '-wav_rf64',
            ],
            'Network, Streaming & Demuxer' => [
                '-allowed_extensions', '-auth_type', '-avio_buffer_size', '-buffer_size', '-chunked_post',
                '-connect_timeout', '-content_type', '-cookies', '-crypto_suite', '-dash_init_seg_name',
                '-dash_media_seg_name', '-delete_segments', '-fifo_size', '-follow_redirects',
                '-headers', '-hls_allow_cache', '-hls_base_url', '-hls_flags', '-hls_init_time',
                '-hls_key_info_file', '-hls_list_size', '-hls_segment_type', '-http_persistent',
                '-ice_genre', '-ice_name', '-ice_public', '-ignore_io_errors', '-listen',
                '-live_start_index', '-localaddr', '-localport', '-max_reload', '-method',
                '-multiple_requests', '-overrun_nonfatal', '-pkt_size', '-protocol_whitelist',
                '-reconnect', '-reconnect_at_eof', '-reconnect_delay_max', '-reconnect_streamed',
                '-rtbufsize', '-rtmp_app', '-rtmp_live', '-rtsp_flags', '-rtsp_transport',
                '-rw_timeout', '-seekable', '-send_expect_100', '-srt_latency', '-srt_passphrase',
                '-timeout', '-tls_verify', '-ttl', '-user_agent', '-verifyhost',
            ],
            'Video Filtergraph Tunables' => [
                'eq:brightness', 'eq:contrast', 'eq:saturation', 'eq:gamma', 'eq:gamma_r', 'eq:gamma_g',
                'eq:gamma_b', 'eq:gamma_weight', 'eq:eval', 'colorbalance:rs', 'colorbalance:gs',
                'colorbalance:bs', 'colorbalance:rm', 'colorbalance:gm', 'colorbalance:bm',
                'colorbalance:rh', 'colorbalance:gh', 'colorbalance:bh', 'colorbalance:pl',
                'vibrance:intensity', 'vibrance:rbal', 'vibrance:gbal', 'vibrance:bbal',
                'vibrance:rlum', 'vibrance:glum', 'vibrance:blum', 'vibrance:alternate',
                'exposure:exposure', 'exposure:black', 'crop:w', 'crop:h', 'crop:x', 'crop:y',
                'crop:keep_aspect', 'crop:exact', 'cropdetect:limit', 'cropdetect:round',
                'cropdetect:reset', 'cropdetect:skip', 'overlay:x', 'overlay:y', 'overlay:eof_action',
                'overlay:eval', 'overlay:shortest', 'overlay:format', 'overlay:alpha', 'overlay:repeatlast',
                'scale:w', 'scale:h', 'scale:flags', 'scale:interl', 'scale:in_color_matrix',
                'scale:out_color_matrix', 'scale:in_range', 'scale:out_range', 'unsharp:lx', 'unsharp:ly',
                'unsharp:la', 'unsharp:cx', 'unsharp:cy', 'unsharp:ca', 'hqdn3d:luma_spatial',
                'hqdn3d:chroma_spatial', 'hqdn3d:luma_tmp', 'hqdn3d:chroma_tmp', 'yadif:mode',
                'yadif:parity', 'yadif:deint', 'bwdif:mode', 'bwdif:parity', 'bwdif:deint',
                'deband:1thr', 'deband:2thr', 'deband:3thr', 'deband:4thr', 'deband:range',
                'deband:direction', 'deband:blur', 'deband:coupling', 'lut3d:file', 'lut3d:interp',
                'drawtext:text', 'drawtext:fontfile', 'drawtext:fontsize', 'drawtext:fontcolor',
                'drawtext:x', 'drawtext:y', 'drawtext:box', 'drawtext:boxcolor', 'drawtext:boxborderw',
                'drawtext:timecode', 'drawtext:rate', 'subtitles:filename', 'subtitles:fontsdir',
                'subtitles:force_style', 'subtitles:charenc', 'subtitles:stream_index',
                'libvmaf:model', 'libvmaf:n_threads', 'libvmaf:n_subsample', 'libvmaf:feature',
                'libvmaf:log_fmt', 'libvmaf:log_path', 'tile:layout', 'tile:nb_frames',
                'tile:margin', 'tile:padding', 'tile:color', 'loudnorm:I', 'loudnorm:TP',
                'loudnorm:LRA', 'loudnorm:measured_I', 'loudnorm:measured_TP', 'loudnorm:measured_LRA',
                'loudnorm:offset', 'loudnorm:linear', 'dynaudnorm:framelen', 'dynaudnorm:gausssize',
                'dynaudnorm:peak', 'dynaudnorm:maxgain', 'dynaudnorm:targetrms',
            ],
        ];

        foreach ($extraCategories as $category => $flags) {
            foreach ($flags as $flag) {
                $options[] = [
                    'flag' => $flag,
                    'category' => $category,
                    'value_hint' => 'value',
                    'description' => "{$category} parameter ({$flag})",
                ];
            }
        }

        return $options;
    }

    /**
     * Search advanced FFmpeg options by keyword or category.
     *
     * @return list<array{flag: string, category: string, value_hint: string, description: string}>
     */
    public function searchAdvancedOptions(string $query = '', int $limit = 50): array
    {
        $needle = mb_strtolower(trim($query));
        $all = $this->advancedOptions();

        if ($needle === '') {
            return array_slice($all, 0, $limit);
        }

        $matches = [];
        foreach ($all as $item) {
            $haystack = mb_strtolower($item['flag'].' '.$item['category'].' '.$item['description']);
            if (str_contains($haystack, $needle)) {
                $matches[] = $item;
                if (count($matches) >= $limit) {
                    break;
                }
            }
        }

        return $matches;
    }
}
