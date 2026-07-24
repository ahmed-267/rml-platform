import ScrollReveal from '@/Components/landing/ScrollReveal';
import { StatusBadge } from '@/Components/ui/StatusBadge';

interface LandingSpainSchemesProps {
    t: Record<string, string>;
}

export default function LandingSpainSchemes({ t }: LandingSpainSchemesProps) {
    const schemes = [
        {
            title: t.scheme_insulation_title,
            detail: t.scheme_insulation_detail,
            zones: ['D1', 'D2', 'E1', 'E2'],
        },
        {
            title: t.scheme_glazing_title,
            detail: t.scheme_glazing_detail,
            zones: null,
        },
        {
            title: t.scheme_heat_title,
            detail: t.scheme_heat_detail,
            zones: null,
        },
    ];

    return (
        <section
            className="bg-white py-16 sm:py-20"
            aria-labelledby="spain-title"
        >
            <div className="mx-auto max-w-content px-4 sm:px-6 lg:px-8">
                <ScrollReveal className="mx-auto mb-12 max-w-2xl text-center">
                    <p className="text-sm font-semibold text-rml-primary">
                        {t.spain_eyebrow}
                    </p>
                    <h2
                        id="spain-title"
                        className="mt-2 text-3xl font-bold tracking-tight text-rml-text sm:text-4xl"
                    >
                        {t.spain_title}
                    </h2>
                    <p className="mt-3 text-rml-muted">{t.spain_subtitle}</p>
                </ScrollReveal>

                <div className="grid gap-5 lg:grid-cols-3">
                    {schemes.map((scheme, index) => (
                        <ScrollReveal
                            key={scheme.title}
                            delayMs={index * 90}
                            className="rml-card bg-rml-background p-6"
                        >
                            <h3 className="text-xl font-bold text-rml-text">
                                {scheme.title}
                            </h3>
                            <p className="mt-3 text-sm leading-relaxed text-rml-muted">
                                {scheme.detail}
                            </p>
                            {scheme.zones && (
                                <div className="mt-5">
                                    <p className="text-xs font-semibold uppercase tracking-wide text-rml-muted">
                                        {t.spain_zones_label}
                                    </p>
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        {scheme.zones.map((zone) => (
                                            <StatusBadge
                                                key={zone}
                                                label={zone}
                                                tone="primary"
                                                mono
                                                className="px-3 py-1.5 text-sm font-bold"
                                            />
                                        ))}
                                    </div>
                                </div>
                            )}
                        </ScrollReveal>
                    ))}
                </div>
            </div>
        </section>
    );
}
