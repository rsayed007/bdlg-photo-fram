import AddProfileForm from '@/components/add-profile-form';
import { GuideAvatar, LevelBadge, Num, StatusBadge } from '@/components/guide-bits';
import { Button } from '@/components/ui/button';
import { loadFrameFonts, loadImage } from '@/frame/fonts';
import FrameStage, { type FrameStageHandle } from '@/frame/frame-stage';
import { clampOffset } from '@/frame/layout';
import type { EditorFrame, FrameStats, PlacedPhoto } from '@/frame/types';
import PublicLayout from '@/layouts/public-layout';
import { getJson, HttpError, postForm, postJson } from '@/lib/http';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { type Guide } from '@/types';
import { router } from '@inertiajs/react';
import { Check, ImagePlus, LoaderCircle, RefreshCw, Sparkles, ZoomIn } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

interface Props {
    frame: EditorFrame;
    guides: Guide[];
    staleAfterHours: number;
}

type Step = 0 | 1 | 2 | 3;
const MAX_BYTES = 10 * 1024 * 1024;
const ACCEPT = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];

interface SlotUpload {
    progress: number | null;
    error: string | null;
}

export default function FrameCreate({ frame, guides, staleAfterHours }: Props) {
    const t = useT();
    const slots = frame.config.photo_slots;
    const [step, setStep] = useState<Step>(guides.length === 1 ? 1 : 0);
    const [guide, setGuide] = useState<Guide | null>(guides.length === 1 ? guides[0] : null);
    const [photos, setPhotos] = useState<(PlacedPhoto | null)[]>(() => slots.map(() => null));
    const [uploads, setUploads] = useState<SlotUpload[]>(() => slots.map(() => ({ progress: null, error: null })));
    const [selected, setSelected] = useState<number | null>(null);
    const [assets, setAssets] = useState<{ bg: HTMLImageElement | null; overlay: HTMLImageElement | null; ready: boolean }>({
        bg: null,
        overlay: null,
        ready: false,
    });
    const [refreshing, setRefreshing] = useState<'idle' | 'updating' | 'updated' | 'stale'>('idle');
    const [generating, setGenerating] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const stageRef = useRef<FrameStageHandle>(null);
    const containerRef = useRef<HTMLDivElement>(null);
    const [containerWidth, setContainerWidth] = useState(360);

    // Keep the guide list in sync after the inline add-profile form reloads props.
    useEffect(() => {
        if (!guide && guides.length > 0) {
            setGuide(guides[0]);
            ensureFresh(guides[0]);
            setStep(1);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [guides]);

    // Fonts + artwork must be ready before the first draw.
    useEffect(() => {
        let cancelled = false;
        Promise.all([
            loadFrameFonts(frame.config),
            loadImage(frame.background_url),
            frame.overlay_url ? loadImage(frame.overlay_url) : Promise.resolve(null),
        ])
            .then(([, bg, overlay]) => !cancelled && setAssets({ bg, overlay, ready: true }))
            .catch(() => !cancelled && setError('Could not load the frame artwork.'));
        return () => {
            cancelled = true;
        };
    }, [frame]);

    useEffect(() => {
        const el = containerRef.current;
        if (!el) return;
        const ro = new ResizeObserver(([entry]) => setContainerWidth(entry.contentRect.width));
        ro.observe(el);
        return () => ro.disconnect();
    }, [step]);

    const scale = Math.min(containerWidth, 560) / frame.size_w;

    const stats: FrameStats = useMemo(
        () => ({
            name: guide?.name ?? null,
            level: guide?.level ?? null,
            points: guide?.points ?? null,
            reviews: guide?.reviews ?? null,
            photos: guide?.photos ?? null,
            videos: guide?.videos ?? null,
            photo_views: guide?.photo_views ?? null,
            places_added: guide?.places_added ?? null,
            roads_added: guide?.roads_added ?? null,
            views_per_photo: guide?.views_per_photo ?? null,
            profile_url: guide?.profile_url ?? null,
        }),
        [guide],
    );

    /**
     * Freshness rule: stats older than 24 h → queue a priority sync and poll
     * every 5 s for up to 2 minutes, then fall back to the stored stats.
     */
    const ensureFresh = useCallback(
        async (g: Guide) => {
            const stale = !g.last_synced_at || Date.now() - new Date(g.last_synced_at).getTime() > staleAfterHours * 3600_000;
            if (!stale || g.status === 'restricted') return;
            setRefreshing('updating');
            try {
                await postJson(route('guides.refresh', g.contrib_id));
            } catch {
                setRefreshing('stale');
                return;
            }
            const started = Date.now();
            const poll = async () => {
                try {
                    const res = await getJson<{ guide: Guide }>(route('guides.status', g.contrib_id));
                    if (res.guide.last_synced_at && res.guide.last_synced_at !== g.last_synced_at) {
                        setGuide(res.guide);
                        setRefreshing('updated');
                        return;
                    }
                    if (res.guide.status === 'restricted' && g.status !== 'restricted') {
                        setGuide(res.guide);
                        setRefreshing('stale');
                        return;
                    }
                } catch {
                    /* keep trying */
                }
                if (Date.now() - started < 120_000) setTimeout(poll, 5000);
                else setRefreshing('stale');
            };
            setTimeout(poll, 5000);
        },
        [staleAfterHours],
    );

    const chooseGuide = (g: Guide) => {
        setGuide(g);
        setRefreshing('idle');
        ensureFresh(g);
        setStep(1);
    };

    useEffect(() => {
        if (guides.length === 1 && guide) ensureFresh(guide);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const setUpload = (i: number, patch: Partial<SlotUpload>) => setUploads((u) => u.map((x, j) => (j === i ? { ...x, ...patch } : x)));

    const onFile = async (i: number, file: File | undefined) => {
        if (!file) return;
        const isHeicName = /\.(heic|heif)$/i.test(file.name);
        if (!ACCEPT.includes(file.type) && !isHeicName) {
            setUpload(i, { error: t('editor.photos.bad_type') });
            return;
        }
        if (file.size > MAX_BYTES) {
            setUpload(i, { error: t('editor.photos.too_big') });
            return;
        }
        setUpload(i, { progress: 0, error: null });
        const data = new FormData();
        data.append('photo', file);
        try {
            const res = await postForm<{ path: string; url: string }>(route('uploads.store'), data, (pct) => setUpload(i, { progress: pct }));
            const image = await loadImage(res.url);
            setPhotos((p) => p.map((x, j) => (j === i ? { url: res.url, path: res.path, image, dx: 0, dy: 0, zoom: 1 } : x)));
            setUpload(i, { progress: null });
            setSelected(i);
        } catch (e) {
            setUpload(i, { progress: null, error: e instanceof HttpError && e.message ? e.message : t('editor.photos.failed') });
        }
    };

    const missingRequired = slots.some((s, i) => s.required && !photos[i]);
    const anyUploading = uploads.some((u) => u.progress !== null);

    const generate = async () => {
        if (!guide || !stageRef.current) return;
        if (missingRequired) {
            setError(t('editor.missing_required'));
            return;
        }
        setGenerating(true);
        setError(null);
        try {
            setSelected(null);
            await new Promise((r) => requestAnimationFrame(() => r(null)));
            const blob = await stageRef.current.exportBlob();
            const data = new FormData();
            data.append('image', blob, `${frame.slug}.png`);
            data.append('local_guide_id', String(guide.id));
            photos.forEach((p) => data.append('sources[]', p?.path ?? ''));
            const res = await postForm<{ share_url: string }>(route('generations.store', frame.slug), data);
            router.visit(res.share_url);
        } catch (e) {
            setError(e instanceof HttpError && e.message ? e.message : 'Something went wrong.');
            setGenerating(false);
        }
    };

    const steps = [t('editor.step.profile'), t('editor.step.photos'), t('editor.step.adjust'), t('editor.step.done')];

    return (
        <PublicLayout title={frame.title} className="max-w-3xl">
            <div className="mb-4">
                {frame.event && <div className="text-muted-foreground text-sm">{frame.event}</div>}
                <h1 className="text-xl font-bold sm:text-2xl">{frame.title}</h1>
            </div>

            {/* Stepper */}
            <ol className="mb-5 grid grid-cols-4 gap-1 text-center text-xs sm:text-sm">
                {steps.map((label, i) => (
                    <li key={label}>
                        <button
                            type="button"
                            disabled={i > 0 && !guide}
                            onClick={() => setStep(i as Step)}
                            className={cn(
                                'w-full border-b-2 px-1 pb-2 font-medium transition-colors disabled:opacity-40',
                                step === i ? 'border-primary text-foreground' : 'text-muted-foreground border-transparent',
                            )}
                        >
                            <span
                                className={cn(
                                    'mr-1 inline-flex size-5 items-center justify-center rounded-full text-[11px]',
                                    step > i ? 'bg-primary text-primary-foreground' : 'bg-muted',
                                )}
                            >
                                {step > i ? <Check className="size-3" /> : i + 1}
                            </span>
                            <span className="hidden sm:inline">{label}</span>
                        </button>
                    </li>
                ))}
            </ol>

            {refreshing !== 'idle' && (
                <div
                    className={cn(
                        'mb-4 flex items-center gap-2 rounded-lg border p-3 text-sm',
                        refreshing === 'updating' && 'border-sky-300 bg-sky-50 text-sky-900 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-100',
                        refreshing === 'updated' &&
                            'border-emerald-300 bg-emerald-50 text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-100',
                        refreshing === 'stale' && 'bg-muted text-muted-foreground',
                    )}
                    role="status"
                >
                    {refreshing === 'updating' ? <RefreshCw className="size-4 animate-spin" /> : <Check className="size-4" />}
                    {refreshing === 'updating'
                        ? t('editor.updating_stats')
                        : refreshing === 'updated'
                          ? t('editor.stats_updated')
                          : t('editor.stats_stale')}
                </div>
            )}

            {/* Step 1: profile */}
            {step === 0 && (
                <section>
                    <h2 className="mb-3 font-semibold">{t('editor.profile.title')}</h2>
                    {guides.length > 0 ? (
                        <ul className="space-y-2">
                            {guides.map((g) => (
                                <li key={g.id}>
                                    <button
                                        type="button"
                                        onClick={() => chooseGuide(g)}
                                        className={cn(
                                            'bg-card hover:border-primary flex w-full items-center gap-3 rounded-xl border p-3 text-left',
                                            guide?.id === g.id && 'border-primary ring-primary/30 ring-2',
                                        )}
                                    >
                                        <GuideAvatar name={g.name} url={g.avatar_url} className="size-10" />
                                        <div className="min-w-0 flex-1">
                                            <div className="truncate font-medium">{g.name ?? g.contrib_id}</div>
                                            <div className="text-muted-foreground flex flex-wrap items-center gap-2 text-xs">
                                                <LevelBadge level={g.level} />
                                                <span>
                                                    <Num value={g.points} compact /> {t('common.points')}
                                                </span>
                                                {g.status !== 'active' && <StatusBadge status={g.status} />}
                                            </div>
                                        </div>
                                        <span className="text-primary text-sm font-medium">{t('editor.profile.choose')}</span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <div className="bg-card rounded-xl border p-4">
                            <p className="mb-3 text-sm font-medium">{t('editor.profile.add')}</p>
                            <AddProfileForm redirect="back" compact />
                        </div>
                    )}
                </section>
            )}

            {/* Step 2: photos */}
            {step === 1 && (
                <section>
                    <h2 className="mb-3 font-semibold">{t('editor.photos.title')}</h2>
                    <ul className="space-y-3">
                        {slots.map((slot, i) => (
                            <li key={i} className="bg-card flex items-center gap-3 rounded-xl border p-3">
                                <div className="bg-muted flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-lg">
                                    {photos[i] ? (
                                        <img src={photos[i]!.url} alt="" className="size-full object-cover" />
                                    ) : (
                                        <ImagePlus className="text-muted-foreground size-6" />
                                    )}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="text-sm font-medium">
                                        {t('editor.photos.slot', { n: i + 1 })}{' '}
                                        <span className="text-muted-foreground font-normal">
                                            ({slot.required ? t('editor.photos.required') : t('editor.photos.optional')})
                                        </span>
                                    </div>
                                    {uploads[i].progress !== null && (
                                        <div className="mt-1">
                                            <div className="bg-muted h-1.5 overflow-hidden rounded-full">
                                                <div className="bg-primary h-full transition-all" style={{ width: `${uploads[i].progress}%` }} />
                                            </div>
                                            <div className="text-muted-foreground mt-1 text-xs">
                                                {t('editor.photos.uploading', { pct: uploads[i].progress ?? 0 })}
                                            </div>
                                        </div>
                                    )}
                                    {uploads[i].error && <div className="mt-1 text-xs text-red-600">{uploads[i].error}</div>}
                                </div>
                                <label className="border-input hover:bg-accent cursor-pointer rounded-md border px-3 py-2 text-sm font-medium">
                                    {photos[i] ? t('editor.photos.replace') : t('editor.photos.choose')}
                                    <input
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif"
                                        className="sr-only"
                                        disabled={uploads[i].progress !== null}
                                        onChange={(e) => {
                                            onFile(i, e.target.files?.[0]);
                                            e.target.value = '';
                                        }}
                                    />
                                </label>
                            </li>
                        ))}
                    </ul>
                    <div className="mt-5 flex justify-between gap-2">
                        <Button variant="ghost" onClick={() => setStep(0)}>
                            {t('common.back')}
                        </Button>
                        <Button onClick={() => setStep(2)} disabled={missingRequired || anyUploading}>
                            {t('common.continue')}
                        </Button>
                    </div>
                    {missingRequired && <p className="text-muted-foreground mt-2 text-right text-xs">{t('editor.missing_required')}</p>}
                </section>
            )}

            {/* Step 3 + 4: adjust & generate (canvas stays mounted for both) */}
            {step >= 2 && (
                <section>
                    <p className="text-muted-foreground mb-3 text-sm">{t('editor.adjust.hint')}</p>
                    <div ref={containerRef} className="mx-auto w-full max-w-[560px]">
                        {!assets.ready ? (
                            <div
                                className="bg-muted flex items-center justify-center rounded-lg"
                                style={{ aspectRatio: `${frame.size_w} / ${frame.size_h}` }}
                            >
                                <LoaderCircle className="text-muted-foreground size-6 animate-spin" />
                                <span className="text-muted-foreground ml-2 text-sm">{t('editor.fonts_loading')}</span>
                            </div>
                        ) : (
                            <div className="overflow-hidden rounded-lg border shadow-sm">
                                <FrameStage
                                    ref={stageRef}
                                    frame={frame}
                                    background={assets.bg}
                                    overlay={assets.overlay}
                                    stats={stats}
                                    photos={photos}
                                    scale={scale}
                                    interactive
                                    showPlaceholders
                                    selected={selected}
                                    onSelect={setSelected}
                                    onPhotoChange={(i, p) => setPhotos((ps) => ps.map((x, j) => (j === i ? p : x)))}
                                />
                            </div>
                        )}
                    </div>

                    {/* Zoom control for the selected photo */}
                    <div className="mx-auto mt-4 max-w-[560px]">
                        {selected !== null && photos[selected] ? (
                            <label className="flex items-center gap-3 text-sm">
                                <ZoomIn className="size-4 shrink-0" />
                                <span className="shrink-0">
                                    {t('editor.adjust.zoom')} · {t('editor.photos.slot', { n: selected + 1 })}
                                </span>
                                <input
                                    type="range"
                                    min={1}
                                    max={4}
                                    step={0.01}
                                    value={photos[selected]!.zoom}
                                    onChange={(e) => {
                                        const zoom = Number(e.target.value);
                                        setPhotos((ps) =>
                                            ps.map((p, j) => {
                                                if (j !== selected || !p) return p;
                                                const next = { ...p, zoom };
                                                return { ...next, ...clampOffset(slots[j], next) };
                                            }),
                                        );
                                    }}
                                    className="accent-primary w-full"
                                />
                            </label>
                        ) : (
                            <p className="text-muted-foreground text-center text-sm">{t('editor.adjust.select')}</p>
                        )}
                    </div>

                    {error && <p className="mt-3 text-center text-sm text-red-600">{error}</p>}

                    <div className="mx-auto mt-5 flex max-w-[560px] justify-between gap-2">
                        <Button variant="ghost" onClick={() => setStep(1)} disabled={generating}>
                            {t('common.back')}
                        </Button>
                        <Button onClick={generate} disabled={generating || !assets.ready || missingRequired} size="lg">
                            {generating ? <LoaderCircle className="size-4 animate-spin" /> : <Sparkles className="size-4" />}
                            {generating ? t('editor.generating') : t('editor.generate')}
                        </Button>
                    </div>
                </section>
            )}
        </PublicLayout>
    );
}
