import {
    ClipboardCheck,
    LockKeyhole,
    ShoppingCart,
    Upload,
} from 'lucide-react';
import ScrollReveal from '@/Components/landing/ScrollReveal';

interface LandingMarketplaceRevealProps {
    t: Record<string, string>;
}

export default function LandingMarketplaceReveal({
    t,
}: LandingMarketplaceRevealProps) {
    const steps = [
        {
            title: t.reveal_1_title,
            body: t.reveal_1_body,
            icon: Upload,
        },
        {
            title: t.reveal_2_title,
            body: t.reveal_2_body,
            icon: ClipboardCheck,
        },
        {
            title: t.reveal_3_title,
            body: t.reveal_3_body,
            icon: ShoppingCart,
        },
        {
            title: t.reveal_4_title,
            body: t.reveal_4_body,
            icon: LockKeyhole,
        },
    ];

    return (
        <section
            className="border-b border-rml-border bg-white py-16 sm:py-20"
            aria-labelledby="marketplace-reveal-title"
        >
            <div className="mx-auto max-w-content px-4 sm:px-6 lg:px-8">
                <ScrollReveal className="mx-auto mb-12 max-w-2xl text-center">
                    <p className="text-sm font-semibold text-rml-primary">
                        {t.reveal_eyebrow}
                    </p>
                    <h2
                        id="marketplace-reveal-title"
                        className="mt-2 text-3xl font-bold tracking-tight text-rml-text sm:text-4xl"
                    >
                        {t.reveal_title}
                    </h2>
                    <p className="mt-3 text-base text-rml-muted sm:text-lg">
                        {t.reveal_subtitle}
                    </p>
                </ScrollReveal>

                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    {steps.map((step, index) => {
                        const Icon = step.icon;
                        return (
                            <ScrollReveal
                                key={step.title}
                                delayMs={index * 90}
                                className="rml-card group relative overflow-hidden bg-rml-background p-6"
                            >
                                <div className="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-rml-primary to-emerald-400 opacity-80" />
                                <div className="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-rml-primary-light text-rml-primary">
                                    <Icon className="h-5 w-5" aria-hidden />
                                </div>
                                <p className="text-xs font-semibold uppercase tracking-wide text-rml-muted">
                                    {t.reveal_step_label} {index + 1}
                                </p>
                                <h3 className="mt-2 text-lg font-bold text-rml-text">
                                    {step.title}
                                </h3>
                                <p className="mt-2 text-sm leading-relaxed text-rml-muted">
                                    {step.body}
                                </p>
                            </ScrollReveal>
                        );
                    })}
                </div>
            </div>
        </section>
    );
}
