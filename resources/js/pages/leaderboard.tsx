import { GuideAvatar, LevelBadge, Num } from '@/components/guide-bits';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import PublicLayout from '@/layouts/public-layout';
import { formatDecimal, relativeTime } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { type Guide, type Paginated } from '@/types';
import { Link, router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ChevronLeft, ChevronRight, GitCompare, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface Filters {
    sort: string;
    dir: 'asc' | 'desc';
    q: string;
    level: number | null;
}

interface Props {
    guides: Paginated<Guide>;
    filters: Filters;
    levels: number[];
}

type Column = { key: keyof Guide | 'views_per_photo'; label: string; compact?: boolean; decimal?: boolean };

export default function Leaderboard({ guides, filters, levels }: Props) {
    const t = useT();
    const locale = useLocale();
    const [q, setQ] = useState(filters.q);
    const [selected, setSelected] = useState<string[]>([]);
    const first = useRef(true);

    const columns: Column[] = [
        { key: 'points', label: t('common.points') },
        { key: 'reviews', label: t('common.reviews') },
        { key: 'photos', label: t('common.photos') },
        { key: 'videos', label: t('common.videos') },
        { key: 'photo_views', label: t('common.photo_views'), compact: true },
        { key: 'views_per_photo', label: t('common.views_per_photo'), decimal: true },
        { key: 'places_added', label: t('common.places_added') },
        { key: 'roads_added', label: t('common.roads_added') },
    ];

    const visit = (params: Partial<Filters> & { page?: number }) => {
        const next = { ...filters, ...params };
        router.get(
            route('leaderboard'),
            {
                sort: next.sort !== 'points' ? next.sort : undefined,
                dir: next.dir !== 'desc' ? next.dir : undefined,
                q: next.q || undefined,
                level: next.level || undefined,
                page: params.page && params.page > 1 ? params.page : undefined,
            },
            { preserveState: true, preserveScroll: params.page === undefined, replace: true },
        );
    };

    // Debounced search.
    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const id = setTimeout(() => visit({ q }), 350);
        return () => clearTimeout(id);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [q]);

    const sortBy = (key: string) => visit({ sort: key, dir: filters.sort === key && filters.dir === 'desc' ? 'asc' : 'desc' });

    const toggle = (id: string) => setSelected((s) => (s.includes(id) ? s.filter((x) => x !== id) : s.length >= 3 ? s : [...s, id]));

    const SortHeader = ({ col, className }: { col: { key: string; label: string }; className?: string }) => (
        <th className={cn('px-2 py-2 text-right font-medium whitespace-nowrap', className)}>
            <button type="button" onClick={() => sortBy(col.key)} className="hover:text-foreground inline-flex items-center gap-1">
                {col.label}
                {filters.sort === col.key && (filters.dir === 'desc' ? <ArrowDown className="size-3" /> : <ArrowUp className="size-3" />)}
            </button>
        </th>
    );

    return (
        <PublicLayout title={t('leaderboard.title')}>
            <div className="mb-6">
                <h1 className="text-2xl font-bold sm:text-3xl">{t('leaderboard.title')}</h1>
                <p className="text-muted-foreground mt-1 text-sm">{t('leaderboard.subtitle')}</p>
            </div>

            {/* Filters in one row */}
            <div className="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center">
                <div className="relative flex-1">
                    <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                    <Input
                        type="search"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder={t('leaderboard.search_placeholder')}
                        className="pl-9"
                        aria-label={t('common.search')}
                    />
                </div>
                <div className="flex gap-2">
                    <select
                        value={filters.level ?? ''}
                        onChange={(e) => visit({ level: e.target.value ? Number(e.target.value) : null })}
                        className="border-input bg-background h-9 flex-1 rounded-md border px-3 text-sm sm:flex-none"
                        aria-label={t('common.level')}
                    >
                        <option value="">{t('common.all_levels')}</option>
                        {levels.map((l) => (
                            <option key={l} value={l}>
                                {t('common.level')} {l}
                            </option>
                        ))}
                    </select>
                    <select
                        value={filters.sort}
                        onChange={(e) => visit({ sort: e.target.value, dir: 'desc' })}
                        className="border-input bg-background h-9 flex-1 rounded-md border px-3 text-sm md:hidden"
                        aria-label="Sort"
                    >
                        {[{ key: 'level', label: t('common.level') }, ...columns].map((c) => (
                            <option key={c.key} value={c.key}>
                                {c.label}
                            </option>
                        ))}
                    </select>
                </div>
            </div>

            {selected.length > 0 && (
                <div className="bg-background/95 sticky top-16 z-30 mb-3 flex items-center justify-between rounded-lg border p-2 shadow-sm">
                    <span className="px-2 text-sm">{t('leaderboard.compare_selected', { count: selected.length })}</span>
                    <Button asChild size="sm">
                        <Link href={route('compare', { ids: selected.join(',') })}>
                            <GitCompare className="size-4" />
                            {t('nav.compare')}
                        </Link>
                    </Button>
                </div>
            )}

            {guides.data.length === 0 ? (
                <p className="text-muted-foreground rounded-xl border border-dashed p-8 text-center">{t('leaderboard.empty')}</p>
            ) : (
                <>
                    {/* Desktop table */}
                    <div className="bg-card hidden overflow-x-auto rounded-xl border md:block">
                        <table className="w-full text-sm">
                            <thead className="text-muted-foreground border-b text-xs">
                                <tr>
                                    <th className="w-8 px-2 py-2" />
                                    <th className="px-2 py-2 text-right font-medium">{t('common.rank')}</th>
                                    <th className="px-2 py-2 text-left font-medium">{t('common.name')}</th>
                                    <SortHeader col={{ key: 'level', label: t('common.level') }} className="text-center" />
                                    {columns.map((c) => (
                                        <SortHeader key={c.key} col={c} />
                                    ))}
                                    <SortHeader col={{ key: 'last_synced_at', label: t('common.updated') }} />
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {guides.data.map((g) => (
                                    <tr key={g.id} className="hover:bg-accent/40">
                                        <td className="px-2 py-2">
                                            <input
                                                type="checkbox"
                                                checked={selected.includes(g.contrib_id)}
                                                onChange={() => toggle(g.contrib_id)}
                                                aria-label={`${t('nav.compare')} ${g.name ?? ''}`}
                                                className="accent-primary size-4"
                                            />
                                        </td>
                                        <td className="text-muted-foreground px-2 py-2 text-right tabular-nums">{g.rank}</td>
                                        <td className="px-2 py-2">
                                            <Link href={route('guides.show', g.contrib_id)} className="flex items-center gap-2 hover:underline">
                                                <GuideAvatar name={g.name} url={g.avatar_url} className="size-7" />
                                                <span className="max-w-48 truncate font-medium">{g.name ?? g.contrib_id}</span>
                                            </Link>
                                        </td>
                                        <td className="px-2 py-2 text-center">
                                            <LevelBadge level={g.level} />
                                        </td>
                                        {columns.map((c) => (
                                            <td key={c.key} className="px-2 py-2 text-right">
                                                {c.decimal ? (
                                                    <span className="tabular-nums">{formatDecimal(g.views_per_photo)}</span>
                                                ) : (
                                                    <Num value={g[c.key as keyof Guide] as number | null} compact={c.compact} />
                                                )}
                                            </td>
                                        ))}
                                        <td className="text-muted-foreground px-2 py-2 text-right text-xs whitespace-nowrap">
                                            {relativeTime(g.last_synced_at, locale, t('common.never'))}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Mobile list */}
                    <ol className="bg-card divide-y rounded-xl border md:hidden">
                        {guides.data.map((g) => {
                            const sortCol = columns.find((c) => c.key === filters.sort);
                            return (
                                <li key={g.id} className="flex items-center gap-3 p-3">
                                    <input
                                        type="checkbox"
                                        checked={selected.includes(g.contrib_id)}
                                        onChange={() => toggle(g.contrib_id)}
                                        aria-label={`${t('nav.compare')} ${g.name ?? ''}`}
                                        className="accent-primary size-4 shrink-0"
                                    />
                                    <span className="text-muted-foreground w-6 shrink-0 text-right text-sm tabular-nums">{g.rank}</span>
                                    <Link href={route('guides.show', g.contrib_id)} className="flex min-w-0 flex-1 items-center gap-2">
                                        <GuideAvatar name={g.name} url={g.avatar_url} className="size-8" />
                                        <div className="min-w-0">
                                            <div className="truncate text-sm font-medium">{g.name ?? g.contrib_id}</div>
                                            <div className="text-muted-foreground flex items-center gap-2 text-xs">
                                                <LevelBadge level={g.level} />
                                                <span>
                                                    <Num value={g.reviews} compact /> {t('common.reviews')}
                                                </span>
                                            </div>
                                        </div>
                                    </Link>
                                    <div className="shrink-0 text-right">
                                        <div className="text-sm font-semibold">
                                            {sortCol?.decimal ? (
                                                formatDecimal(g.views_per_photo)
                                            ) : (
                                                <Num value={g[(sortCol?.key ?? 'points') as keyof Guide] as number | null} compact />
                                            )}
                                        </div>
                                        <div className="text-muted-foreground text-xs">{sortCol?.label ?? t('common.points')}</div>
                                    </div>
                                </li>
                            );
                        })}
                    </ol>

                    {/* Pagination */}
                    {guides.last_page > 1 && (
                        <nav className="mt-4 flex items-center justify-between gap-2 text-sm">
                            <span className="text-muted-foreground">
                                {guides.from}–{guides.to} / {guides.total}
                            </span>
                            <div className="flex gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={guides.current_page <= 1}
                                    onClick={() => visit({ page: guides.current_page - 1 })}
                                >
                                    <ChevronLeft className="size-4" />
                                    {t('common.previous')}
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={guides.current_page >= guides.last_page}
                                    onClick={() => visit({ page: guides.current_page + 1 })}
                                >
                                    {t('common.next')}
                                    <ChevronRight className="size-4" />
                                </Button>
                            </div>
                        </nav>
                    )}
                </>
            )}
        </PublicLayout>
    );
}
