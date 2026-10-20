import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useT } from '@/lib/i18n';
import { type SharedData } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, LoaderCircle, ShieldAlert } from 'lucide-react';
import { type FormEvent } from 'react';

interface Props {
    /** 'back' keeps the user on the current page (e.g. inside the frame editor). */
    redirect?: 'back' | 'profile';
    compact?: boolean;
}

/** Module A: paste a profile URL, short link or the app's share text. */
export default function AddProfileForm({ redirect = 'profile', compact = false }: Props) {
    const t = useT();
    const { flash } = usePage<SharedData>().props;
    const { data, setData, post, processing, errors, reset } = useForm({ profile_url: '', redirect });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('guides.store'), { preserveScroll: true, onSuccess: () => reset('profile_url') });
    };

    const queued = flash?.queued;

    return (
        <form onSubmit={submit} className="space-y-3">
            <div className="space-y-2">
                <Label htmlFor="profile_url">{t('add.label')}</Label>
                <textarea
                    id="profile_url"
                    name="profile_url"
                    rows={compact ? 2 : 3}
                    required
                    value={data.profile_url}
                    onChange={(e) => setData('profile_url', e.target.value)}
                    placeholder={t('add.placeholder')}
                    className="border-input bg-background placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-base shadow-xs focus-visible:ring-2 focus-visible:outline-none md:text-sm"
                    autoComplete="off"
                    spellCheck={false}
                />
                <p className="text-muted-foreground text-xs">{t('add.help')}</p>
                <InputError message={errors.profile_url} />
            </div>

            <div className="flex items-start gap-2 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100">
                <ShieldAlert className="mt-0.5 size-4 shrink-0" />
                <p>{t('add.restricted_note')}</p>
            </div>

            {queued && redirect === 'back' && (
                <div className="flex items-start gap-2 rounded-md border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900 dark:border-emerald-700 dark:bg-emerald-950 dark:text-emerald-100">
                    <CheckCircle2 className="mt-0.5 size-4 shrink-0" />
                    <p>
                        {!queued.created
                            ? t('add.exists')
                            : queued.position !== null
                              ? t('add.queued', { position: queued.position })
                              : t('add.queued_no_position')}
                    </p>
                </div>
            )}

            <Button type="submit" disabled={processing} className="w-full sm:w-auto">
                {processing && <LoaderCircle className="size-4 animate-spin" />}
                {processing ? t('add.submitting') : t('add.submit')}
            </Button>
        </form>
    );
}
