import { formatCompact, formatNumber } from '@/lib/format';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { type GuideStatus } from '@/types';
import { AlertTriangle, Clock, Lock, Star } from 'lucide-react';

export function GuideAvatar({ name, url, className }: { name: string | null; url: string | null; className?: string }) {
    const initials = (name ?? '?')
        .split(/\s+/)
        .map((p) => p[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();

    return url ? (
        <img
            src={url}
            alt=""
            referrerPolicy="no-referrer"
            loading="lazy"
            className={cn('bg-muted size-9 shrink-0 rounded-full object-cover', className)}
        />
    ) : (
        <span
            className={cn(
                'bg-muted text-muted-foreground flex size-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                className,
            )}
        >
            {initials}
        </span>
    );
}

export function LevelBadge({ level, className }: { level: number | null; className?: string }) {
    const t = useT();
    if (level === null) return <span className="text-muted-foreground">{t('common.unknown')}</span>;

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-900 tabular-nums dark:bg-amber-900/40 dark:text-amber-100',
                className,
            )}
            title={`${t('common.level')} ${level}`}
        >
            <Star className="size-3 fill-current" />
            {level}
        </span>
    );
}

/** Unknown → "—" (never 0). `compact` → 1.2K / 3.4M. */
export function Num({ value, compact = false, className }: { value: number | null | undefined; compact?: boolean; className?: string }) {
    const unknown = value === null || value === undefined;
    return (
        <span
            className={cn('tabular-nums', unknown && 'text-muted-foreground', className)}
            title={!unknown && compact ? formatNumber(value) : undefined}
        >
            {compact ? formatCompact(value) : formatNumber(value)}
        </span>
    );
}

const STATUS_STYLE: Record<GuideStatus, { icon: typeof Clock; cls: string }> = {
    pending: { icon: Clock, cls: 'bg-sky-100 text-sky-900 dark:bg-sky-900/40 dark:text-sky-100' },
    active: { icon: Star, cls: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100' },
    restricted: { icon: Lock, cls: 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100' },
    failed: { icon: AlertTriangle, cls: 'bg-rose-100 text-rose-900 dark:bg-rose-900/40 dark:text-rose-100' },
    inactive: { icon: Clock, cls: 'bg-muted text-muted-foreground' },
};

export function StatusBadge({ status }: { status: GuideStatus }) {
    const t = useT();
    const { icon: Icon, cls } = STATUS_STYLE[status];
    return (
        <span className={cn('inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium', cls)}>
            <Icon className="size-3" />
            {t(`status.${status}`)}
        </span>
    );
}

export function StatCard({ label, value, sub, compact = false }: { label: string; value: number | null; sub?: string | null; compact?: boolean }) {
    return (
        <div className="bg-card rounded-xl border p-3 sm:p-4">
            <div className="text-muted-foreground text-xs sm:text-sm">{label}</div>
            <div className="mt-1 text-xl font-semibold sm:text-2xl">
                <Num value={value} compact={compact} />
            </div>
            {sub && <div className="text-muted-foreground mt-1 text-xs">{sub}</div>}
        </div>
    );
}
