import type { FrameConfig } from './types';

const loaded = new Map<string, Promise<void>>();

/**
 * Load every configured font before the first draw (canvas text does not
 * re-render when a web font arrives later).
 */
export function loadFrameFonts(config: FrameConfig): Promise<void> {
    return Promise.all(
        config.fonts.map((font) => {
            const weight = String(font.weight ?? 700);
            const key = `${font.family}|${weight}|${font.src}`;
            if (!loaded.has(key)) {
                const face = new FontFace(font.family, `url(${JSON.stringify(font.src)})`, { weight, display: 'block' });
                loaded.set(
                    key,
                    face
                        .load()
                        .then((f) => {
                            document.fonts.add(f);
                        })
                        .then(() => document.fonts.load(`${weight} 40px "${font.family}"`))
                        .then(() => undefined)
                        .catch(() => undefined), // fall back to system fonts rather than block forever
                );
            }
            return loaded.get(key)!;
        }),
    ).then(() => undefined);
}

export function loadImage(url: string): Promise<HTMLImageElement> {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.decoding = 'async';
        img.onload = () => resolve(img);
        img.onerror = () => reject(new Error(`Could not load ${url}`));
        img.src = url;
    });
}
