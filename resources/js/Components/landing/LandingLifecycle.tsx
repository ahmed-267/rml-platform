import { useEffect, useMemo, useRef, useState } from 'react';
import {
    ClipboardCheck,
    FileUp,
    Lock,
    Send,
    Tag,
    Unlock,
    Wallet,
} from 'lucide-react';
import FloatingLeadCard from '@/Components/landing/FloatingLeadCard';
import ScrollReveal from '@/Components/landing/ScrollReveal';
import { usePrefersReducedMotion } from '@/hooks/use-prefers-reduced-motion';
import { cn } from '@/lib/cn';

interface LandingLifecycleProps {
    t: Record<string, string>;
}

const STAGE_ICONS = [
    Send,
    FileUp,
    ClipboardCheck,
    Tag,
    Lock,
    Unlock,
    Wallet,
] as const;

export default function LandingLifecycle({ t }: LandingLifecycleProps) {
    const reducedMotion = usePrefersReducedMotion();
    const sectionRef = useRef<HTMLElement | null>(null);
    const [active, setActive] = useState(0);
    const [inView, setInView] = useState(false);
    const [paused, setPaused] = useState(false);
    const [cardKey, setCardKey] = useState(0);

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

    useEffect(() => {
        const section = sectionRef.current;
        if (!section || typeof IntersectionObserver === 'undefined') {
            setInView(true);
            return;
        }

        const observer = new IntersectionObserver(
            ([entry]) => setInView(Boolean(entry?.isIntersecting)),
            { threshold: 0.35 },
        );
        observer.observe(section);
        return () => observer.disconnect();
    }, []);

    useEffect(() => {
        if (reducedMotion || !inView || paused) {
            return;
        }

        const timer = window.setInterval(() => {
            setActive((prev) => {
                const next = (prev + 1) % stages.length;
                setCardKey((key) => key + 1);
                return next;
            });
        }, 3200);

        return () => window.clearInterval(timer);
    }, [inView, paused, reducedMotion, stages.length]);

    const selectStage = (index: number) => {
        setActive(index);
        setCardKey((key) => key + 1);
        setPaused(true);
    };

    const current = stages[active] ?? stages[0];
    const CurrentIcon = STAGE_ICONS[active] ?? Send;

    return (
        <section
            ref={sectionRef}
            className="relative overflow-hidden bg-gradient-to-b from-white via-rml-background to-white py-12 sm:py-14 lg:py-16"
            aria-labelledby="lifecycle-title"
            onMouseEnter={() => setPaused(true)}
            onMouseLeave={() => setPaused(false)}
        >
            <div
                className="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-rml-primary/30 to-transparent"
                aria-hidden
            />

            <div className="relative mx-auto max-w-content px-4 sm:px-6 lg:px-8">
                <ScrollReveal className="mx-auto max-w-2xl text-center">
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

                {/* Progress rail */}
                <ScrollReveal delayMs={80} className="mt-8">
                    <ol
                        className="relative flex gap-1 overflow-x-auto pb-1 sm:grid sm:grid-cols-7 sm:gap-2 sm:overflow-visible sm:pb-0"
                        role="list"
                    >
                        <div
                            className="pointer-events-none absolute left-3 right-3 top-[1.125rem] hidden h-0.5 bg-rml-border sm:block"
                            aria-hidden
                        />
                        <div
                            className="pointer-events-none absolute left-3 top-[1.125rem] hidden h-0.5 origin-left bg-rml-primary transition-transform duration-500 ease-out sm:block"
                            style={{
                                width: 'calc(100% - 1.5rem)',
                                transform: `scaleX(${active / Math.max(stages.length - 1, 1)})`,
                            }}
                            aria-hidden
                        />
                        {stages.map((stage, index) => {
                            const Icon = STAGE_ICONS[index] ?? Send;
                            const isActive = index === active;
                            const isDone = index < active;

                            return (
                                <li
                                    key={stage.key}
                                    className="relative min-w-[4.5rem] flex-1 sm:min-w-0"
                                >
                                    <button
                                        type="button"
                                        onClick={() => selectStage(index)}
                                        className={cn(
                                            'group flex w-full flex-col items-center gap-2 rounded-xl px-1 py-2 transition rml-focus-ring',
                                            isActive
                                                ? 'bg-rml-primary-lighter'
                                                : 'hover:bg-rml-background',
                                        )}
                                        aria-current={
                                            isActive ? 'step' : undefined
                                        }
                                    >
                                        <span
                                            className={cn(
                                                'relative z-[1] flex h-9 w-9 items-center justify-center rounded-full border-2 text-xs font-bold transition duration-300',
                                                isActive &&
                                                    'scale-110 border-rml-primary bg-rml-primary text-white shadow-md shadow-emerald-900/15',
                                                isDone &&
                                                    !isActive &&
                                                    'border-rml-primary bg-rml-primary-light text-rml-primary',
                                                !isActive &&
                                                    !isDone &&
                                                    'border-rml-border bg-white text-rml-muted group-hover:border-rml-primary/40',
                                            )}
                                        >
                                            <Icon
                                                className="h-4 w-4"
                                                aria-hidden
                                            />
                                        </span>
                                        <span
                                            className={cn(
                                                'hidden text-center text-[11px] font-semibold leading-tight sm:block',
                                                isActive
                                                    ? 'text-rml-primary'
                                                    : 'text-rml-muted',
                                            )}
                                        >
                                            {stage.title}
                                        </span>
                                        <span className="font-mono text-[10px] text-rml-muted sm:hidden">
                                            {String(index + 1).padStart(2, '0')}
                                        </span>
                                    </button>
                                </li>
                            );
                        })}
                    </ol>
                </ScrollReveal>

                {/* Active stage panel */}
                <div className="mt-6 grid items-stretch gap-4 lg:grid-cols-[1.05fr_0.95fr] lg:gap-5">
                    <ScrollReveal
                        delayMs={120}
                        className="relative overflow-hidden rounded-2xl border border-rml-border bg-white p-5 shadow-sm sm:p-6"
                    >
                        <div
                            className="pointer-events-none absolute -right-8 -top-8 h-32 w-32 rounded-full bg-rml-primary/10 blur-2xl"
                            aria-hidden
                        />
                        <div className="relative flex items-start gap-3">
                            <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-rml-primary-light text-rml-primary ring-1 ring-rml-primary/15">
                                <CurrentIcon className="h-5 w-5" aria-hidden />
                            </span>
                            <div className="min-w-0 flex-1">
                                <p className="text-xs font-semibold uppercase tracking-wide text-rml-muted">
                                    {t.life_stage_label} {active + 1} /{' '}
                                    {stages.length}
                                </p>
                                <h3
                                    key={`title-${active}`}
                                    className={cn(
                                        'mt-1 text-xl font-bold text-rml-text sm:text-2xl',
                                        !reducedMotion &&
                                            'animate-[rml-stage-in_380ms_ease-out]',
                                    )}
                                >
                                    {current.title}
                                </h3>
                                <p
                                    key={`body-${active}`}
                                    className={cn(
                                        'mt-2 text-sm leading-relaxed text-rml-muted sm:text-base',
                                        !reducedMotion &&
                                            'animate-[rml-stage-in_420ms_ease-out]',
                                    )}
                                >
                                    {current.body}
                                </p>
                            </div>
                        </div>

                        <div className="relative mt-5 flex flex-wrap gap-2">
                            {stages.map((stage, index) => (
                                <button
                                    key={stage.key}
                                    type="button"
                                    onClick={() => selectStage(index)}
                                    className={cn(
                                        'h-1.5 flex-1 min-w-[2rem] rounded-full transition-all duration-300',
                                        index === active
                                            ? 'bg-rml-primary'
                                            : index < active
                                              ? 'bg-rml-primary/40'
                                              : 'bg-rml-border hover:bg-rml-muted/40',
                                    )}
                                    aria-label={`${t.life_stage_label} ${index + 1}`}
                                />
                            ))}
                        </div>
                    </ScrollReveal>

                    <ScrollReveal delayMs={160} className="relative">
                        <div className="flex h-full items-center justify-center rounded-2xl bg-gradient-to-br from-rml-sidebar via-slate-800 to-rml-sidebar p-5 ring-1 ring-slate-700/60 sm:p-6">
                            <div
                                className="pointer-events-none absolute inset-0 opacity-30"
                                style={{
                                    backgroundImage:
                                        'radial-gradient(circle at 20% 20%, rgba(22,163,74,0.35), transparent 45%), radial-gradient(circle at 80% 70%, rgba(37,99,235,0.25), transparent 40%)',
                                }}
                                aria-hidden
                            />
                            <FloatingLeadCard
                                key={cardKey}
                                className={cn(
                                    'relative w-full max-w-sm shadow-xl shadow-black/25',
                                    !reducedMotion &&
                                        'animate-[rml-stage-in_400ms_ease-out]',
                                )}
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
                                float={!reducedMotion}
                            />
                        </div>
                    </ScrollReveal>
                </div>
            </div>
        </section>
    );
}
