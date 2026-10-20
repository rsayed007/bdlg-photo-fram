import { Button } from '@/components/ui/button';
import PublicLayout from '@/layouts/public-layout';
import { useT } from '@/lib/i18n';
import { Link } from '@inertiajs/react';
import { Check, Copy, Download, Facebook, MessageCircle, Sparkles } from 'lucide-react';
import { useState } from 'react';

interface Props {
    generation: {
        id: string;
        image_url: string;
        download_url: string;
        share_url: string;
        downloads: number;
        name: string;
        event: string;
        frame_title: string;
        frame_create_url: string;
        guide_url: string | null;
        width: number;
        height: number;
    };
}

export default function Share({ generation: g }: Props) {
    const t = useT();
    const [copied, setCopied] = useState(false);
    const text = `${g.name} — ${g.event}`;

    const copy = async () => {
        await navigator.clipboard.writeText(g.share_url);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <PublicLayout className="max-w-4xl">
            <div className="grid gap-6 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] md:items-start">
                <img
                    src={g.image_url}
                    alt={`${g.frame_title} — ${g.name}`}
                    width={g.width}
                    height={g.height}
                    className="bg-muted mx-auto w-full max-w-sm rounded-xl border shadow-sm"
                />

                <div className="space-y-5">
                    <div>
                        <div className="text-muted-foreground text-sm">{g.event}</div>
                        <h1 className="text-2xl font-bold">{g.frame_title}</h1>
                        <p className="mt-1">
                            {g.guide_url ? (
                                <Link href={g.guide_url} className="text-primary hover:underline">
                                    {t('share.by', { name: g.name })}
                                </Link>
                            ) : (
                                t('share.by', { name: g.name })
                            )}
                        </p>
                    </div>

                    <div className="grid gap-2 sm:grid-cols-2">
                        <Button asChild size="lg" className="sm:col-span-2">
                            <a href={g.download_url}>
                                <Download className="size-4" />
                                {t('share.download')}
                            </a>
                        </Button>
                        <Button asChild variant="outline" className="border-green-600 text-green-700 dark:text-green-400">
                            <a href={`https://wa.me/?text=${encodeURIComponent(`${text}\n${g.share_url}`)}`} target="_blank" rel="noopener noreferrer">
                                <MessageCircle className="size-4" />
                                {t('share.whatsapp')}
                            </a>
                        </Button>
                        <Button asChild variant="outline" className="border-blue-600 text-blue-700 dark:text-blue-400">
                            <a
                                href={`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(g.share_url)}`}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <Facebook className="size-4" />
                                {t('share.facebook')}
                            </a>
                        </Button>
                        <Button variant="ghost" onClick={copy} className="sm:col-span-2">
                            {copied ? <Check className="size-4" /> : <Copy className="size-4" />}
                            {copied ? t('share.copied') : t('share.copy')}
                        </Button>
                    </div>

                    <p className="text-muted-foreground text-sm">{t('share.downloads', { count: g.downloads })}</p>

                    <div className="rounded-xl border border-emerald-300 bg-emerald-50 p-4 dark:border-emerald-800 dark:bg-emerald-950">
                        <Button asChild variant="default" className="w-full">
                            <Link href={g.frame_create_url}>
                                <Sparkles className="size-4" />
                                {t('share.create_own')}
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>
        </PublicLayout>
    );
}
