import {
    CheckCircle2,
    FileCheck2,
    Lock,
    MapPin,
    Scale,
    ShieldCheck,
} from 'lucide-react';
import ScrollReveal from '@/Components/landing/ScrollReveal';

interface LandingTrustSectionProps {
    t: Record<string, string>;
}

export default function LandingTrustSection({ t }: LandingTrustSectionProps) {
    const items = [
        { title: t.trust_1_title, body: t.trust_1_body, icon: ShieldCheck },
        { title: t.trust_2_title, body: t.trust_2_body, icon: FileCheck2 },
        { title: t.trust_3_title, body: t.trust_3_body, icon: Scale },
        { title: t.trust_4_title, body: t.trust_4_body, icon: Lock },
        { title: t.trust_5_title, body: t.trust_5_body, icon: CheckCircle2 },
        { title: t.trust_6_title, body: t.trust_6_body, icon: MapPin },
    ];

    return (
        <section
            className="bg-rml-sidebar py-16 text-white sm:py-20"
            aria-labelledby="trust-title"
        >
            <div className="mx-auto max-w-content px-4 sm:px-6 lg:px-8">
                <ScrollReveal className="mx-auto mb-12 max-w-2xl text-center">
                    <p className="text-sm font-semibold text-green-300">
                        {t.trust_eyebrow}
                    </p>
                    <h2
                        id="trust-title"
                        className="mt-2 text-3xl font-bold tracking-tight sm:text-4xl"
                    >
                        {t.trust_title}
                    </h2>
                    <p className="mt-3 text-slate-300">{t.trust_subtitle}</p>
                </ScrollReveal>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {items.map((item, index) => {
                        const Icon = item.icon;
                        return (
                            <ScrollReveal
                                key={item.title}
                                delayMs={index * 70}
                                className="rounded-xl border border-white/10 bg-white/5 p-5 backdrop-blur"
                            >
                                <div className="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-rml-primary/20 text-green-300">
                                    <Icon className="h-5 w-5" aria-hidden />
                                </div>
                                <h3 className="font-semibold text-white">
                                    {item.title}
                                </h3>
                                <p className="mt-2 text-sm leading-relaxed text-slate-300">
                                    {item.body}
                                </p>
                            </ScrollReveal>
                        );
                    })}
                </div>

                <ScrollReveal className="mt-10 flex flex-wrap items-center justify-center gap-x-8 gap-y-3 rounded-2xl border border-white/10 bg-white/5 px-6 py-4 text-sm font-medium text-slate-300">
                    <span>{t.trust_strip_gdpr}</span>
                    <span>{t.trust_strip_payments}</span>
                    <span>{t.trust_strip_audit}</span>
                    <span>{t.trust_strip_langs}</span>
                </ScrollReveal>
            </div>
        </section>
    );
}
