import { formatCompact, formatNumber } from '@/lib/format';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { CartesianGrid, Legend, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

export type Range = '7d' | '30d' | '90d' | 'all';
export const RANGES: Range[] = ['7d', '30d', '90d', 'all'];

export function filterRange<T extends { date: string }>(rows: T[], range: Range): T[] {
    if (range === 'all' || rows.length === 0) return rows;
    const days = { '7d': 7, '30d': 30, '90d': 90 }[range];
    const last = new Date(rows[rows.length - 1].date);
    const cutoff = new Date(last.getTime() - (days - 1) * 86400000);
    return rows.filter((r) => new Date(r.date) >= cutoff);
}

export function RangeToggle({ value, onChange }: { value: Range; onChange: (r: Range) => void }) {
    const t = useT();
    return (
        <div className="bg-muted inline-flex rounded-lg p-1" role="group">
            {RANGES.map((r) => (
                <button
                    key={r}
                    type="button"
                    onClick={() => onChange(r)}
                    aria-pressed={value === r}
                    className={cn(
                        'rounded-md px-3 py-1 text-sm font-medium transition-colors',
                        value === r ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground',
                    )}
                >
                    {t(`guide.range.${r}`)}
                </button>
            ))}
        </div>
    );
}

const shortDate = (iso: string) => new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });

const axisProps = {
    tick: { fill: 'var(--muted-foreground)', fontSize: 11 },
    tickLine: false,
    axisLine: false,
} as const;

interface Series {
    key: string;
    label: string;
    color: string;
}

/**
 * One metric over time. Several series (compare page) share the one y-axis
 * because they are the same measure; different measures get separate charts.
 */
export function MetricChart({
    title,
    rows,
    series,
    height = 180,
}: {
    title: string;
    rows: Record<string, number | string | null>[];
    series: Series[];
    height?: number;
}) {
    const t = useT();
    const hasData = rows.some((r) => series.some((s) => r[s.key] !== null && r[s.key] !== undefined));

    return (
        <figure className="bg-card rounded-xl border p-3 sm:p-4">
            <figcaption className="mb-2 text-sm font-medium">{title}</figcaption>
            {!hasData || rows.length < 2 ? (
                <div className="text-muted-foreground flex items-center justify-center text-sm" style={{ height }}>
                    {t('guide.no_history')}
                </div>
            ) : (
                <ResponsiveContainer width="100%" height={height}>
                    <LineChart data={rows} margin={{ top: 6, right: 20, bottom: 0, left: 0 }}>
                        <CartesianGrid vertical={false} stroke="var(--border)" />
                        <XAxis dataKey="date" tickFormatter={shortDate} minTickGap={24} {...axisProps} />
                        <YAxis width={44} tickFormatter={(v: number) => formatCompact(v)} domain={['auto', 'auto']} {...axisProps} />
                        <Tooltip
                            cursor={{ stroke: 'var(--muted-foreground)', strokeDasharray: '3 3' }}
                            contentStyle={{
                                background: 'var(--popover)',
                                border: '1px solid var(--border)',
                                borderRadius: 8,
                                color: 'var(--popover-foreground)',
                                fontSize: 12,
                            }}
                            labelFormatter={(label) => new Date(String(label)).toLocaleDateString('en-GB', { dateStyle: 'medium' })}
                            formatter={(value, name) => [formatNumber(typeof value === 'number' ? value : null), name]}
                        />
                        {series.length > 1 && (
                            <Legend
                                iconType="plainline"
                                wrapperStyle={{ fontSize: 12 }}
                                // Text keeps the text color; the line swatch carries identity.
                                formatter={(value) => <span style={{ color: 'var(--foreground)' }}>{value}</span>}
                            />
                        )}
                        {series.map((s) => (
                            <Line
                                key={s.key}
                                type="monotone"
                                dataKey={s.key}
                                name={s.label}
                                stroke={s.color}
                                strokeWidth={2}
                                dot={false}
                                activeDot={{ r: 4, strokeWidth: 2, stroke: 'var(--card)' }}
                                connectNulls
                                isAnimationActive={false}
                            />
                        ))}
                    </LineChart>
                </ResponsiveContainer>
            )}
        </figure>
    );
}
