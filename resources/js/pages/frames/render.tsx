import { loadFrameFonts, loadImage } from '@/frame/fonts';
import FrameStage, { type FrameStageHandle } from '@/frame/frame-stage';
import type { EditorFrame, FrameStats, PlacedPhoto } from '@/frame/types';
import { Head } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

interface Props {
    frame: EditorFrame;
    stats: FrameStats;
    /** Photo URLs per slot; missing slots get a generated placeholder. */
    photos: (string | null)[];
}

declare global {
    interface Window {
        /** Resolves to a PNG data URL at native size (read by the Browsershot renderer). */
        __frameRender?: Promise<string>;
    }
}

/** Placeholder photo: soft gradient + slot number. */
function placeholder(i: number, w: number, h: number): Promise<HTMLImageElement> {
    const c = document.createElement('canvas');
    c.width = w;
    c.height = h;
    const ctx = c.getContext('2d')!;
    const hues = [160, 200, 30, 280, 340, 100];
    const g = ctx.createLinearGradient(0, 0, w, h);
    g.addColorStop(0, `hsl(${hues[i % hues.length]} 55% 70%)`);
    g.addColorStop(1, `hsl(${hues[i % hues.length]} 60% 40%)`);
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, w, h);
    ctx.fillStyle = 'rgba(255,255,255,0.85)';
    ctx.font = `700 ${Math.round(Math.min(w, h) / 4)}px Poppins, sans-serif`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(`📷 ${i + 1}`, w / 2, h / 2);
    return loadImage(c.toDataURL('image/png'));
}

/**
 * Bare page that renders a frame with the exact same component as the editor.
 * Used by the admin live preview (iframe) and the optional server-side
 * Browsershot renderer.
 */
export default function FrameRender({ frame, stats, photos }: Props) {
    const stageRef = useRef<FrameStageHandle>(null);
    const [state, setState] = useState<{ bg: HTMLImageElement; overlay: HTMLImageElement | null; photos: PlacedPhoto[] } | null>(null);
    const [width, setWidth] = useState(() => (typeof window === 'undefined' ? frame.size_w : window.innerWidth));
    const resolveRef = useRef<(v: string) => void>(undefined);

    if (typeof window !== 'undefined' && !window.__frameRender) {
        window.__frameRender = new Promise((resolve) => (resolveRef.current = resolve));
    }

    useEffect(() => {
        const onResize = () => setWidth(window.innerWidth);
        window.addEventListener('resize', onResize);
        return () => window.removeEventListener('resize', onResize);
    }, []);

    useEffect(() => {
        (async () => {
            await loadFrameFonts(frame.config);
            const [bg, overlay] = await Promise.all([loadImage(frame.background_url), frame.overlay_url ? loadImage(frame.overlay_url) : null]);
            const placed = await Promise.all(
                frame.config.photo_slots.map(async (slot, i) => ({
                    url: photos[i] ?? '',
                    image: photos[i] ? await loadImage(photos[i]!) : await placeholder(i, slot.w * 2, slot.h * 2),
                    dx: 0,
                    dy: 0,
                    zoom: 1,
                })),
            );
            setState({ bg, overlay, photos: placed });
        })();
    }, [frame, photos]);

    useEffect(() => {
        if (!state) return;
        requestAnimationFrame(() => {
            const url = stageRef.current?.exportDataUrl();
            if (url) resolveRef.current?.(url);
            document.body.dataset.rendered = '1';
        });
    }, [state]);

    const scale = Math.min(1, width / frame.size_w);

    return (
        <>
            <Head title={`Preview — ${frame.title}`} />
            <div style={{ margin: 0, background: '#f4f4f5', minHeight: '100vh' }}>
                {state && (
                    <FrameStage
                        ref={stageRef}
                        frame={frame}
                        background={state.bg}
                        overlay={state.overlay}
                        stats={stats}
                        photos={state.photos}
                        scale={scale}
                    />
                )}
            </div>
        </>
    );
}
