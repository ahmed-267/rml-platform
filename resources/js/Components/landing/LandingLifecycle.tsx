import { useEffect, useMemo, useRef, useState } from 'react';
import FloatingLeadCard from '@/Components/landing/FloatingLeadCard';
import ScrollReveal from '@/Components/landing/ScrollReveal';
import { Button } from '@/Components/ui/Button';
import { useIsDesktop } from '@/hooks/use-media-query';
import { usePrefersReducedMotion } from '@/hooks/use-prefers-reduced-motion';
import { cn } from '@/lib/cn';

interface LandingLifecycleProps {
    t: Record<string, string>;
}

export default function LandingLifecycle({ t }: LandingLifecycleProps) {
    const isDesktop = useIsDesktop();
    const reducedMotion = usePrefersReducedMotion();
    const sectionRef = useRef<HTMLElement | null>(null);
    const [active, setActive] = useState(0);

    const stages = useMemo(
        () => [
            {
                key: 'submit',
                title: t.life_submit_title,
                body: t.life_submit_body,
                status: t.status_submitted,
                detail: t.detail_evidence_needed,
                tone: 'pending' as const,
            },
            {
                key: 'evidence',
                title: t.life_evidence_title,
                body: t.life_evidence_body,
                status: t.status_evidence,
                detail: t.detail_docs_uploaded,
                tone: 'pending' as const,
            },
            {
                key: 'audit',
                title: t.life_audit_title,
                body: t.life_audit_body,
                status: t.status_audited,
                detail: t.detail_checklist_pass,
                tone: 'audited' as const,
            },
            {
                key: 'price',
                title: t.life_price_title,
                body: t.life_price_body,
                status: t.status_listed,
                detail: t.detail_marketplace_ready,
                tone: 'audited' as const,
            },
            {
                key: 'purchase',
                title: t.life_purchase_title,
                body: t.life_purchase_body,
                status: t.status_payment_pending,
                detail: t.detail_locked,
                tone: 'locked' as const,
            },
            {
                key: 'release',
                title: t.life_release_title,
                body: t.life_release_body,
                status: t.status_paid,
                detail: t.detail_released,
                tone: 'released' as const,
            },
            {
                key: 'payout',
                title: t.life_payout_title,
                body: t.life_payout_body,
                status: t.status_payout_due,
                detail: t.detail_seller_paid,
                tone: 'paid' as const,
            },
        ],
        [t],
    );

    const stickyEnabled = isDesktop && !reducedMotion;

    useEffect(() => {
        if (!stickyEnabled) {
            return;
        }

        const section = sectionRef.current;
        if (!section) {
            return;
        }

        const onScroll = () => {
            const rect = section.getBoundingClientRect();
            const viewport = window.innerHeight || 1;
            const total = Math.max(section.offsetHeight - viewport, 1);
            const progressed = Math.min(
                Math.max(-rect.top / total, 0),
                0.999,
            );
            const index = Math.min(
                stages.length - 1,
                Math.floor(progressed * stages.length),
            );
            setActive(index);
        };

        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, [stickyEnabled, stages.length]);

    const current = stages[active] ?? stages[0];

    return (
        <section
            ref={sectionRef}
            className={cn(
                'relative bg-gradient-to-b from-rml-background via-white to-rml-background',
                stickyEnabled ? 'h-[100vh]' : 'py-12 sm:py-16',
            )}
            aria-labelledby="lifecycle-title"
        >
            <div
                className={cn(
                    'mx-auto max-w-content px-4 sm:px-6 lg:px-8',
                    stickyEnabled && 'sticky top-20 py-6 lg:py-8',
                )}
            >
                <ScrollReveal className="mb-6 max-w-2xl">
                    <p className="text-sm font-semibold text-rml-primary">
                        {t.life_eyebrow}
                    </p>
                    <h2
                        id="lifecycle-title"
                        className="mt-2 text-3xl font-bold tracking-tight text-rml-text sm:text-4xl"
                    >
                        {t.life_title}
                    </h2>
                    <p className="mt-3 text-rml-muted">{t.life_subtitle}</p>
                </ScrollReveal>

                {stickyEnabled ? (
                    <div className="grid gap-6 lg:grid-cols-[0.95fr_1.05fr] lg:items-start lg:gap-8">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wide text-rml-muted">
                                {t.life_stage_label} {active + 1} /{' '}
                                {stages.length}
                            </p>
                            <h3 className="mt-2 text-2xl font-bold text-rml-text">
                                {current.title}
                            </h3>
                            <p className="mt-2 max-w-md text-base leading-relaxed text-rml-muted">
                                {current.body}
                            </p>
                            <ol className="mt-4 space-y-1" role="list">
                                {stages.map((stage, index) => {
                                    const isActive = index === active;
                                    return (
                                        <li key={stage.key}>
                                            <Button
                                                type="button"
                                                variant={
                                                    isActive ? 'soft' : 'ghost'
                                                }
                                                size="sm"
                                                fullWidth
                                                className={cn(
                                                    'justify-start font-medium',
                                                    isActive
                                                        ? 'font-semibold'
                                                        : 'text-rml-muted',
                                                )}
                                                onClick={() => setActive(index)}
                                                aria-current={
                                                    isActive
                                                        ? 'step'
                                                        : undefined
                                                }
                                            >
                                                <span className="font-mono text-xs tabular-nums">
                                                    {String(index + 1).padStart(
                                                        2,
                                                        '0',
                                                    )}
                                                </span>
                                                {stage.title}
                                            </Button>
                                        </li>
                                    );
                                })}
                            </ol>
                        </div>
                        <div className="relative flex items-center justify-center rounded-2xl bg-gradient-to-br from-rml-primary/10 via-white to-rml-blue/10 p-5 ring-1 ring-rml-border/60 lg:min-h-[280px]">
                            <FloatingLeadCard
                                className="relative w-full max-w-sm"
                                reference="LD-1041"
                                zone="E1"
                                size="112 m²"
                                scheme={t.scheme_insulation_title}
                                statusLabel={current.status}
                                detailLabel={current.detail}
                                zoneLabel={t.label_zone}
                                sizeLabel={t.label_size}
                                schemeLabel={t.label_scheme}
                                statusTone={current.tone}
                            />
                        </div>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {stages.map((stage, index) => (
                            <ScrollReveal
                                key={stage.key}
                                delayMs={index * 40}
                                className="rml-card p-5"
                            >
                                <p className="font-mono text-xs text-rml-muted">
                                    {String(index + 1).padStart(2, '0')}
                                </p>
                                <h3 className="mt-1 text-lg font-bold text-rml-text">
                                    {stage.title}
                                </h3>
                                <p className="mt-2 text-sm text-rml-muted">
                                    {stage.body}
                                </p>
                            </ScrollReveal>
                        ))}
                    </div>
                )}
            </div>
        </section>
    );
}
