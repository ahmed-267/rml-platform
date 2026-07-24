import { Link } from '@inertiajs/react';
import FloatingLeadCard from '@/Components/landing/FloatingLeadCard';
import ScrollReveal from '@/Components/landing/ScrollReveal';
import { Button } from '@/Components/ui/Button';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { usePrefersReducedMotion } from '@/hooks/use-prefers-reduced-motion';

interface LandingHeroProps {
    t: Record<string, string>;
}

export default function LandingHero({ t }: LandingHeroProps) {
    const reducedMotion = usePrefersReducedMotion();

    const badges = [
        t.badge_audited,
        t.badge_payment_protected,
        t.badge_spain,
        t.badge_privacy,
    ];

    return (
        <section
            id="home"
            className="relative overflow-hidden bg-rml-sidebar"
            aria-labelledby="landing-hero-title"
        >
            <div
                className="pointer-events-none absolute inset-0 opacity-40"
                style={{
                    backgroundImage:
                        'radial-gradient(circle at 15% 20%, rgba(22,163,74,0.35), transparent 42%), radial-gradient(circle at 85% 10%, rgba(37,99,235,0.22), transparent 35%), linear-gradient(135deg, rgba(15,23,42,0.2), rgba(15,23,42,0.85))',
                }}
            />
            <div
                className="pointer-events-none absolute inset-0 opacity-[0.12]"
                style={{
                    backgroundImage:
                        'url("data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'0.35\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")',
                }}
                aria-hidden
            />

            <div className="relative mx-auto grid max-w-content gap-10 px-4 py-16 sm:px-6 sm:py-20 lg:grid-cols-[1.05fr_0.95fr] lg:items-center lg:gap-12 lg:px-8 lg:py-24">
                <ScrollReveal>
                    <p className="mb-4 inline-flex items-center gap-2 rounded-full border border-green-500/30 bg-green-500/15 px-3.5 py-1.5 text-xs font-semibold text-green-300">
                        <span className="h-1.5 w-1.5 rounded-full bg-green-400" />
                        {t.hero_pill}
                    </p>
                    <h1
                        id="landing-hero-title"
                        className="max-w-xl text-4xl font-extrabold leading-[1.1] tracking-tight text-white sm:text-5xl lg:text-[3.25rem]"
                    >
                        {t.hero_title}
                    </h1>
                    <p className="mt-5 max-w-xl text-base leading-relaxed text-slate-300 sm:text-lg">
                        {t.hero_subtitle}
                    </p>

                    <div className="mt-8 flex flex-wrap gap-3">
                        <Link href={route('register.buyer')}>
                            <Button size="lg">{t.cta_register_buyer}</Button>
                        </Link>
                        <Link href={route('register.seller')}>
                            <Button
                                size="lg"
                                className="border border-white/20 bg-white/10 text-white hover:bg-white/15"
                            >
                                {t.cta_register_seller}
                            </Button>
                        </Link>
                        <a href="#free-installation">
                            <Button
                                size="lg"
                                variant="ghost"
                                className="text-slate-200 hover:bg-white/10 hover:text-white"
                            >
                                {t.cta_homeowner}
                            </Button>
                        </a>
                    </div>

                    <ul className="mt-8 flex flex-wrap gap-2">
                        {badges.map((badge) => (
                            <li key={badge}>
                                <StatusBadge
                                    label={badge}
                                    tone="primary"
                                    className="bg-white/10 text-slate-100 ring-white/20"
                                />
                            </li>
                        ))}
                    </ul>
                </ScrollReveal>

                <ScrollReveal delayMs={120} direction="left" className="relative">
                    <div className="relative mx-auto max-w-lg">
                        <div className="absolute -inset-4 rounded-[2rem] bg-gradient-to-br from-rml-primary/30 via-transparent to-rml-blue/20 blur-2xl" />
                        <div className="relative overflow-hidden rounded-[1.75rem] border border-white/10 bg-slate-900/80 p-4 shadow-2xl shadow-black/40 sm:p-5">
                            <div className="mb-4 flex items-center justify-between">
                                <div>
                                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                        {t.hero_mock_label}
                                    </p>
                                    <p className="text-sm font-semibold text-white">
                                        {t.hero_mock_title}
                                    </p>
                                </div>
                                <StatusBadge
                                    label={t.hero_mock_live}
                                    tone="success"
                                    className="bg-rml-primary/20 text-green-300 ring-green-500/30"
                                />
                            </div>
                            <div className="grid gap-3 rounded-xl border border-white/10 bg-slate-950/60 p-3 sm:grid-cols-3">
                                {[
                                    t.hero_kpi_audited,
                                    t.hero_kpi_pending,
                                    t.hero_kpi_released,
                                ].map((label) => (
                                    <div
                                        key={label}
                                        className="rounded-lg bg-white/5 px-3 py-3"
                                    >
                                        <p className="text-[11px] text-slate-400">
                                            {label}
                                        </p>
                                        <p className="mt-1 font-mono text-lg font-semibold text-white">
                                            —
                                        </p>
                                    </div>
                                ))}
                            </div>
                            <div className="mt-4 space-y-2">
                                {(
                                    [
                                        {
                                            ref: 'LD-1041',
                                            zone: 'E1',
                                            status: t.status_audited,
                                            tone: 'success' as const,
                                        },
                                        {
                                            ref: 'LD-1042',
                                            zone: 'D2',
                                            status: t.status_payment_pending,
                                            tone: 'warning' as const,
                                        },
                                        {
                                            ref: 'PKG-012',
                                            zone: 'Mix',
                                            status: t.status_listed,
                                            tone: 'info' as const,
                                        },
                                    ] as const
                                ).map((row) => (
                                    <div
                                        key={row.ref}
                                        className="flex items-center justify-between gap-2 rounded-lg border border-white/10 bg-white/5 px-3 py-2.5 text-sm"
                                    >
                                        <span className="font-mono text-slate-200">
                                            {row.ref}
                                        </span>
                                        <span className="text-slate-400">
                                            {row.zone}
                                        </span>
                                        <StatusBadge
                                            label={row.status}
                                            tone={row.tone}
                                            className="shrink-0"
                                        />
                                    </div>
                                ))}
                            </div>
                        </div>

                        <FloatingLeadCard
                            className="absolute -left-2 top-8 hidden w-56 sm:block lg:-left-10"
                            float={!reducedMotion}
                            reference="LD-1041"
                            zone="E1"
                            size="112 m²"
                            scheme={t.scheme_insulation_title}
                            statusLabel={t.status_audited}
                            detailLabel={t.detail_locked}
                            zoneLabel={t.label_zone}
                            sizeLabel={t.label_size}
                            schemeLabel={t.label_scheme}
                            statusTone="audited"
                        />
                        <FloatingLeadCard
                            className="absolute -bottom-6 -right-1 hidden w-56 sm:block lg:-right-8"
                            float={!reducedMotion}
                            reference="LD-1048"
                            zone="D1"
                            size="96 m²"
                            scheme={t.scheme_heat_title}
                            statusLabel={t.status_paid}
                            detailLabel={t.detail_released}
                            zoneLabel={t.label_zone}
                            sizeLabel={t.label_size}
                            schemeLabel={t.label_scheme}
                            statusTone="released"
                        />
                    </div>
                    <p className="sr-only">{t.hero_visual_alt}</p>
                </ScrollReveal>
            </div>
        </section>
    );
}
