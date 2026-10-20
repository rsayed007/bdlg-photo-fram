import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';

export type Translate = (key: string, replace?: Record<string, string | number>) => string;

/**
 * UI strings come from lang/{bn,en}.json via Inertia shared props.
 * Placeholders use Laravel's `:name` syntax.
 */
export function useT(): Translate {
    const { translations } = usePage<SharedData>().props;

    return useCallback(
        (key, replace) => {
            let value = translations?.[key] ?? key;
            if (replace) {
                for (const [k, v] of Object.entries(replace)) {
                    value = value.replaceAll(`:${k}`, String(v));
                }
            }
            return value;
        },
        [translations],
    );
}

export function useLocale(): 'bn' | 'en' {
    return usePage<SharedData>().props.locale ?? 'bn';
}
