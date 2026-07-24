import { Link } from '@inertiajs/react';
import { Button } from '@/Components/ui/Button';
import ScrollReveal from '@/Components/landing/ScrollReveal';

interface LandingFinalCtaProps {
    t: Record<string, string>;
}

export default function LandingFinalCta({ t }: LandingFinalCtaProps) {
    return (
        <section
            className="relative overflow-hidden bg-rml-sidebar py-16 sm:py-20"
            aria-labelledby="final-cta-title"
        >
            <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_center,_rgba(22,163,74,0.22),_transparent_55%)]" />
            <div className="relative mx-auto max-w-3xl px-4 text-center sm:px-6 lg:px-8">
                <ScrollReveal>
                    <h2
                        id="final-cta-title"
                        className="text-3xl font-bold tracking-tight text-white sm:text-4xl"
                    >
                        {t.final_title}
                    </h2>
                    <p className="mx-auto mt-4 max-w-xl text-base text-slate-300">
                        {t.final_subtitle}
                    </p>
                    <div className="mt-8 flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center">
                        <Link href={route('register.buyer')}>
                            <Button size="lg" fullWidth className="sm:w-auto">
                                {t.cta_register_buyer}
                            </Button>
                        </Link>
                        <Link href={route('register.seller')}>
                            <Button
                                size="lg"
                                fullWidth
                                className="border border-white/20 bg-white/10 text-white hover:bg-white/15 sm:w-auto"
                            >
                                {t.cta_register_seller}
                            </Button>
                        </Link>
                        <a href="#free-installation">
                            <Button
                                size="lg"
                                fullWidth
                                variant="ghost"
                                className="text-slate-200 hover:bg-white/10 hover:text-white sm:w-auto"
                            >
                                {t.cta_homeowner}
                            </Button>
                        </a>
                    </div>
                </ScrollReveal>
            </div>
        </section>
    );
}
