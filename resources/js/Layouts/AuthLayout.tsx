import { PropsWithChildren } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { CheckCircle2, ShieldCheck, Sparkles } from 'lucide-react';
import RmlLogo from '@/Components/branding/RmlLogo';
import FloatingLeadCard from '@/Components/landing/FloatingLeadCard';
import { LanguageSwitcher } from '@/Components/layout/LanguageSwitcher';
import { BackLink } from '@/Components/ui/BackLink';
import type { PageProps } from '@/types';

export interface AuthLayoutProps extends PropsWithChildren {
    title: string;
    subtitle?: string;
    wide?: boolean;
    /**
     * Fallback when history/referrer is unavailable. Defaults to `/`.
     * Pass `null` to hide the back control.
     */
    backHref?: string | null;
    backLabel?: string;
    /**
     * Auth pages use an explicit destination (`backHref`) so Back always works.
     * Opt into history only when a page truly needs it.
     */
    backUsesHistory?: boolean;
}

export default function AuthLayout({
    title,
    subtitle,
    children,
    wide = false,
    backHref = '/',
    backLabel,
    backUsesHistory = false,
}: AuthLayoutProps) {
    const { translations } = usePage<PageProps>().props;
    const auth = translations.auth ?? {};
    const common = translations.common ?? {};
    const brand = translations.brand ?? {};
    const footer =
        common.auth_footer ?? brand.footer_brand ?? 'RML Energy Saving';
    const resolvedBackLabel =
        backLabel ??
        (backHref === '/'
            ? (common.back_to_home ?? common.back ?? 'Back to home')
            : (common.back ?? 'Back'));

    const points = [
        {
            icon: ShieldCheck,
            label: auth.panel_point_1 ?? 'Evidence audited before sale',
        },
        {
            icon: CheckCircle2,
            label:
                auth.panel_point_2 ??
                'Customer details released after payment',
        },
        {
            icon: Sparkles,
            label:
                auth.panel_point_3 ??
                'Built for sellers, buyers, and auditors',
        },
    ];

    return (
        <div className="min-h-screen bg-rml-background lg:grid lg:grid-cols-2">
            <aside className="relative hidden overflow-hidden bg-rml-sidebar text-white lg:flex lg:flex-col lg:justify-between">
                <div
                    className="pointer-events-none absolute inset-0 opacity-50"
                    style={{
                        backgroundImage:
                            'radial-gradient(circle at 18% 22%, rgba(22,163,74,0.4), transparent 42%), radial-gradient(circle at 82% 12%, rgba(37,99,235,0.28), transparent 36%), linear-gradient(160deg, rgba(15,23,42,0.15), rgba(15,23,42,0.92))',
                    }}
                />
                <div
                    className="pointer-events-none absolute inset-0 opacity-[0.1]"
                    style={{
                        backgroundImage:
                            'url("data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'0.35\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")',
                    }}
                    aria-hidden
                />

                <div className="relative z-10 flex h-full flex-col justify-between p-10 xl:p-12">
                    <Link href="/" className="inline-flex w-fit">
                        <RmlLogo showWordmark variant="light" className="scale-110" />
                    </Link>

                    <div className="max-w-lg space-y-8">
                        <div className="space-y-4">
                            <p className="inline-flex items-center gap-2 rounded-full border border-green-500/30 bg-green-500/15 px-3.5 py-1.5 text-xs font-semibold text-green-300">
                                <span className="h-1.5 w-1.5 rounded-full bg-green-400" />
                                {auth.panel_eyebrow ??
                                    'Spain energy schemes marketplace'}
                            </p>
                            <h2 className="text-3xl font-extrabold leading-tight tracking-tight xl:text-4xl">
                                {auth.panel_title ??
                                    'Audited leads. Protected payments.'}
                            </h2>
                            <p className="text-base leading-relaxed text-slate-300">
                                {auth.panel_body ??
                                    'Submit, validate, and buy verified energy-saving opportunities.'}
                            </p>
                        </div>

                        <ul className="space-y-3">
                            {points.map(({ icon: Icon, label }) => (
                                <li
                                    key={label}
                                    className="flex items-start gap-3 text-sm text-slate-200"
                                >
                                    <span className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white/10 text-green-300 ring-1 ring-white/10">
                                        <Icon className="h-4 w-4" aria-hidden />
                                    </span>
                                    <span className="pt-1.5 font-medium">
                                        {label}
                                    </span>
                                </li>
                            ))}
                        </ul>

                        <FloatingLeadCard
                            reference={auth.panel_card_ref ?? 'LD-2401'}
                            zone={auth.panel_card_zone ?? 'D2'}
                            size={auth.panel_card_size ?? '92 m²'}
                            scheme={auth.panel_card_scheme ?? 'Insulation'}
                            statusLabel={
                                auth.panel_card_status ?? 'Audited'
                            }
                            detailLabel={
                                auth.panel_card_detail ??
                                'Ready for marketplace release'
                            }
                            zoneLabel="Zone"
                            sizeLabel="Size"
                            schemeLabel="Scheme"
                            statusTone="audited"
                            float
                            className="max-w-sm border-white/10 bg-white/95 shadow-2xl shadow-black/30"
                        />
                    </div>

                    <p className="text-xs text-slate-400">{footer}</p>
                </div>
            </aside>

            <div className="relative flex min-h-screen flex-col">
                <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_right,_rgba(22,163,74,0.1),_transparent_40%),radial-gradient(circle_at_bottom_left,_rgba(37,99,235,0.06),_transparent_35%)] lg:hidden" />

                <header className="relative z-10 flex items-center justify-between gap-3 px-4 py-4 sm:px-6 lg:px-10">
                    <div className="min-w-0">
                        {backHref != null && resolvedBackLabel !== '' ? (
                            <BackLink
                                href={backHref}
                                label={resolvedBackLabel}
                                showLabel
                                useHistory={backUsesHistory}
                            />
                        ) : (
                            <span />
                        )}
                    </div>
                    <div className="flex items-center gap-3">
                        <Link href="/" className="inline-flex lg:hidden">
                            <RmlLogo showWordmark />
                        </Link>
                        <LanguageSwitcher />
                    </div>
                </header>

                <main className="relative z-10 flex flex-1 items-center justify-center px-4 py-6 sm:px-6 lg:px-10 lg:py-10">
                    <div
                        className={`w-full ${wide ? 'max-w-3xl' : 'max-w-md'}`}
                    >
                        <div className="mb-6 space-y-2 lg:mb-8">
                            <div className="hidden lg:block">
                                <Link href="/" className="inline-flex">
                                    <RmlLogo showWordmark />
                                </Link>
                            </div>
                            <h1 className="text-2xl font-bold tracking-tight text-rml-text sm:text-3xl">
                                {title}
                            </h1>
                            {subtitle && (
                                <p className="max-w-xl text-sm leading-relaxed text-rml-muted sm:text-base">
                                    {subtitle}
                                </p>
                            )}
                        </div>

                        <div className="rml-card p-5 shadow-sm sm:p-8">
                            {children}
                        </div>

                        <p className="mt-6 text-center text-xs text-rml-muted lg:text-left">
                            {footer}
                        </p>
                    </div>
                </main>
            </div>
        </div>
    );
}
