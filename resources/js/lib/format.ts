/**
 * Number formatting shared by the public pages and the frame renderer.
 * Unknown values (null/undefined) are shown as "—", never 0.
 */
export const UNKNOWN = '—';

export function formatNumber(value: number | null | undefined): string {
    if (value === null || value === undefined || Number.isNaN(value)) return UNKNOWN;
    return new Intl.NumberFormat('en-US').format(value);
}

/** 1.2K / 3.4M / 1.1B (one decimal, trailing ".0" dropped). */
export function formatCompact(value: number | null | undefined): string {
    if (value === null || value === undefined || Number.isNaN(value)) return UNKNOWN;
    const abs = Math.abs(value);
    const units: [number, string][] = [
        [1e9, 'B'],
        [1e6, 'M'],
        [1e3, 'K'],
    ];
    for (const [size, suffix] of units) {
        if (abs >= size) {
            // Truncate (not round) so 999,950 never displays as "1000K".
            const scaled = Math.floor((value / size) * 10) / 10;
            return `${scaled.toFixed(1).replace(/\.0$/, '')}${suffix}`;
        }
    }
    return String(value);
}

export function formatDecimal(value: number | null | undefined, digits = 1): string {
    if (value === null || value === undefined || Number.isNaN(value)) return UNKNOWN;
    return new Intl.NumberFormat('en-US', { maximumFractionDigits: digits }).format(value);
}

export function relativeTime(iso: string | null | undefined, locale: string, never = UNKNOWN): string {
    if (!iso) return never;
    const diffSec = Math.round((new Date(iso).getTime() - Date.now()) / 1000);
    const rtf = new Intl.RelativeTimeFormat(locale === 'bn' ? 'bn' : 'en', { numeric: 'auto' });
    const steps: [number, Intl.RelativeTimeFormatUnit][] = [
        [60, 'second'],
        [60, 'minute'],
        [24, 'hour'],
        [30, 'day'],
        [12, 'month'],
        [Infinity, 'year'],
    ];
    let value = diffSec;
    for (const [size, unit] of steps) {
        if (Math.abs(value) < size) return rtf.format(value, unit);
        value = Math.round(value / size);
    }
    return rtf.format(value, 'year');
}
