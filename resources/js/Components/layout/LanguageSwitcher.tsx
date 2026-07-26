import { Fragment } from 'react';
import { Menu, MenuButton, MenuItem, MenuItems, Transition } from '@headlessui/react';
import { Check, ChevronDown } from 'lucide-react';
import { router, usePage } from '@inertiajs/react';
import { cn } from '@/lib/cn';
import type { PageProps } from '@/types';

const localeMeta: Record<
    string,
    { name: string; flag: string }
> = {
    en: { name: 'English', flag: '🇬🇧' },
    es: { name: 'Español', flag: '🇪🇸' },
    fr: { name: 'Français', flag: '🇫🇷' },
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
    const active = localeMeta[current] ?? {
        name: current.toUpperCase(),
        flag: '🏳️',
    };

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
        <Menu as="div" className={cn('relative inline-block text-left', className)}>
            <MenuButton
                className={cn(
                    'inline-flex items-center gap-2 rounded-lg border px-2.5 py-1.5 text-sm font-medium transition rml-focus-ring',
                    variant === 'dark'
                        ? 'border-white/15 bg-white/5 text-white hover:bg-white/10'
                        : 'border-rml-border bg-white text-rml-text hover:bg-rml-background',
                )}
                aria-label={languageLabel}
            >
                <span aria-hidden className="text-base leading-none">
                    {active.flag}
                </span>
                <span className="hidden sm:inline">{active.name}</span>
                <ChevronDown className="h-3.5 w-3.5 opacity-60" aria-hidden />
            </MenuButton>

            <Transition
                as={Fragment}
                enter="transition ease-out duration-100"
                enterFrom="transform opacity-0 scale-95"
                enterTo="transform opacity-100 scale-100"
                leave="transition ease-in duration-75"
                leaveFrom="transform opacity-100 scale-100"
                leaveTo="transform opacity-0 scale-95"
            >
                <MenuItems
                    className={cn(
                        'absolute right-0 z-50 mt-1.5 w-44 origin-top-right rounded-xl border p-1 shadow-lg focus:outline-none',
                        variant === 'dark'
                            ? 'border-white/10 bg-rml-sidebar text-white'
                            : 'border-rml-border bg-white text-rml-text',
                    )}
                >
                    {locales.map((locale) => {
                        const meta = localeMeta[locale] ?? {
                            name: locale.toUpperCase(),
                            flag: '🏳️',
                        };
                        const selected = locale === current;

                        return (
                            <MenuItem key={locale}>
                                {({ focus }) => (
                                    <button
                                        type="button"
                                        onClick={() => setLocale(locale)}
                                        className={cn(
                                            'flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-sm',
                                            focus &&
                                                (variant === 'dark'
                                                    ? 'bg-white/10'
                                                    : 'bg-rml-background'),
                                            selected && 'font-semibold',
                                        )}
                                    >
                                        <span aria-hidden>{meta.flag}</span>
                                        <span className="flex-1">{meta.name}</span>
                                        {selected && (
                                            <Check
                                                className="h-4 w-4 text-rml-primary"
                                                aria-hidden
                                            />
                                        )}
                                    </button>
                                )}
                            </MenuItem>
                        );
                    })}
                </MenuItems>
            </Transition>
        </Menu>
    );
}
