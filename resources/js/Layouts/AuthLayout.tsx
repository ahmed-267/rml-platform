import { PropsWithChildren } from 'react';
import { Link, usePage } from '@inertiajs/react';
import RmlLogo from '@/Components/branding/RmlLogo';
import { LanguageSwitcher } from '@/Components/layout/LanguageSwitcher';
import type { PageProps } from '@/types';

export interface AuthLayoutProps extends PropsWithChildren {
    title: string;
    subtitle?: string;
    wide?: boolean;
}

export default function AuthLayout({
    title,
    subtitle,
    children,
    wide = false,
}: AuthLayoutProps) {
    const { translations } = usePage<PageProps>().props;
    const footer =
        translations.common?.auth_footer ?? 'RML Energy Exchange · EN / ES / FR';

    return (
        <div className="relative min-h-screen overflow-hidden bg-rml-background">
            <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_right,_rgba(22,163,74,0.12),_transparent_40%),radial-gradient(circle_at_bottom_left,_rgba(37,99,235,0.08),_transparent_35%)]" />

            <div className="relative flex min-h-screen flex-col justify-center px-4 py-10 sm:px-6 lg:px-8">
                <div className="absolute right-4 top-4 sm:right-6 sm:top-6">
                    <LanguageSwitcher />
                </div>

                <div
                    className={`mx-auto w-full ${wide ? 'max-w-3xl' : 'max-w-md'}`}
                >
                    <div className="mb-8 flex flex-col items-center text-center">
                        <Link href="/" className="inline-flex">
                            <RmlLogo showWordmark className="scale-110" />
                        </Link>
                        <h1 className="mt-6 text-2xl font-bold text-rml-text">
                            {title}
                        </h1>
                        {subtitle && (
                            <p className="mt-2 max-w-xl text-sm text-rml-muted">
                                {subtitle}
                            </p>
                        )}
                    </div>

                    <div className="rml-card p-6 sm:p-8">{children}</div>

                    <p className="mt-6 text-center text-xs text-rml-muted">
                        {footer}
                    </p>
                </div>
            </div>
        </div>
    );
}
