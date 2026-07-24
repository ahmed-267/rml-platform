import { router, usePage } from '@inertiajs/react';
import { cn } from '@/lib/cn';
import type { PageProps } from '@/types';

const labels: Record<string, string> = {
    en: 'EN',
    es: 'ES',
    fr: 'FR',
};

export interface LanguageSwitcherProps {
    className?: string;
    variant?: 'light' | 'dark';
}

export function LanguageSwitcher({
    className,
    variant = 'light',
}: LanguageSwitcherProps) {
    const { app, translations } = usePage<PageProps>().props;
    const current = app.locale || 'en';
    const locales = app.locales ?? ['en', 'es', 'fr'];
    const languageLabel = translations.common?.language ?? 'Language';

    const setLocale = (locale: string) => {
        if (locale === current) {
            return;
        }

        router.post(
            route('locale.update'),
            { locale },
            { preserveScroll: true, preserveState: false },
        );
    };

    return (
        <div
            className={cn(
                'inline-flex items-center gap-1 rounded-lg border p-0.5 text-xs font-semibold',
                variant === 'dark'
                    ? 'border-white/15 bg-white/5 text-white'
                    : 'border-rml-border bg-white text-rml-muted',
                className,
            )}
            role="group"
            aria-label={languageLabel}
        >
            {locales.map((locale) => {
                const active = locale === current;
                return (
                    <button
                        key={locale}
                        type="button"
                        onClick={() => setLocale(locale)}
                        className={cn(
                            'rounded-md px-2 py-1 transition',
                            active
                                ? variant === 'dark'
                                    ? 'bg-white/15 text-white'
                                    : 'bg-rml-primary-light text-rml-primary'
                                : variant === 'dark'
                                  ? 'hover:bg-white/10 hover:text-white'
                                  : 'hover:bg-rml-background hover:text-rml-text',
                        )}
                        aria-pressed={active}
                    >
                        {labels[locale] ?? locale.toUpperCase()}
                    </button>
                );
            })}
        </div>
    );
}
