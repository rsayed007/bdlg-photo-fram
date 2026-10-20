import { formatCompact } from '@/lib/format';
import type { FrameConfig, FrameStats, PhotoSlot, PlacedPhoto, TextSlot } from './types';

/**
 * Pure layout helpers shared by the editor and the server-side renderer.
 */

const STAT_FIELDS = [
    'name',
    'level',
    'points',
    'reviews',
    'photos',
    'videos',
    'photo_views',
    'places_added',
    'roads_added',
    'views_per_photo',
] as const;

function formatValue(value: number | string | null | undefined, format: TextSlot['format']): string | null {
    if (value === null || value === undefined || value === '') return null;
    if (typeof value === 'string') return value;
    if (format === 'compact') return formatCompact(value);
    if (format === 'raw') return String(value);
    return new Intl.NumberFormat('en-US', { maximumFractionDigits: 1 }).format(value);
}

/**
 * The text a slot prints, or null when it must be hidden. A slot is hidden
 * when any value it references is unknown — we never print "null", "0" or "-"
 * in place of a missing stat (spec §7).
 */
export function resolveText(slot: TextSlot, stats: FrameStats): string | null {
    if (slot.field === 'static') return slot.template?.trim() || null;

    const template = slot.template ?? `{${slot.field}}`;
    let missing = false;
    const text = template.replace(/\{([a-z_]+)\}/g, (_, key: string) => {
        if (!(STAT_FIELDS as readonly string[]).includes(key)) return '';
        const formatted = formatValue(stats[key as keyof FrameStats] as number | string | null, key === 'name' ? 'raw' : slot.format);
        if (formatted === null) missing = true;
        return formatted ?? '';
    });

    return missing ? null : text.trim() || null;
}

export function fontStack(config: FrameConfig, slot: TextSlot): string {
    const primary = slot.font ?? config.fonts[0]?.family ?? 'sans-serif';
    // Fallback to every declared font so Bangla glyphs in a Latin font slot still render.
    const rest = config.fonts.map((f) => f.family).filter((f) => f !== primary);
    return [primary, ...rest, 'sans-serif'].map((f) => (f.includes(' ') && !f.startsWith('"') ? `"${f}"` : f)).join(', ');
}

export function fontWeight(config: FrameConfig, slot: TextSlot): number {
    return slot.weight ?? config.fonts.find((f) => f.family === (slot.font ?? config.fonts[0]?.family))?.weight ?? 700;
}

/** Width available to a slot (explicit max_width or the space to the frame edge). */
export function slotMaxWidth(slot: TextSlot, frameWidth: number): number {
    if (slot.max_width) return slot.max_width;
    const align = slot.align ?? 'left';
    if (align === 'center') return 2 * Math.min(slot.x, frameWidth - slot.x);
    if (align === 'right') return slot.x;
    return frameWidth - slot.x;
}

let measureCtx: CanvasRenderingContext2D | null = null;
function ctx(): CanvasRenderingContext2D {
    measureCtx ??= document.createElement('canvas').getContext('2d')!;
    return measureCtx;
}

export interface FittedText {
    text: string;
    size: number;
    /** Left x of the text box (Konva.Text with width=boxWidth and align). */
    boxX: number;
    boxWidth: number;
}

/**
 * Shrink the font until the text fits max_width (down to 60% of the
 * configured size), then truncate with an ellipsis.
 */
export function fitText(text: string, slot: TextSlot, config: FrameConfig, frameWidth: number): FittedText {
    const maxWidth = slotMaxWidth(slot, frameWidth);
    const family = fontStack(config, slot);
    const weight = fontWeight(config, slot);
    const c = ctx();
    const measure = (t: string, size: number) => {
        c.font = `${weight} ${size}px ${family}`;
        return c.measureText(t).width;
    };

    const minSize = slot.size * 0.6;
    let size = slot.size;
    while (measure(text, size) > maxWidth && size > minSize) {
        size = Math.max(minSize, size * 0.97);
    }

    let out = text;
    if (measure(out, size) > maxWidth) {
        const chars = Array.from(text); // keep surrogate pairs / Bangla clusters mostly intact
        while (chars.length > 1 && measure(chars.join('') + '…', size) > maxWidth) chars.pop();
        out = chars.join('').trimEnd() + '…';
    }

    const align = slot.align ?? 'left';
    const boxX = align === 'center' ? slot.x - maxWidth / 2 : align === 'right' ? slot.x - maxWidth : slot.x;

    return { text: out, size, boxX, boxWidth: maxWidth };
}

/** Image size that covers the slot at the given zoom. */
export function coverSize(slot: PhotoSlot, img: { width: number; height: number }, zoom: number) {
    const base = Math.max(slot.w / img.width, slot.h / img.height);
    return { w: img.width * base * zoom, h: img.height * base * zoom };
}

/** Keep the photo covering the slot: clamp the centre offset. */
export function clampOffset(slot: PhotoSlot, photo: Pick<PlacedPhoto, 'image' | 'zoom' | 'dx' | 'dy'>) {
    const { w, h } = coverSize(slot, { width: photo.image.naturalWidth, height: photo.image.naturalHeight }, photo.zoom);
    const maxX = Math.max(0, (w - slot.w) / 2);
    const maxY = Math.max(0, (h - slot.h) / 2);
    return { dx: Math.min(maxX, Math.max(-maxX, photo.dx)), dy: Math.min(maxY, Math.max(-maxY, photo.dy)) };
}
