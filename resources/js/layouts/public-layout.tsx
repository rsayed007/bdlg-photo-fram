import AppLogoIcon from '@/components/app-logo-icon';
import { Sheet, SheetContent, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { useLocale, useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Languages, Menu } from 'lucide-react';
import { type PropsWithChildren, type ReactNode } from 'react';

interface Props {
    title?: string;
    /** Rendered full-bleed above the main container (e.g. the home hero). */
    hero?: ReactNode;
    className?: string;
}

export default function PublicLayout({ title, hero, className, children }: PropsWithChildren<Props>) {
    const t = useT();
    const { auth } = usePage<SharedData>().props;

    return (
        <div className="bg-background flex min-h-screen flex-col">
            {title && <Head title={title} />}
            <SiteHeader />
            {hero}
            <main className={cn('mx-auto w-full max-w-6xl flex-1 px-4 py-6 sm:px-6 sm:py-10', className)}>{children}</main>
            <footer className="border-t">
                <div className="text-muted-foreground mx-auto flex max-w-6xl flex-col gap-3 px-4 py-6 text-sm sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <p>{t('footer.disclaimer')}</p>
                    <nav className="flex gap-4">
                        <Link href={route('privacy')} className="hover:text-foreground">
                            {t('nav.privacy')}
                        </Link>
                        {auth.user?.is_admin && (
                            <a href="/admin" className="hover:text-foreground">
                                {t('nav.admin')}
                            </a>
                        )}
                    </nav>
                </div>
            </footer>
        </div>
    );
}

function useNavItems() {
    const t = useT();
    return [
        { href: route('home'), label: t('nav.home'), active: route().current('home') },
        { href: route('leaderboard'), label: t('nav.leaderboard'), active: route().current('leaderboard') || route().current('guides.*') },
        { href: route('frames.index'), label: t('nav.frames'), active: route().current('frames.*') || route().current('share.*') },
        { href: route('compare'), label: t('nav.compare'), active: route().current('compare') },
    ];
}

function SiteHeader() {
    const t = useT();
    const items = useNavItems();
    const { auth } = usePage<SharedData>().props;

    return (
        <header className="bg-background/90 supports-[backdrop-filter]:bg-background/70 sticky top-0 z-40 border-b backdrop-blur">
            <div className="mx-auto flex h-14 max-w-6xl items-center gap-3 px-4 sm:h-16 sm:px-6">
                <Link href={route('home')} className="flex min-w-0 items-center gap-2 font-semibold">
                    <span className="bg-brand text-brand-foreground flex size-8 shrink-0 items-center justify-center rounded-md">
                        <AppLogoIcon className="size-5 fill-current" />
                    </span>
                    <span className="truncate text-sm sm:text-base">{t('site.short')}</span>
                </Link>

                <nav className="ml-6 hidden items-center gap-1 md:flex">
                    {items.map((item) => (
                        <Link
                            key={item.href}
                            href={item.href}
                            className={cn(
                                'rounded-md px-3 py-2 text-sm font-medium transition-colors',
                                item.active ? 'bg-accent text-foreground' : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {item.label}
                        </Link>
                    ))}
                </nav>

                <div className="ml-auto flex items-center gap-2">
                    <LocaleSwitcher />
                    <div className="hidden items-center gap-2 md:flex">
                        <AuthLinks />
                    </div>
                    <Sheet>
                        <SheetTrigger className="hover:bg-accent rounded-md p-2 md:hidden" aria-label={t('nav.menu')}>
                            <Menu className="size-5" />
                        </SheetTrigger>
                        <SheetContent side="right" className="w-72 p-0">
                            <SheetTitle className="border-b px-5 py-4 text-left">{t('site.name')}</SheetTitle>
                            <nav className="flex flex-col p-2">
                                {items.map((item) => (
                                    <Link
                                        key={item.href}
                                        href={item.href}
                                        className={cn('rounded-md px-3 py-3 text-base', item.active ? 'bg-accent font-medium' : '')}
                                    >
                                        {item.label}
                                    </Link>
                                ))}
                                <Link href={route('privacy')} className="rounded-md px-3 py-3 text-base">
                                    {t('nav.privacy')}
                                </Link>
                            </nav>
                            <div className="flex flex-col gap-2 border-t p-4">
                                <AuthLinks stacked />
                                {auth.user?.is_admin && (
                                    <a href="/admin" className="text-muted-foreground px-1 text-sm">
                                        {t('nav.admin')}
                                    </a>
                                )}
                            </div>
                        </SheetContent>
                    </Sheet>
                </div>
            </div>
        </header>
    );
}

function AuthLinks({ stacked = false }: { stacked?: boolean }) {
    const t = useT();
    const { auth } = usePage<SharedData>().props;
    const base = 'rounded-md px-3 py-2 text-sm font-medium';

    if (auth.user) {
        return (
            <div className={cn('flex gap-2', stacked && 'flex-col')}>
                <Link href={route('dashboard')} className={cn(base, 'hover:bg-accent')}>
                    {t('nav.my_frames')}
                </Link>
                <Link href={route('logout')} method="post" as="button" className={cn(base, 'text-muted-foreground hover:bg-accent text-left')}>
                    {t('nav.logout')}
                </Link>
            </div>
        );
    }

    return (
        <div className={cn('flex gap-2', stacked && 'flex-col')}>
            <Link href={route('login')} className={cn(base, 'hover:bg-accent')}>
                {t('nav.login')}
            </Link>
            <Link href={route('register')} className={cn(base, 'bg-primary text-primary-foreground hover:opacity-90')}>
                {t('nav.register')}
            </Link>
        </div>
    );
}

function LocaleSwitcher() {
    const locale = useLocale();
    const next = locale === 'bn' ? 'en' : 'bn';

    return (
        <button
            type="button"
            onClick={() => router.post(route('locale', next), {}, { preserveScroll: true })}
            className="hover:bg-accent flex items-center gap-1 rounded-md px-2 py-2 text-sm font-medium"
            aria-label={next === 'en' ? 'Switch to English' : 'বাংলায় দেখুন'}
        >
            <Languages className="size-4" />
            {next === 'en' ? 'EN' : 'বাং'}
        </button>
    );
}
