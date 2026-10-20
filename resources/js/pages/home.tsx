import AddProfileForm from '@/components/add-profile-form';
import { GuideAvatar, LevelBadge, Num } from '@/components/guide-bits';
import { Button } from '@/components/ui/button';
import PublicLayout from '@/layouts/public-layout';
import { useT } from '@/lib/i18n';
import { type FrameCard, type GenerationCard, type Guide } from '@/types';
import { Link } from '@inertiajs/react';
import { ArrowRight, ExternalLink, Frame as FrameIcon, Trophy } from 'lucide-react';

interface Props {
    content: { title: string; tagline: string; intro: string; links: { label: string; url: string }[] };
    frames: FrameCard[];
    featured: GenerationCard[];
    top: Guide[];
    totals: { members: number; reviews: number | null; photos: number | null; photo_views: number | null };
}

export default function Home({ content, frames, featured, top, totals }: Props) {
    const t = useT();
    const firstFrame = frames[0];

    return (
        <PublicLayout
            title={content.title}
            hero={
                <section className="relative overflow-hidden border-b bg-gradient-to-br from-emerald-800 via-emerald-700 to-emerald-900 text-white">
                    {/* Flag-inspired red disc */}
                    <div className="pointer-events-none absolute -top-24 -right-24 size-72 rounded-full bg-rose-500/40 blur-2xl sm:size-96" />
                    <div className="relative mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-16">
                        <h1 className="text-3xl leading-tight font-bold sm:text-5xl">{content.title}</h1>
                        <p className="mt-3 text-lg text-emerald-50 sm:text-xl">{content.tagline}</p>
                        <p className="mt-4 max-w-2xl text-sm leading-relaxed text-emerald-100 sm:text-base">{content.intro}</p>
                        <div className="mt-6 flex flex-col gap-3 sm:flex-row">
                            <Button asChild size="lg" className="bg-white text-emerald-900 hover:bg-emerald-50">
                                <Link href={firstFrame ? firstFrame.create_url : route('frames.index')}>
                                    <FrameIcon className="size-4" />
                                    {t('home.cta_frame')}
                                </Link>
                            </Button>
                            <Button
                                asChild
                                size="lg"
                                variant="outline"
                                className="border-white/50 bg-transparent text-white hover:bg-white/10 hover:text-white"
                            >
                                <a href="#add-profile">
                                    <Trophy className="size-4" />
                                    {t('home.cta_join')}
                                </a>
                            </Button>
                        </div>

                        <dl className="mt-8 grid grid-cols-2 gap-3 sm:mt-10 sm:grid-cols-4">
                            {[
                                { label: t('home.stats.members'), value: totals.members },
                                { label: t('home.stats.reviews'), value: totals.reviews },
                                { label: t('home.stats.photos'), value: totals.photos },
                                { label: t('home.stats.views'), value: totals.photo_views },
                            ].map((s) => (
                                <div key={s.label} className="rounded-xl bg-white/10 p-3 backdrop-blur-sm sm:p-4">
                                    <dt className="text-xs text-emerald-100 sm:text-sm">{s.label}</dt>
                                    <dd className="mt-1 text-2xl font-semibold sm:text-3xl">
                                        <Num value={s.value} compact className="text-white" />
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </div>
                </section>
            }
        >
            <div className="space-y-12 sm:space-y-16">
                {/* Event frames: public editor links */}
                <section aria-labelledby="frames-title">
                    <SectionHeader id="frames-title" title={t('home.frames.title')} subtitle={t('home.frames.subtitle')} />
                    {frames.length === 0 ? (
                        <p className="text-muted-foreground rounded-xl border border-dashed p-6 text-center">{t('home.frames.empty')}</p>
                    ) : (
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4">
                            {frames.map((frame) => (
                                <Link
                                    key={frame.id}
                                    href={frame.create_url}
                                    className="group bg-card overflow-hidden rounded-xl border transition-shadow hover:shadow-md"
                                >
                                    <div className="bg-muted overflow-hidden" style={{ aspectRatio: `${frame.size_w} / ${frame.size_h}` }}>
                                        {frame.thumbnail_url && (
                                            <img
                                                src={frame.thumbnail_url}
                                                alt=""
                                                loading="lazy"
                                                className="size-full object-cover transition-transform group-hover:scale-[1.02]"
                                            />
                                        )}
                                    </div>
                                    <div className="p-3">
                                        {frame.event && <div className="text-muted-foreground line-clamp-1 text-xs">{frame.event}</div>}
                                        <div className="line-clamp-2 text-sm font-medium">{frame.title}</div>
                                        <div className="text-primary mt-2 inline-flex items-center gap-1 text-sm font-medium">
                                            {t('home.frames.create')}
                                            <ArrowRight className="size-3.5" />
                                        </div>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    )}
                </section>

                {/* Admin-featured generated frames: public share links */}
                {featured.length > 0 && (
                    <section aria-labelledby="gallery-title">
                        <SectionHeader id="gallery-title" title={t('home.gallery.title')} subtitle={t('home.gallery.subtitle')} />
                        <div className="-mx-4 flex snap-x gap-3 overflow-x-auto px-4 pb-2 sm:mx-0 sm:grid sm:grid-cols-4 sm:overflow-visible sm:px-0 lg:grid-cols-6">
                            {featured.map((g) => (
                                <Link
                                    key={g.id}
                                    href={g.share_url}
                                    className="bg-card w-36 shrink-0 snap-start overflow-hidden rounded-xl border sm:w-auto"
                                >
                                    <img
                                        src={g.image_url}
                                        alt={g.frame_title}
                                        loading="lazy"
                                        className="bg-muted aspect-[9/16] w-full object-cover"
                                    />
                                    <div className="p-2">
                                        <div className="line-clamp-1 text-xs font-medium">{g.guide_name ?? g.frame_title}</div>
                                        <div className="text-muted-foreground line-clamp-1 text-xs">{g.frame_title}</div>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </section>
                )}

                <div className="grid gap-8 lg:grid-cols-5">
                    {/* Top guides */}
                    <section aria-labelledby="top-title" className="lg:col-span-3">
                        <SectionHeader id="top-title" title={t('home.top.title')} />
                        <ol className="bg-card divide-y rounded-xl border">
                            {top.map((g) => (
                                <li key={g.id}>
                                    <Link href={route('guides.show', g.contrib_id)} className="hover:bg-accent/50 flex items-center gap-3 p-3">
                                        <span className="text-muted-foreground w-5 text-center text-sm font-semibold tabular-nums">{g.rank}</span>
                                        <GuideAvatar name={g.name} url={g.avatar_url} />
                                        <div className="min-w-0 flex-1">
                                            <div className="truncate font-medium">{g.name ?? g.contrib_id}</div>
                                            <div className="text-muted-foreground flex items-center gap-2 text-xs">
                                                <LevelBadge level={g.level} />
                                                <span>
                                                    <Num value={g.reviews} compact /> {t('common.reviews')}
                                                </span>
                                            </div>
                                        </div>
                                        <div className="text-right">
                                            <div className="font-semibold">
                                                <Num value={g.points} compact />
                                            </div>
                                            <div className="text-muted-foreground text-xs">{t('common.points')}</div>
                                        </div>
                                    </Link>
                                </li>
                            ))}
                        </ol>
                        <Link href={route('leaderboard')} className="text-primary mt-3 inline-flex items-center gap-1 text-sm font-medium">
                            {t('home.top.full')}
                            <ArrowRight className="size-3.5" />
                        </Link>
                    </section>

                    {/* Add profile */}
                    <section id="add-profile" aria-labelledby="add-title" className="scroll-mt-20 lg:col-span-2">
                        <SectionHeader id="add-title" title={t('home.add.title')} subtitle={t('home.add.subtitle')} />
                        <div className="bg-card rounded-xl border p-4">
                            <AddProfileForm />
                        </div>

                        {content.links.length > 0 && (
                            <div className="mt-6">
                                <h3 className="mb-2 text-sm font-semibold">{t('home.links.title')}</h3>
                                <ul className="space-y-1">
                                    {content.links.map((l) => (
                                        <li key={l.url}>
                                            <a
                                                href={l.url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="text-primary inline-flex items-center gap-1 text-sm hover:underline"
                                            >
                                                {l.label}
                                                <ExternalLink className="size-3" />
                                            </a>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </section>
                </div>
            </div>
        </PublicLayout>
    );
}

function SectionHeader({ id, title, subtitle }: { id: string; title: string; subtitle?: string }) {
    return (
        <div className="mb-4">
            <h2 id={id} className="text-xl font-bold sm:text-2xl">
                {title}
            </h2>
            {subtitle && <p className="text-muted-foreground mt-1 text-sm">{subtitle}</p>}
        </div>
    );
}
