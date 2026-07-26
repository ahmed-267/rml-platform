import { FormEvent, useMemo, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { AuditChecklist } from '@/Components/ui/AuditChecklist';
import {
    Alert,
    BackLink,
    Button,
    StatusBadge,
    Textarea,
} from '@/Components/ui';
import { leadStatusLabel, leadStatusTone } from '@/lib/lead-status';
import type { PageProps } from '@/types';

interface ChecklistItem {
    id: string;
    key: string;
    label: string;
    required: boolean;
    checked: boolean;
    notes?: string | null;
}

interface LeadPayload {
    id: number;
    lead_reference: string;
    status: string | null;
    customer_first_name: string | null;
    customer_last_name: string | null;
    customer_phone: string | null;
    customer_whatsapp: string | null;
    customer_email: string | null;
    address_line_1: string | null;
    address_line_2: string | null;
    city: string | null;
    postcode: string | null;
    country: string | null;
    property_type: string | null;
    epc_rating: string | null;
    size_m2: number | null;
    distance_km: number | null;
    seller_name: string | null;
    created_at: string | null;
    rejection_reason: string | null;
    requested_info: string | null;
    scheme: { name: string } | null;
    zone: { code: string } | null;
    metrics: Array<{ key: string; value: string | null; label: string }>;
    evidence: Array<{
        id: number;
        original_name: string;
        file_type: string | null;
        status: string | null;
        mime_type?: string | null;
        size?: number | null;
    }>;
    audit: {
        id: number;
        status: string | null;
        audit_notes: string | null;
        rejection_reason: string | null;
        requested_info: string | null;
        is_final: boolean;
        final_decision_by: { name: string } | null;
    } | null;
}

export default function AuditorAuditShow({
    lead,
    checklist: initialChecklist,
    can_recommend,
}: {
    lead: LeadPayload;
    checklist: ChecklistItem[];
    checklist_progress: { checked: number; total: number; percent: number };
    can_recommend: boolean;
}) {
    const { translations } = usePage<PageProps>().props;
    const t = translations.auditor.audit;
    const common = translations.auditor.common;
    const auditStatuses = translations.audit_statuses ?? {};
    const leadStatuses = translations.lead_statuses;

    const [items, setItems] = useState(initialChecklist);
    const [notes, setNotes] = useState(lead.audit?.audit_notes ?? '');
    const [rejectionReason, setRejectionReason] = useState('');
    const [requestedInfo, setRequestedInfo] = useState('');
    const [processing, setProcessing] = useState(false);

    const progress = useMemo(() => {
        const checked = items.filter((i) => i.checked).length;
        const total = items.length;
        return {
            checked,
            total,
            percent: total > 0 ? Math.round((checked / total) * 100) : 0,
        };
    }, [items]);

    const checklistPayload = () =>
        items.map((item) => ({
            id: Number(item.id),
            checked: item.checked,
            notes: item.notes ?? null,
        }));

    const post = (url: string, extra: Record<string, unknown> = {}) => {
        setProcessing(true);
        router.post(
            url,
            {
                audit_notes: notes,
                checklist: checklistPayload(),
                ...extra,
            },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
            },
        );
    };

    const saveChecklist = (event: FormEvent) => {
        event.preventDefault();
        post(route('auditor.audits.checklist', lead.id));
    };

    const missingRequired = items.filter((i) => i.required && !i.checked);

    return (
        <AppLayout
            title={t.page_title}
            subtitle={lead.lead_reference}
        >
            <Head title={`${t.page_title} · ${lead.lead_reference}`} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center gap-3">
                    <BackLink
                        href={route('auditor.audits.index', {
                            tab: 'my-audits',
                        })}
                        label={common.back}
                    />
                    <p className="font-mono text-lg font-semibold text-rml-text">
                        {lead.lead_reference}
                    </p>
                </div>

                {lead.audit?.is_final && (
                    <Alert variant="info" title={t.final_decision_title}>
                        {t.final_decision_body}
                    </Alert>
                )}

                <section className="rml-card space-y-3 p-5">
                    <div className="flex flex-wrap items-center gap-2">
                        <h2 className="text-base font-semibold text-rml-text">
                            {t.summary}
                        </h2>
                        <StatusBadge
                            label={leadStatusLabel(lead.status, leadStatuses)}
                            tone={leadStatusTone(lead.status)}
                        />
                        {lead.audit?.status && (
                            <StatusBadge
                                label={
                                    auditStatuses[lead.audit.status] ??
                                    lead.audit.status
                                }
                                tone={leadStatusTone(lead.audit.status)}
                            />
                        )}
                    </div>
                    <dl className="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                        <Field label={common.lead_id} value={lead.lead_reference} mono />
                        <Field label={common.scheme} value={lead.scheme?.name} />
                        <Field label={common.zone} value={lead.zone?.code} />
                        <Field
                            label={common.size}
                            value={lead.size_m2 != null ? `${lead.size_m2} m²` : null}
                        />
                        <Field
                            label={t.distance}
                            value={
                                lead.distance_km != null
                                    ? `${lead.distance_km} km`
                                    : null
                            }
                        />
                        <Field label={common.seller} value={lead.seller_name} />
                        <Field
                            label={common.submitted}
                            value={
                                lead.created_at
                                    ? new Date(lead.created_at).toLocaleString()
                                    : null
                            }
                        />
                    </dl>
                </section>

                <section className="rml-card space-y-3 p-5">
                    <h2 className="text-base font-semibold text-rml-text">
                        {t.customer}
                    </h2>
                    <dl className="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                        <Field
                            label={t.customer_name}
                            value={`${lead.customer_first_name ?? ''} ${lead.customer_last_name ?? ''}`.trim()}
                        />
                        <Field label={t.phone} value={lead.customer_phone} />
                        <Field label={t.whatsapp} value={lead.customer_whatsapp} />
                        <Field label={t.email} value={lead.customer_email} />
                        <Field label={t.address} value={lead.address_line_1} />
                        <Field label={t.address_2} value={lead.address_line_2} />
                        <Field label={t.city} value={lead.city} />
                        <Field label={t.postcode} value={lead.postcode} />
                        <Field label={t.property_type} value={lead.property_type} />
                        <Field label={t.epc} value={lead.epc_rating} />
                    </dl>
                </section>

                <section className="rml-card space-y-3 p-5">
                    <h2 className="text-base font-semibold text-rml-text">
                        {t.metrics}
                    </h2>
                    {lead.metrics.length === 0 ? (
                        <p className="text-sm text-rml-muted">{t.metrics_empty}</p>
                    ) : (
                        <dl className="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                            {lead.metrics.map((metric) => (
                                <Field
                                    key={metric.key}
                                    label={metric.label}
                                    value={metric.value}
                                />
                            ))}
                        </dl>
                    )}
                </section>

                <section className="rml-card space-y-3 p-5">
                    <h2 className="text-base font-semibold text-rml-text">
                        {t.evidence}
                    </h2>
                    {lead.evidence.length === 0 ? (
                        <Alert variant="warning" title={t.evidence_missing}>
                            {t.evidence_missing_help}
                        </Alert>
                    ) : (
                        <ul className="divide-y divide-rml-border">
                            {lead.evidence.map((file) => (
                                <li
                                    key={file.id}
                                    className="flex flex-wrap items-center justify-between gap-2 py-3 text-sm"
                                >
                                    <div>
                                        <p className="font-medium text-rml-text">
                                            {file.original_name}
                                        </p>
                                        <p className="text-rml-muted">
                                            {file.file_type ?? '—'}
                                            {file.mime_type
                                                ? ` · ${file.mime_type}`
                                                : ''}
                                        </p>
                                    </div>
                                    <StatusBadge
                                        label={
                                            file.status
                                                ? leadStatusLabel(
                                                      file.status,
                                                      translations.statuses,
                                                  )
                                                : t.evidence_available
                                        }
                                        tone="info"
                                    />
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <form onSubmit={saveChecklist} className="space-y-4">
                    <div className="rml-card space-y-3 p-5">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h2 className="text-base font-semibold text-rml-text">
                                    {t.checklist}
                                </h2>
                                <p className="text-sm text-rml-muted">
                                    {t.checklist_progress.replace(
                                        ':percent',
                                        String(progress.percent),
                                    )}{' '}
                                    ({progress.checked}/{progress.total})
                                </p>
                            </div>
                        </div>
                        {missingRequired.length > 0 && (
                            <Alert variant="warning" title={t.required_remaining}>
                                {missingRequired.map((i) => i.label).join(', ')}
                            </Alert>
                        )}
                        <AuditChecklist
                            title={t.checklist}
                            subtitle={t.checklist_subtitle}
                            items={items.map((item) => ({
                                id: item.id,
                                label: item.label,
                                description: item.required
                                    ? t.required
                                    : undefined,
                                checked: item.checked,
                            }))}
                            readOnly={!can_recommend}
                            onChange={(id, checked) =>
                                setItems((prev) =>
                                    prev.map((item) =>
                                        item.id === id
                                            ? { ...item, checked }
                                            : item,
                                    ),
                                )
                            }
                        />
                    </div>

                    <section className="rml-card space-y-3 p-5">
                        <h2 className="text-base font-semibold text-rml-text">
                            {t.notes}
                        </h2>
                        <Textarea
                            value={notes}
                            onChange={(e) => setNotes(e.target.value)}
                            rows={4}
                            disabled={!can_recommend}
                            placeholder={t.notes_placeholder}
                        />
                        {can_recommend && (
                            <Button type="submit" variant="outline" disabled={processing}>
                                {t.save_checklist}
                            </Button>
                        )}
                    </section>
                </form>

                {can_recommend && (
                    <section className="rml-card space-y-4 p-5">
                        <h2 className="text-base font-semibold text-rml-text">
                            {t.recommendation}
                        </h2>
                        <p className="text-sm text-rml-muted">
                            {t.recommendation_help}
                        </p>

                        <div className="space-y-2">
                            <label className="text-sm font-medium text-rml-text">
                                {t.rejection_reason}
                            </label>
                            <Textarea
                                value={rejectionReason}
                                onChange={(e) =>
                                    setRejectionReason(e.target.value)
                                }
                                rows={3}
                                placeholder={t.rejection_placeholder}
                            />
                        </div>

                        <div className="space-y-2">
                            <label className="text-sm font-medium text-rml-text">
                                {t.requested_info}
                            </label>
                            <Textarea
                                value={requestedInfo}
                                onChange={(e) =>
                                    setRequestedInfo(e.target.value)
                                }
                                rows={3}
                                placeholder={t.info_placeholder}
                            />
                        </div>

                        <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                            <Button
                                type="button"
                                disabled={processing}
                                onClick={() =>
                                    post(
                                        route(
                                            'auditor.audits.recommend-accept',
                                            lead.id,
                                        ),
                                    )
                                }
                            >
                                {t.recommend_accept}
                            </Button>
                            <Button
                                type="button"
                                variant="danger"
                                disabled={processing}
                                onClick={() =>
                                    post(
                                        route(
                                            'auditor.audits.recommend-reject',
                                            lead.id,
                                        ),
                                        {
                                            rejection_reason: rejectionReason,
                                        },
                                    )
                                }
                            >
                                {t.recommend_reject}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={processing}
                                onClick={() =>
                                    post(
                                        route(
                                            'auditor.audits.request-info',
                                            lead.id,
                                        ),
                                        {
                                            requested_info: requestedInfo,
                                        },
                                    )
                                }
                            >
                                {t.request_info}
                            </Button>
                        </div>
                    </section>
                )}

                {(lead.audit?.rejection_reason ||
                    lead.audit?.requested_info ||
                    lead.rejection_reason ||
                    lead.requested_info) && (
                    <section className="rml-card space-y-2 p-5 text-sm">
                        <h2 className="text-base font-semibold text-rml-text">
                            {t.decision_history}
                        </h2>
                        {(lead.audit?.rejection_reason ||
                            lead.rejection_reason) && (
                            <p>
                                <span className="text-rml-muted">
                                    {t.rejection_reason}:{' '}
                                </span>
                                {lead.audit?.rejection_reason ??
                                    lead.rejection_reason}
                            </p>
                        )}
                        {(lead.audit?.requested_info ||
                            lead.requested_info) && (
                            <p>
                                <span className="text-rml-muted">
                                    {t.requested_info}:{' '}
                                </span>
                                {lead.audit?.requested_info ??
                                    lead.requested_info}
                            </p>
                        )}
                        {lead.audit?.final_decision_by && (
                            <p>
                                <span className="text-rml-muted">
                                    {t.final_by}:{' '}
                                </span>
                                {lead.audit.final_decision_by.name}
                            </p>
                        )}
                    </section>
                )}
            </div>
        </AppLayout>
    );
}

function Field({
    label,
    value,
    mono = false,
}: {
    label: string;
    value?: string | number | null;
    mono?: boolean;
}) {
    return (
        <div>
            <dt className="text-rml-muted">{label}</dt>
            <dd
                className={
                    mono
                        ? 'font-mono font-semibold text-rml-text'
                        : 'font-medium text-rml-text'
                }
            >
                {value || '—'}
            </dd>
        </div>
    );
}
