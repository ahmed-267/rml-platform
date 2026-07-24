import { useState } from 'react';
import ScrollReveal from '@/Components/landing/ScrollReveal';
import { Button } from '@/Components/ui/Button';
import { cn } from '@/lib/cn';

interface LandingFaqProps {
    t: Record<string, string>;
}

export default function LandingFaq({ t }: LandingFaqProps) {
    const [openFaq, setOpenFaq] = useState<number | null>(0);

    const faqs = [
        { q: t.faq_1_q, a: t.faq_1_a },
        { q: t.faq_2_q, a: t.faq_2_a },
        { q: t.faq_3_q, a: t.faq_3_a },
        { q: t.faq_4_q, a: t.faq_4_a },
        { q: t.faq_5_q, a: t.faq_5_a },
        { q: t.faq_6_q, a: t.faq_6_a },
        { q: t.faq_7_q, a: t.faq_7_a },
    ];

    return (
        <section
            className="border-y border-rml-border bg-rml-background py-16 sm:py-20"
            aria-labelledby="faq-title"
        >
            <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <ScrollReveal className="mb-10 text-center">
                    <h2
                        id="faq-title"
                        className="text-3xl font-bold tracking-tight text-rml-text sm:text-4xl"
                    >
                        {t.faq_title}
                    </h2>
                    <p className="mt-3 text-rml-muted">{t.faq_subtitle}</p>
                </ScrollReveal>

                <ScrollReveal className="rml-card divide-y divide-rml-border overflow-hidden">
                    {faqs.map((faq, index) => {
                        const open = openFaq === index;
                        return (
                            <div key={faq.q} className="px-2 py-1 sm:px-3">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    fullWidth
                                    className="h-auto justify-between gap-3 whitespace-normal px-3 py-3 text-left font-semibold text-rml-text sm:text-base"
                                    onClick={() =>
                                        setOpenFaq(open ? null : index)
                                    }
                                    aria-expanded={open}
                                >
                                    <span>{faq.q}</span>
                                    <span
                                        className="shrink-0 text-rml-muted"
                                        aria-hidden
                                    >
                                        {open ? '−' : '+'}
                                    </span>
                                </Button>
                                <div
                                    className={cn(
                                        'overflow-hidden px-3 text-sm leading-relaxed text-rml-muted transition-all',
                                        open
                                            ? 'mb-3 max-h-48 opacity-100'
                                            : 'max-h-0 opacity-0',
                                    )}
                                >
                                    {faq.a}
                                </div>
                            </div>
                        );
                    })}
                </ScrollReveal>
            </div>
        </section>
    );
}
