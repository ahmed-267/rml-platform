import ScrollReveal from '@/Components/landing/ScrollReveal';

interface LandingProductPreviewProps {
    t: Record<string, string>;
}

function MockFrame({
    title,
    rows,
}: {
    title: string;
    rows: string[];
}) {
    return (
        <div className="rml-card overflow-hidden">
            <div className="flex items-center gap-1.5 border-b border-rml-border bg-rml-background px-4 py-3">
                <span className="h-2 w-2 rounded-full bg-slate-300" />
                <span className="h-2 w-2 rounded-full bg-slate-300" />
                <span className="h-2 w-2 rounded-full bg-slate-300" />
                <span className="ml-2 text-xs font-semibold text-rml-muted">
                    {title}
                </span>
            </div>
            <div className="space-y-2 p-4">
                {rows.map((row) => (
                    <div
                        key={row}
                        className="flex items-center justify-between rounded-lg bg-rml-background px-3 py-2 text-xs text-rml-text"
                    >
                        <span className="font-medium">{row}</span>
                        <span className="h-2 w-10 rounded-full bg-rml-primary/30" />
                    </div>
                ))}
            </div>
        </div>
    );
}

export default function LandingProductPreview({
    t,
}: LandingProductPreviewProps) {
    const previews = [
        {
            title: t.preview_seller_title,
            rows: [
                t.preview_seller_row_1,
                t.preview_seller_row_2,
                t.preview_seller_row_3,
            ],
        },
        {
            title: t.preview_buyer_title,
            rows: [
                t.preview_buyer_row_1,
                t.preview_buyer_row_2,
                t.preview_buyer_row_3,
            ],
        },
        {
            title: t.preview_auditor_title,
            rows: [
                t.preview_auditor_row_1,
                t.preview_auditor_row_2,
                t.preview_auditor_row_3,
            ],
        },
        {
            title: t.preview_admin_title,
            rows: [
                t.preview_admin_row_1,
                t.preview_admin_row_2,
                t.preview_admin_row_3,
            ],
        },
        {
            title: t.preview_reports_title,
            rows: [
                t.preview_reports_row_1,
                t.preview_reports_row_2,
                t.preview_reports_row_3,
            ],
        },
    ];

    return (
        <section
            className="border-b border-rml-border bg-rml-background py-16 sm:py-20"
            aria-labelledby="preview-title"
        >
            <div className="mx-auto max-w-content px-4 sm:px-6 lg:px-8">
                <ScrollReveal className="mx-auto mb-12 max-w-2xl text-center">
                    <p className="text-sm font-semibold text-rml-primary">
                        {t.preview_eyebrow}
                    </p>
                    <h2
                        id="preview-title"
                        className="mt-2 text-3xl font-bold tracking-tight text-rml-text sm:text-4xl"
                    >
                        {t.preview_title}
                    </h2>
                    <p className="mt-3 text-rml-muted">{t.preview_subtitle}</p>
                </ScrollReveal>

                <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    {previews.map((preview, index) => (
                        <ScrollReveal key={preview.title} delayMs={index * 80}>
                            <MockFrame
                                title={preview.title}
                                rows={preview.rows}
                            />
                        </ScrollReveal>
                    ))}
                </div>
            </div>
        </section>
    );
}
