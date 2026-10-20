import AddProfileForm from '@/components/add-profile-form';
import { GuideAvatar, LevelBadge, Num, StatusBadge } from '@/components/guide-bits';
import { Button } from '@/components/ui/button';
import PublicLayout from '@/layouts/public-layout';
import { useT } from '@/lib/i18n';
import { type GenerationCard, type Guide } from '@/types';
import { Link, router } from '@inertiajs/react';
import { Settings, Trash2 } from 'lucide-react';

interface Props {
    guides: Guide[];
    generations: GenerationCard[];
}

/** "My frames": the member's claimed profiles and generated frames. */
export default function Dashboard({ guides, generations }: Props) {
    const t = useT();

    const destroy = (id: string) => {
        if (confirm(t('mine.delete_confirm'))) {
            router.delete(route('generations.destroy', id), { preserveScroll: true });
        }
    };

    return (
        <PublicLayout title={t('mine.title')}>
            <div className="flex items-center justify-between gap-2">
                <h1 className="text-2xl font-bold sm:text-3xl">{t('mine.title')}</h1>
                <Button asChild variant="ghost" size="sm">
                    <Link href={route('profile.edit')}>
                        <Settings className="size-4" />
                    </Link>
                </Button>
            </div>

            {generations.length === 0 ? (
                <div className="text-muted-foreground mt-6 rounded-xl border border-dashed p-8 text-center">
                    <p>{t('mine.empty')}</p>
                    <Button asChild className="mt-4">
                        <Link href={route('frames.index')}>{t('home.cta_frame')}</Link>
                    </Button>
                </div>
            ) : (
                <div className="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    {generations.map((g) => (
                        <div key={g.id} className="bg-card overflow-hidden rounded-xl border">
                            <Link href={g.share_url}>
                                <img src={g.image_url} alt={g.frame_title} loading="lazy" className="bg-muted aspect-[9/16] w-full object-cover" />
                            </Link>
                            <div className="flex items-center justify-between gap-2 p-2">
                                <span className="line-clamp-1 text-xs">{g.frame_title}</span>
                                <button
                                    type="button"
                                    onClick={() => destroy(g.id)}
                                    className="text-muted-foreground rounded p-1 hover:text-red-600"
                                    aria-label={t('mine.delete')}
                                >
                                    <Trash2 className="size-4" />
                                </button>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <section className="mt-10">
                <h2 className="mb-3 text-xl font-bold">{t('mine.profiles')}</h2>
                {guides.length > 0 && (
                    <ul className="mb-4 space-y-2">
                        {guides.map((g) => (
                            <li key={g.id}>
                                <Link href={route('guides.show', g.contrib_id)} className="bg-card flex items-center gap-3 rounded-xl border p-3">
                                    <GuideAvatar name={g.name} url={g.avatar_url} />
                                    <div className="min-w-0 flex-1">
                                        <div className="truncate font-medium">{g.name ?? g.contrib_id}</div>
                                        <div className="text-muted-foreground flex items-center gap-2 text-xs">
                                            <LevelBadge level={g.level} />
                                            <span>
                                                <Num value={g.points} compact /> {t('common.points')}
                                            </span>
                                        </div>
                                    </div>
                                    <StatusBadge status={g.status} />
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
                <div className="bg-card max-w-xl rounded-xl border p-4">
                    <AddProfileForm redirect="back" compact />
                </div>
            </section>
        </PublicLayout>
    );
}
