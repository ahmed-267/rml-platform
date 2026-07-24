import { Link } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import { Button } from '@/Components/ui/Button';
import ScrollReveal from '@/Components/landing/ScrollReveal';

interface LandingBuyerSellerSplitProps {
    t: Record<string, string>;
}

export default function LandingBuyerSellerSplit({
    t,
}: LandingBuyerSellerSplitProps) {
    const sellerPoints = [
        t.split_sell_1,
        t.split_sell_2,
        t.split_sell_3,
        t.split_sell_4,
    ];
    const buyerPoints = [
        t.split_buy_1,
        t.split_buy_2,
        t.split_buy_3,
        t.split_buy_4,
    ];

    return (
        <section
            id="buy-leads"
            className="scroll-mt-20 border-y border-rml-border bg-white py-16 sm:py-20"
            aria-labelledby="split-title"
        >
            <div className="mx-auto max-w-content px-4 sm:px-6 lg:px-8">
                <ScrollReveal className="mx-auto mb-12 max-w-2xl text-center">
                    <h2
                        id="split-title"
                        className="text-3xl font-bold tracking-tight text-rml-text sm:text-4xl"
                    >
                        {t.split_title}
                    </h2>
                    <p className="mt-3 text-rml-muted">{t.split_subtitle}</p>
                </ScrollReveal>

                <div className="grid gap-6 lg:grid-cols-2">
                    <div id="sell-leads" className="scroll-mt-20">
                        <ScrollReveal className="rml-card overflow-hidden bg-gradient-to-br from-rml-primary-lighter via-white to-white p-7 sm:p-8">
                            <p className="text-sm font-semibold text-rml-primary">
                                {t.split_sell_eyebrow}
                            </p>
                            <h3 className="mt-2 text-2xl font-bold text-rml-text">
                                {t.split_sell_title}
                            </h3>
                            <p className="mt-3 text-sm leading-relaxed text-rml-muted">
                                {t.split_sell_body}
                            </p>
                            <ul className="mt-6 space-y-3">
                                {sellerPoints.map((point) => (
                                    <li
                                        key={point}
                                        className="flex gap-2 text-sm text-rml-text"
                                    >
                                        <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-rml-primary" />
                                        <span>{point}</span>
                                    </li>
                                ))}
                            </ul>
                            <div className="mt-8">
                                <Link href={route('register.seller')}>
                                    <Button size="lg">{t.split_sell_cta}</Button>
                                </Link>
                            </div>
                        </ScrollReveal>
                    </div>

                    <ScrollReveal
                        delayMs={100}
                        className="overflow-hidden rounded-3xl border border-slate-800 bg-rml-sidebar p-7 text-white shadow-xl sm:p-8"
                    >
                        <p className="text-sm font-semibold text-green-300">
                            {t.split_buy_eyebrow}
                        </p>
                        <h3 className="mt-2 text-2xl font-bold">{t.split_buy_title}</h3>
                        <p className="mt-3 text-sm leading-relaxed text-slate-300">
                            {t.split_buy_body}
                        </p>
                        <ul className="mt-6 space-y-3">
                            {buyerPoints.map((point) => (
                                <li
                                    key={point}
                                    className="flex gap-2 text-sm text-slate-100"
                                >
                                    <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-green-400" />
                                    <span>{point}</span>
                                </li>
                            ))}
                        </ul>
                        <div className="mt-8">
                            <Link href={route('register.buyer')}>
                                <Button size="lg">{t.split_buy_cta}</Button>
                            </Link>
                        </div>
                    </ScrollReveal>
                </div>
            </div>
        </section>
    );
}
