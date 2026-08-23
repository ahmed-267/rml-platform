import { Alert, StatusBadge } from '@/Components/ui';

export interface ComparisonRow {
    field: string;
    label: string;
    submitted: string | null;
    catastro: string | null;
    survey: string | null;
}

export interface ComparisonWarning {
    code: string;
    message: string;
    severity: 'warning' | 'danger' | string;
}

export interface PreInstallationComparisonPayload {
    rows: ComparisonRow[];
    warnings: ComparisonWarning[];
}

function cell(value: string | null | undefined, emptyLabel: string): string {
    return value && value.trim() !== '' ? value : emptyLabel;
}

export function PreInstallationComparisonPanel({
    comparison,
    title,
    submittedLabel,
    catastroLabel,
    surveyLabel,
    warningsTitle,
    emptyLabel = '—',
    emptyState,
}: {
    comparison: PreInstallationComparisonPayload | null | undefined;
    title: string;
    submittedLabel: string;
    catastroLabel: string;
    surveyLabel: string;
    warningsTitle: string;
    emptyLabel?: string;
    emptyState?: string;
}) {
    if (!comparison) {
        return (
            <section className="rml-card space-y-2 p-4 sm:p-5">
                <h2 className="text-base font-semibold text-rml-text">{title}</h2>
                <p className="text-sm text-rml-muted">
                    {emptyState ?? 'Comparison data is not available yet.'}
                </p>
            </section>
        );
    }

    const warnings = comparison.warnings ?? [];

    return (
        <section className="rml-card space-y-4 p-4 sm:p-5">
            <h2 className="text-base font-semibold text-rml-text">{title}</h2>

            {warnings.length > 0 && (
                <div className="space-y-2">
                    <h3 className="text-sm font-medium text-rml-text">
                        {warningsTitle}
                    </h3>
                    <div className="flex flex-wrap gap-2">
                        {warnings.map((warning) => (
                            <StatusBadge
                                key={`${warning.code}:${warning.message}`}
                                label={warning.message}
                                tone={
                                    warning.severity === 'danger'
                                        ? 'danger'
                                        : 'warning'
                                }
                            />
                        ))}
                    </div>
                    <div className="space-y-2 sm:hidden">
                        {warnings.map((warning) => (
                            <Alert
                                key={`msg-${warning.code}:${warning.message}`}
                                variant={
                                    warning.severity === 'danger'
                                        ? 'error'
                                        : 'warning'
                                }
                            >
                                {warning.message}
                            </Alert>
                        ))}
                    </div>
                </div>
            )}

            {/* Desktop table */}
            <div className="hidden overflow-x-auto md:block">
                <table className="min-w-full text-left text-sm">
                    <thead>
                        <tr className="border-b border-rml-border text-xs uppercase tracking-wide text-rml-muted">
                            <th className="py-2 pr-3 font-medium">Field</th>
                            <th className="py-2 pr-3 font-medium">
                                {submittedLabel}
                            </th>
                            <th className="py-2 pr-3 font-medium">
                                {catastroLabel}
                            </th>
                            <th className="py-2 font-medium">{surveyLabel}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {comparison.rows.map((row) => (
                            <tr
                                key={row.field}
                                className="border-b border-rml-border/70 align-top"
                            >
                                <td className="py-2.5 pr-3 font-medium text-rml-text">
                                    {row.label}
                                </td>
                                <td className="py-2.5 pr-3 text-rml-text">
                                    {cell(row.submitted, emptyLabel)}
                                </td>
                                <td className="py-2.5 pr-3 text-rml-text">
                                    {cell(row.catastro, emptyLabel)}
                                </td>
                                <td className="py-2.5 text-rml-text">
                                    {cell(row.survey, emptyLabel)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* Mobile stacked cards */}
            <div className="space-y-3 md:hidden">
                {comparison.rows.map((row) => (
                    <div
                        key={row.field}
                        className="rounded-xl border border-rml-border p-3"
                    >
                        <p className="text-sm font-semibold text-rml-text">
                            {row.label}
                        </p>
                        <dl className="mt-2 space-y-1.5 text-sm">
                            <div className="flex justify-between gap-3">
                                <dt className="text-rml-muted">
                                    {submittedLabel}
                                </dt>
                                <dd className="text-right text-rml-text">
                                    {cell(row.submitted, emptyLabel)}
                                </dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-rml-muted">
                                    {catastroLabel}
                                </dt>
                                <dd className="text-right text-rml-text">
                                    {cell(row.catastro, emptyLabel)}
                                </dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-rml-muted">{surveyLabel}</dt>
                                <dd className="text-right text-rml-text">
                                    {cell(row.survey, emptyLabel)}
                                </dd>
                            </div>
                        </dl>
                    </div>
                ))}
            </div>
        </section>
    );
}
