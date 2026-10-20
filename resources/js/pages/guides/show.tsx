import { filterRange, MetricChart, RangeToggle, type Range } from '@/components/growth-chart';
import { GuideAvatar, LevelBadge, StatCard, StatusBadge } from '@/components/guide-bits';
import { Button } from '@/components/ui/button';
import PublicLayout from '@/layouts/public-layout';
import { formatDecimal, relativeTime } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { type GenerationCard, type Guide, type HistoryPoint, type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, ExternalLink, GitCompare, LoaderCircle, Lock } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

interface Props {
    guide: Guide & { created_at: string; last_attempt_at: string | null };
    ranks: Record<'points' | 'reviews' | 'photos' | 'photo_views', { rank: number | null; total: number }> | null;
    history: HistoryPoint[];
    generations: GenerationCard[];
}

export default function GuideShow({ guide, ranks, history, generations }: Props) {
    const t = useT();
    const locale = useLocale();
    const { flash } = usePage<SharedData>().props;
    const [range, setRange] = useState<Range>('30d');

    // Pending profiles: poll until the first sync lands (5 s, max 2 min).
    useEffect(() => {
        if (guide.status !== 'pending') return;
        let tries = 0;
        const id = setInterval(async () => {
            tries++;
            try {
                const res = await fetch(route('guides.status', guide.contrib_id), { headers: { Accept: 'application/json' } });
                const json = await res.json();
                if (json.guide.status !== 'pending') {
                    clearInterval(id);
                    router.reload();
                }
            } catch {
                /* keep polling */
            }
            if (tries >= 24) clearInterval(id);
        }, 5000);
        return () => clearInterval(id);
    }, [guide.status, guide.contrib_id]);

    const rows = useMemo(() => filterRange(history, range), [history, range]);
    const rankSub = (key: keyof NonNullable<Props['ranks']>) => {
        const r = ranks?.[key];
        return r?.rank ? t('guide.rank_of', { rank: r.rank, total: r.total }) : null;
    };

    const metrics: { key: keyof HistoryPoint; label: string }[] = [
        { key: 'points', label: t('common.points') },
        { key: 'reviews', label: t('common.reviews') },
        { key: 'photos', label: t('common.photos') },
        { key: 'photo_views', label: t('common.photo_views') },
    ];

    return (
        <PublicLayout title={guide.name ?? guide.contrib_id}>
            {/* Header */}
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                <GuideAvatar name={guide.name} url={guide.avatar_url} className="size-16 text-lg" />
                <div className="min-w-0 flex-1">
                    <h1 className="truncate text-2xl font-bold sm:text-3xl">{guide.name ?? guide.contrib_id}</h1>
                    <div className="mt-1 flex flex-wrap items-center gap-2 text-sm">
                        {guide.level !== null && (
                            <span className="inline-flex items-center gap-1">
                                <LevelBadge level={guide.level} /> Local Guide
                            </span>
                        )}
                        <StatusBadge status={guide.status} />
                        <span className="text-muted-foreground text-xs">
                            {t('guide.last_synced', { time: relativeTime(guide.last_synced_at, locale, t('common.never')) })}
                        </span>
                    </div>
                </div>
                <div className="flex gap-2">
                    <Button asChild variant="outline" size="sm">
                        <a href={guide.profile_url} target="_blank" rel="noopener noreferrer">
                            <ExternalLink className="size-4" />
                            {t('guide.view_on_maps')}
                        </a>
                    </Button>
                    <Button asChild variant="outline" size="sm">
                        <Link href={route('compare', { ids: guide.contrib_id })}>
                            <GitCompare className="size-4" />
                            {t('guide.compare')}
                        </Link>
                    </Button>
                </div>
            </div>

            {flash?.queued && flash.queued.contrib_id === guide.contrib_id && guide.status !== 'pending' && flash.queued.created && (
                <Notice icon={CheckCircle2} tone="success" title={t('add.queued_no_position')} />
            )}

            {guide.status === 'pending' && (
                <Notice
                    icon={LoaderCircle}
                    spin
                    tone="info"
                    title={t('guide.pending_title')}
                    body={
                        t('guide.pending_body') +
                        (flash?.queued?.position ? ' ' + t('guide.queue_position', { position: flash.queued.position }) : '')
                    }
                />
            )}
            {guide.status === 'restricted' && (
                <Notice icon={Lock} tone="warning" title={t('guide.restricted_title')} body={t('guide.restricted_body')} />
            )}
            {guide.status === 'failed' && <Notice icon={AlertTriangle} tone="danger" title={t('guide.failed_title')} body={t('guide.failed_body')} />}

            {/* Stat cards */}
            <div className="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <StatCard label={t('common.points')} value={guide.points} sub={rankSub('points')} />
                <StatCard label={t('common.reviews')} value={guide.reviews} sub={rankSub('reviews')} />
                <StatCard label={t('common.photos')} value={guide.photos} sub={rankSub('photos')} />
                <StatCard label={t('common.photo_views')} value={guide.photo_views} compact sub={rankSub('photo_views')} />
                <StatCard label={t('common.videos')} value={guide.videos} />
                <StatCard label={t('common.places_added')} value={guide.places_added} />
                <StatCard label={t('common.roads_added')} value={guide.roads_added} />
                <div className="bg-card rounded-xl border p-3 sm:p-4">
                    <div className="text-muted-foreground text-xs sm:text-sm">{t('common.views_per_photo')}</div>
                    <div className="mt-1 text-xl font-semibold tabular-nums sm:text-2xl">{formatDecimal(guide.views_per_photo)}</div>
                </div>
            </div>

            {/* Growth: small multiples, one measure per chart */}
            <section className="mt-10">
                <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h2 className="text-xl font-bold">{t('guide.growth')}</h2>
                    <RangeToggle value={range} onChange={setRange} />
                </div>
                <div className="grid gap-3 sm:grid-cols-2">
                    {metrics.map((m) => (
                        <MetricChart
                            key={m.key}
                            title={m.label}
                            rows={rows as unknown as Record<string, number | string | null>[]}
                            series={[{ key: m.key, label: m.label, color: 'var(--series-1)' }]}
                        />
                    ))}
                </div>
            </section>

            {generations.length > 0 && (
                <section className="mt-10">
                    <h2 className="mb-3 text-xl font-bold">{t('guide.frames')}</h2>
                    <div className="grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                        {generations.map((g) => (
                            <Link key={g.id} href={g.share_url} className="bg-card overflow-hidden rounded-lg border">
                                <img src={g.image_url} alt={g.frame_title} loading="lazy" className="bg-muted aspect-[9/16] w-full object-cover" />
                                <div className="line-clamp-1 p-2 text-xs">{g.frame_title}</div>
                            </Link>
                        ))}
                    </div>
                </section>
            )}
        </PublicLayout>
    );
}

function Notice({
    icon: Icon,
    title,
    body,
    tone,
    spin,
}: {
    icon: typeof Lock;
    title: string;
    body?: string;
    tone: 'info' | 'warning' | 'danger' | 'success';
    spin?: boolean;
}) {
    const tones = {
        info: 'border-sky-300 bg-sky-50 text-sky-900 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-100',
        warning: 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100',
        danger: 'border-rose-300 bg-rose-50 text-rose-900 dark:border-rose-800 dark:bg-rose-950 dark:text-rose-100',
        success: 'border-emerald-300 bg-emerald-50 text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-100',
    };
    return (
        <div className={`mt-4 flex gap-3 rounded-xl border p-4 ${tones[tone]}`} role="status">
            <Icon className={`mt-0.5 size-5 shrink-0 ${spin ? 'animate-spin' : ''}`} />
            <div>
                <div className="font-semibold">{title}</div>
                {body && <p className="mt-1 text-sm">{body}</p>}
            </div>
        </div>
    );
}
