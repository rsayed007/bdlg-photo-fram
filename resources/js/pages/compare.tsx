import { filterRange, MetricChart, RangeToggle, type Range } from '@/components/growth-chart';
import { GuideAvatar, LevelBadge, Num } from '@/components/guide-bits';
import { Input } from '@/components/ui/input';
import PublicLayout from '@/layouts/public-layout';
import { formatDecimal } from '@/lib/format';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { type Guide, type HistoryPoint } from '@/types';
import { Link, router } from '@inertiajs/react';
import { Plus, Search, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

type CompareGuide = Guide & { history: HistoryPoint[] };

interface Props {
    guides: CompareGuide[];
    q: string;
    candidates?: Guide[];
    max: number;
}

// Categorical slots 1-3 in fixed order: color follows the guide's position.
const COLORS = ['var(--series-1)', 'var(--series-2)', 'var(--series-3)'];
const DOT = ['bg-series-1', 'bg-series-2', 'bg-series-3'];

type Metric = 'points' | 'reviews' | 'photos' | 'photo_views';

export default function Compare({ guides, q, candidates = [], max }: Props) {
    const t = useT();
    const [metric, setMetric] = useState<Metric>('points');
    const [range, setRange] = useState<Range>('90d');
    const [search, setSearch] = useState(q);
    const first = useRef(true);
    const ids = guides.map((g) => g.contrib_id);

    const go = (next: string[]) => router.get(route('compare'), next.length ? { ids: next.join(',') } : {}, { preserveScroll: true });

    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const id = setTimeout(
            () => router.reload({ data: { ids: ids.join(',') || undefined, q: search || undefined }, only: ['candidates', 'q'] }),
            300,
        );
        return () => clearTimeout(id);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const rows: { label: string; key: keyof Guide; compact?: boolean; decimal?: boolean }[] = [
        { label: t('common.level'), key: 'level' },
        { label: t('common.points'), key: 'points' },
        { label: t('common.reviews'), key: 'reviews' },
        { label: t('common.photos'), key: 'photos' },
        { label: t('common.videos'), key: 'videos' },
        { label: t('common.photo_views'), key: 'photo_views', compact: true },
        { label: t('common.views_per_photo'), key: 'views_per_photo', decimal: true },
        { label: t('common.places_added'), key: 'places_added' },
        { label: t('common.roads_added'), key: 'roads_added' },
    ];

    // Merge histories by date: { date, g0, g1, g2 }.
    const chartRows = useMemo(() => {
        const byDate = new Map<string, Record<string, number | string | null>>();
        guides.forEach((g, i) =>
            g.history.forEach((h) => {
                const row = byDate.get(h.date) ?? { date: h.date };
                row[`g${i}`] = h[metric];
                byDate.set(h.date, row);
            }),
        );
        const sorted = [...byDate.values()].sort((a, b) => String(a.date).localeCompare(String(b.date))) as ({ date: string } & Record<
            string,
            number | string | null
        >)[];
        return filterRange(sorted, range);
    }, [guides, metric, range]);

    const metricLabels: Record<Metric, string> = {
        points: t('common.points'),
        reviews: t('common.reviews'),
        photos: t('common.photos'),
        photo_views: t('common.photo_views'),
    };

    return (
        <PublicLayout title={t('compare.title')}>
            <h1 className="text-2xl font-bold sm:text-3xl">{t('compare.title')}</h1>
            <p className="text-muted-foreground mt-1 text-sm">{t('compare.subtitle')}</p>

            {guides.length < max && (
                <div className="relative mt-4 max-w-md">
                    <Search className="text-muted-foreground absolute top-2.5 left-3 size-4" />
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder={t('leaderboard.search_placeholder')}
                        className="pl-9"
                    />
                    {search && candidates.length > 0 && (
                        <ul className="bg-popover absolute z-20 mt-1 w-full overflow-hidden rounded-md border shadow-md">
                            {candidates.map((c) => (
                                <li key={c.id}>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setSearch('');
                                            go([...ids, c.contrib_id]);
                                        }}
                                        className="hover:bg-accent flex w-full items-center gap-2 px-3 py-2 text-left text-sm"
                                    >
                                        <Plus className="size-4" />
                                        <GuideAvatar name={c.name} url={c.avatar_url} className="size-6" />
                                        <span className="flex-1 truncate">{c.name}</span>
                                        <LevelBadge level={c.level} />
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}

            {guides.length === 0 ? (
                <p className="text-muted-foreground mt-6 rounded-xl border border-dashed p-8 text-center">
                    {t('compare.empty')}{' '}
                    <Link href={route('leaderboard')} className="text-primary underline">
                        {t('nav.leaderboard')}
                    </Link>
                </p>
            ) : (
                <>
                    <div className="bg-card mt-6 overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b">
                                    <th className="text-muted-foreground px-3 py-3 text-left text-xs font-medium">{t('compare.metric')}</th>
                                    {guides.map((g, i) => (
                                        <th key={g.id} className="min-w-28 px-3 py-3 text-right align-top">
                                            <div className="flex flex-col items-end gap-1">
                                                <Link href={route('guides.show', g.contrib_id)} className="flex items-center gap-1.5 hover:underline">
                                                    <span className={cn('size-2.5 shrink-0 rounded-full', DOT[i])} aria-hidden />
                                                    <span className="max-w-32 truncate font-semibold">{g.name ?? g.contrib_id}</span>
                                                </Link>
                                                <button
                                                    type="button"
                                                    onClick={() => go(ids.filter((x) => x !== g.contrib_id))}
                                                    className="text-muted-foreground hover:text-foreground inline-flex items-center gap-0.5 text-xs font-normal"
                                                >
                                                    <X className="size-3" />
                                                    {t('compare.remove')}
                                                </button>
                                            </div>
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {rows.map((r) => {
                                    const values = guides.map((g) => g[r.key] as number | null);
                                    const best = Math.max(...values.map((v) => v ?? -Infinity));
                                    return (
                                        <tr key={r.key}>
                                            <td className="text-muted-foreground px-3 py-2">{r.label}</td>
                                            {values.map((v, i) => (
                                                <td
                                                    key={i}
                                                    className={cn(
                                                        'px-3 py-2 text-right',
                                                        guides.length > 1 && v !== null && v === best && 'font-semibold',
                                                    )}
                                                >
                                                    {r.decimal ? (
                                                        <span className="tabular-nums">{formatDecimal(v)}</span>
                                                    ) : (
                                                        <Num value={v} compact={r.compact} />
                                                    )}
                                                </td>
                                            ))}
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    <section className="mt-8">
                        <div className="mb-3 flex flex-wrap items-center gap-2">
                            <select
                                value={metric}
                                onChange={(e) => setMetric(e.target.value as Metric)}
                                className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                                aria-label={t('compare.metric')}
                            >
                                {(Object.keys(metricLabels) as Metric[]).map((m) => (
                                    <option key={m} value={m}>
                                        {metricLabels[m]}
                                    </option>
                                ))}
                            </select>
                            <RangeToggle value={range} onChange={setRange} />
                        </div>
                        <MetricChart
                            title={metricLabels[metric]}
                            rows={chartRows}
                            height={280}
                            series={guides.map((g, i) => ({ key: `g${i}`, label: g.name ?? g.contrib_id, color: COLORS[i] }))}
                        />
                    </section>
                </>
            )}
        </PublicLayout>
    );
}
