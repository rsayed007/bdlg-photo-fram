import PublicLayout from '@/layouts/public-layout';
import { useT } from '@/lib/i18n';
import { type FrameCard } from '@/types';
import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

interface Props {
    groups: { event: string | null; description: string | null; frames: FrameCard[] }[];
}

export default function FramesIndex({ groups }: Props) {
    const t = useT();

    return (
        <PublicLayout title={t('frames.title')}>
            <h1 className="text-2xl font-bold sm:text-3xl">{t('frames.title')}</h1>
            <p className="text-muted-foreground mt-1 text-sm">{t('frames.subtitle')}</p>

            {groups.length === 0 && (
                <p className="text-muted-foreground mt-8 rounded-xl border border-dashed p-8 text-center">{t('home.frames.empty')}</p>
            )}

            <div className="mt-8 space-y-10">
                {groups.map((group) => (
                    <section key={group.event ?? 'other'}>
                        {group.event && <h2 className="text-xl font-bold">{group.event}</h2>}
                        {group.description && <p className="text-muted-foreground mt-1 text-sm">{group.description}</p>}
                        <div className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4">
                            {group.frames.map((frame) => (
                                <Link
                                    key={frame.id}
                                    href={frame.create_url}
                                    className="group bg-card overflow-hidden rounded-xl border hover:shadow-md"
                                >
                                    <div className="bg-muted overflow-hidden" style={{ aspectRatio: `${frame.size_w} / ${frame.size_h}` }}>
                                        {frame.thumbnail_url && (
                                            <img src={frame.thumbnail_url} alt="" loading="lazy" className="size-full object-cover" />
                                        )}
                                    </div>
                                    <div className="p-3">
                                        <div className="line-clamp-2 text-sm font-medium">{frame.title}</div>
                                        <div className="text-primary mt-2 inline-flex items-center gap-1 text-sm font-medium">
                                            {t('home.frames.create')}
                                            <ArrowRight className="size-3.5" />
                                        </div>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </section>
                ))}
            </div>
        </PublicLayout>
    );
}
