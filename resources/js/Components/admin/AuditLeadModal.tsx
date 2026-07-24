import { FormEvent, ReactNode, useEffect, useMemo, useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import {
    AuditChecklist,
    Button,
    Drawer,
    FormInput,
    Modal,
    Textarea,
} from '@/Components/ui';
import { useIsMobile } from '@/hooks/use-media-query';
import { formatDate, formatMoney } from '@/lib/admin-helpers';
import { leadStatusLabel, leadStatusTone } from '@/lib/lead-status';
import type { PageProps } from '@/types';
import { StatusBadge } from '@/Components/ui/StatusBadge';

export interface AuditChecklistItemPayload {
    id: number;
    key: string;
    label: string;
    required?: boolean;
}

export interface AuditPricingPayload {
    suggested_selling_price: number | null;
    price_per_m2: number | null;
    size_m2: number | null;
    zone_code: string | null;
    formula: string | null;
    buying_price: number | null;
    selling_price: number | null;
    expected_margin: number | null;
}

export interface AuditEvidenceFile {
    id: number;
    file_type: string | null;
    original_name: string | null;
    mime_type: string | null;
    size: number | null;
    view_url?: string | null;
    download_url?: string | null;
    is_image?: boolean;
}

export interface AuditLeadPayload {
    id: number;
    lead_reference: string;
    status: string | null;
    customer_first_name: string;
    customer_last_name: string;
    customer_phone: string;
    customer_email: string | null;
    address_line_1: string | null;
    city: string | null;
    postcode: string | null;
    buying_price?: number | null;
    selling_price?: number | null;
    expected_margin?: number | null;
    scheme: { id: number; name: string } | null;
    zone: { id: number; code: string; name: string } | null;
    size_m2: number | null;
    seller?: { id: number; name: string; email: string } | null;
    seller_company?: { id: number; name: string } | null;
    evidence: AuditEvidenceFile[];
    created_at?: string | null;
    submitted_at?: string | null;
}

export interface AuditPayload {
    checklist: AuditChecklistItemPayload[];
    pricing: AuditPricingPayload;
    lead: AuditLeadPayload;
}

export interface AuditLeadModalProps {
    open: boolean;
    onClose: () => void;
    leadId: number;
    /** Optional preloaded payload; modal will refetch when missing. */
    audit?: AuditPayload | null;
}

type DecisionMode = 'accept' | 'reject' | 'request_info' | null;

function Shell({
    open,
    onClose,
    title,
    subtitle,
    children,
    footer,
    isMobile,
}: {
    open: boolean;
    onClose: () => void;
    title: string;
    subtitle?: string;
    children: ReactNode;
    footer?: ReactNode;
    isMobile: boolean;
}) {
    if (isMobile) {
        return (
            <Drawer
                open={open}
                onClose={onClose}
                title={title}
                description={subtitle}
                footer={footer}
                side="bottom"
                className="max-h-[92vh]"
            >
                {children}
            </Drawer>
        );
    }

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={title}
            description={subtitle}
            size="xl"
            footer={footer}
        >
            {children}
        </Modal>
    );
}

function isAuditPayload(value: unknown): value is AuditPayload {
    if (!value || typeof value !== 'object') {
        return false;
    }

    const record = value as Record<string, unknown>;
    const lead = record.lead;

    return (
        Array.isArray(record.checklist) &&
        !!record.pricing &&
        typeof record.pricing === 'object' &&
        !!lead &&
        typeof lead === 'object' &&
        typeof (lead as AuditLeadPayload).lead_reference === 'string'
    );
}

export function AuditLeadModal({
    open,
    onClose,
    leadId,
    audit,
}: AuditLeadModalProps) {
    const { translations, app } = usePage<PageProps>().props;
    const t = translations.admin.audit;
    const common = translations.admin.common;
    const leadStatuses = translations.lead_statuses;
    const isMobile = useIsMobile();

    const [decision, setDecision] = useState<DecisionMode>(null);
    const [checklistState, setChecklistState] = useState<
        Record<string, boolean>
    >({});
    const [checklistError, setChecklistError] = useState<string | null>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const [loadedAudit, setLoadedAudit] = useState<AuditPayload | null>(
        audit && isAuditPayload(audit) ? audit : null,
    );
    const [loadingAudit, setLoadingAudit] = useState(false);
    const [loadError, setLoadError] = useState<string | null>(null);

    const { data, setData, errors, reset, clearErrors } = useForm({
        audit_notes: '',
        buying_price: '' as string | number,
        selling_price: '' as string | number,
        override_reason: '',
        rejection_reason: '',
        requested_info: '',
        checklist: [] as Array<{
            id: number;
            checked: boolean;
            notes?: string;
        }>,
    });

    useEffect(() => {
        if (!open) {
            setDecision(null);
            setChecklistError(null);
            setLoadError(null);
            setSubmitting(false);
            return;
        }

        if (audit && isAuditPayload(audit)) {
            setLoadedAudit(audit);
            setLoadingAudit(false);
            setLoadError(null);
            return;
        }

        let cancelled = false;
        setLoadingAudit(true);
        setLoadError(null);
        setLoadedAudit(null);

        const url = route('admin.leads.audit.data', leadId);

        fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then(async (response) => {
                const contentType = response.headers.get('content-type') ?? '';
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                if (!contentType.includes('application/json')) {
                    throw new Error('non-json');
                }
                return response.json();
            })
            .then((payload: unknown) => {
                if (cancelled) {
                    return;
                }
                if (!isAuditPayload(payload)) {
                    throw new Error('invalid-payload');
                }
                setLoadedAudit(payload);
                setLoadError(null);
            })
            .catch(() => {
                if (!cancelled) {
                    setLoadedAudit(null);
                    setLoadError(t.load_error ?? common.empty);
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setLoadingAudit(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [open, audit, leadId]);

    useEffect(() => {
        if (!loadedAudit || !open) {
            return;
        }

        const checklist = loadedAudit.checklist.map((item) => ({
            id: item.id,
            checked: false,
        }));

        setData({
            audit_notes: '',
            buying_price: loadedAudit.pricing.buying_price ?? '',
            selling_price:
                loadedAudit.pricing.selling_price ??
                loadedAudit.pricing.suggested_selling_price ??
                '',
            override_reason: '',
            rejection_reason: '',
            requested_info: '',
            checklist,
        });

        setChecklistState(
            Object.fromEntries(
                loadedAudit.checklist.map((item) => [String(item.id), false]),
            ),
        );
        setDecision(null);
        setChecklistError(null);
        clearErrors();
    }, [loadedAudit, open]);

    const checklistItems = useMemo(() => {
        if (!loadedAudit) {
            return [];
        }

        return loadedAudit.checklist.map((item) => ({
            id: String(item.id),
            label: item.label,
            checked: checklistState[String(item.id)] ?? false,
            required: Boolean(item.required),
        }));
    }, [loadedAudit, checklistState]);

    const checklistComplete = useMemo(() => {
        if (!loadedAudit || loadedAudit.checklist.length === 0) {
            return true;
        }

        return loadedAudit.checklist.every(
            (item) => checklistState[String(item.id)],
        );
    }, [loadedAudit, checklistState]);

    const checkedCount = useMemo(
        () => Object.values(checklistState).filter(Boolean).length,
        [checklistState],
    );

    const buildChecklistPayload = (nextState: Record<string, boolean>) =>
        loadedAudit?.checklist.map((item) => ({
            id: item.id,
            checked: nextState[String(item.id)] ?? false,
        })) ?? [];

    const handleChecklistChange = (id: string, checked: boolean) => {
        setChecklistState((prev) => {
            const next = { ...prev, [id]: checked };
            setData('checklist', buildChecklistPayload(next));
            return next;
        });
        setChecklistError(null);
    };

    const finishSuccess = () => {
        setSubmitting(false);
        reset();
        setDecision(null);
        setChecklistError(null);
        onClose();
    };

    const submitAccept = (event?: FormEvent) => {
        event?.preventDefault();

        if (!checklistComplete) {
            setChecklistError(t.checklist_incomplete);
            setDecision(null);
            return;
        }

        setSubmitting(true);

        router.post(
            route('admin.leads.audit.accept', leadId),
            {
                audit_notes: data.audit_notes || undefined,
                buying_price: data.buying_price,
                selling_price: data.selling_price,
                override_reason: data.override_reason || undefined,
                checklist: buildChecklistPayload(checklistState),
            },
            {
                preserveScroll: true,
                onSuccess: finishSuccess,
                onError: (formErrors) => {
                    setSubmitting(false);
                    if (formErrors.checklist) {
                        setChecklistError(formErrors.checklist);
                    }
                    if (formErrors.override_reason) {
                        setDecision('accept');
                    }
                },
                onFinish: () => setSubmitting(false),
            },
        );
    };

    const submitReject = (event: FormEvent) => {
        event.preventDefault();
        if (data.rejection_reason.trim().length < 5) {
            return;
        }
        setSubmitting(true);
        router.post(
            route('admin.leads.audit.reject', leadId),
            {
                rejection_reason: data.rejection_reason,
                audit_notes: data.audit_notes || undefined,
                checklist: buildChecklistPayload(checklistState),
            },
            {
                preserveScroll: true,
                onSuccess: finishSuccess,
                onFinish: () => setSubmitting(false),
            },
        );
    };

    const submitRequestInfo = (event: FormEvent) => {
        event.preventDefault();
        if (data.requested_info.trim().length < 5) {
            return;
        }
        setSubmitting(true);
        router.post(
            route('admin.leads.audit.request-info', leadId),
            {
                requested_info: data.requested_info,
                audit_notes: data.audit_notes || undefined,
                checklist: buildChecklistPayload(checklistState),
            },
            {
                preserveScroll: true,
                onSuccess: finishSuccess,
                onFinish: () => setSubmitting(false),
            },
        );
    };

    const lead = loadedAudit?.lead;
    const pricing = loadedAudit?.pricing;
    const ready = !loadingAudit && !!loadedAudit && !!lead;

    const footer = (
        <div className="flex w-full flex-col gap-3">
            {decision === 'accept' && ready && (
                <form onSubmit={submitAccept} className="space-y-2">
                    <p className="text-sm text-rml-text">{t.accept_confirm}</p>
                    <div className="flex flex-wrap justify-end gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => setDecision(null)}
                            disabled={submitting}
                        >
                            {common.cancel}
                        </Button>
                        <Button
                            type="submit"
                            disabled={submitting || !checklistComplete}
                        >
                            {t.accept}
                        </Button>
                    </div>
                </form>
            )}

            {decision === 'reject' && ready && (
                <form onSubmit={submitReject} className="space-y-2">
                    <Textarea
                        label={t.rejection_reason}
                        name="rejection_reason"
                        required
                        value={data.rejection_reason}
                        error={errors.rejection_reason}
                        onChange={(e) =>
                            setData('rejection_reason', e.target.value)
                        }
                    />
                    <div className="flex flex-wrap justify-end gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => setDecision(null)}
                            disabled={submitting}
                        >
                            {common.cancel}
                        </Button>
                        <Button
                            type="submit"
                            variant="danger"
                            disabled={
                                submitting ||
                                data.rejection_reason.trim().length < 5
                            }
                        >
                            {t.reject}
                        </Button>
                    </div>
                </form>
            )}

            {decision === 'request_info' && ready && (
                <form onSubmit={submitRequestInfo} className="space-y-2">
                    <Textarea
                        label={t.requested_info}
                        name="requested_info"
                        required
                        value={data.requested_info}
                        error={errors.requested_info}
                        onChange={(e) =>
                            setData('requested_info', e.target.value)
                        }
                    />
                    <div className="flex flex-wrap justify-end gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => setDecision(null)}
                            disabled={submitting}
                        >
                            {common.cancel}
                        </Button>
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={
                                submitting ||
                                data.requested_info.trim().length < 5
                            }
                        >
                            {t.request_info}
                        </Button>
                    </div>
                </form>
            )}

            {decision === null && (
                <div className="flex w-full flex-col gap-2 sm:flex-row sm:justify-end">
                    <Button
                        variant="ghost"
                        onClick={onClose}
                        disabled={submitting}
                    >
                        {common.cancel}
                    </Button>
                    <Button
                        variant="outline"
                        onClick={() => setDecision('request_info')}
                        disabled={submitting || !ready}
                    >
                        {t.request_info}
                    </Button>
                    <Button
                        variant="danger"
                        onClick={() => setDecision('reject')}
                        disabled={submitting || !ready}
                    >
                        {t.reject}
                    </Button>
                    <Button
                        onClick={() => {
                            if (!checklistComplete) {
                                setChecklistError(t.checklist_incomplete);
                                return;
                            }
                            setDecision('accept');
                        }}
                        disabled={submitting || !ready}
                    >
                        {t.accept}
                    </Button>
                </div>
            )}
        </div>
    );

    return (
        <>
            <Shell
                open={open}
                onClose={onClose}
                title={t.title}
                subtitle={
                    lead
                        ? `${lead.lead_reference} · ${t.subtitle}`
                        : t.subtitle
                }
                footer={footer}
                isMobile={isMobile}
            >
                {loadingAudit ? (
                    <div className="space-y-4 py-4" aria-busy="true">
                        <div className="h-4 w-40 animate-pulse rounded bg-rml-border" />
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="h-16 animate-pulse rounded-lg bg-rml-border/70" />
                            <div className="h-16 animate-pulse rounded-lg bg-rml-border/70" />
                            <div className="h-16 animate-pulse rounded-lg bg-rml-border/70" />
                            <div className="h-16 animate-pulse rounded-lg bg-rml-border/70" />
                        </div>
                        <p className="text-center text-sm text-rml-muted">
                            {t.loading ?? common.loading ?? '…'}
                        </p>
                    </div>
                ) : loadError || !lead || !loadedAudit ? (
                    <div className="space-y-3 py-8 text-center">
                        <p className="text-sm text-rml-red">
                            {loadError ?? t.load_error}
                        </p>
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => {
                                setLoadError(null);
                                setLoadedAudit(null);
                                setLoadingAudit(true);
                                fetch(route('admin.leads.audit.data', leadId), {
                                    headers: {
                                        Accept: 'application/json',
                                        'X-Requested-With': 'XMLHttpRequest',
                                    },
                                    credentials: 'same-origin',
                                })
                                    .then(async (response) => {
                                        if (!response.ok) {
                                            throw new Error('fail');
                                        }
                                        return response.json();
                                    })
                                    .then((payload: unknown) => {
                                        if (!isAuditPayload(payload)) {
                                            throw new Error('invalid');
                                        }
                                        setLoadedAudit(payload);
                                        setLoadError(null);
                                    })
                                    .catch(() => {
                                        setLoadError(t.load_error);
                                    })
                                    .finally(() => setLoadingAudit(false));
                            }}
                        >
                            {common.retry ?? common.apply}
                        </Button>
                    </div>
                ) : (
                    <div className="max-h-[65vh] space-y-6 overflow-y-auto pr-1">
                        <section className="space-y-3">
                            <h3 className="text-sm font-semibold text-rml-text">
                                {t.summary}
                            </h3>
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="font-mono text-sm font-medium">
                                    {lead.lead_reference}
                                </span>
                                {lead.status && (
                                    <StatusBadge
                                        label={leadStatusLabel(
                                            lead.status,
                                            leadStatuses,
                                        )}
                                        tone={leadStatusTone(lead.status)}
                                    />
                                )}
                            </div>
                            <dl className="grid gap-2 text-sm sm:grid-cols-2">
                                <div>
                                    <dt className="text-rml-muted">
                                        {common.scheme}
                                    </dt>
                                    <dd>{lead.scheme?.name ?? '—'}</dd>
                                </div>
                                <div>
                                    <dt className="text-rml-muted">
                                        {common.zone}
                                    </dt>
                                    <dd>{lead.zone?.code ?? '—'}</dd>
                                </div>
                                <div>
                                    <dt className="text-rml-muted">
                                        {common.date}
                                    </dt>
                                    <dd>
                                        {formatDate(
                                            lead.created_at ??
                                                lead.submitted_at ??
                                                null,
                                            app.locale,
                                        )}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-rml-muted">
                                        {t.buying_price}
                                    </dt>
                                    <dd>
                                        {formatMoney(
                                            lead.buying_price ??
                                                pricing?.buying_price,
                                        )}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-rml-muted">
                                        {t.selling_price}
                                    </dt>
                                    <dd>
                                        {formatMoney(
                                            lead.selling_price ??
                                                pricing?.selling_price,
                                        )}
                                    </dd>
                                </div>
                            </dl>
                        </section>

                        <section className="space-y-3">
                            <h3 className="text-sm font-semibold text-rml-text">
                                {t.customer}
                            </h3>
                            <dl className="grid gap-2 text-sm sm:grid-cols-2">
                                <div>
                                    <dt className="text-rml-muted">
                                        {common.name}
                                    </dt>
                                    <dd>
                                        {`${lead.customer_first_name} ${lead.customer_last_name}`.trim()}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-rml-muted">
                                        {common.phone}
                                    </dt>
                                    <dd>{lead.customer_phone}</dd>
                                </div>
                                <div>
                                    <dt className="text-rml-muted">
                                        {common.email}
                                    </dt>
                                    <dd>{lead.customer_email ?? '—'}</dd>
                                </div>
                                <div className="sm:col-span-2">
                                    <dt className="text-rml-muted">
                                        {common.details}
                                    </dt>
                                    <dd>
                                        {[
                                            lead.address_line_1,
                                            lead.city,
                                            lead.postcode,
                                        ]
                                            .filter(Boolean)
                                            .join(', ') || '—'}
                                    </dd>
                                </div>
                            </dl>
                        </section>

                        <section className="space-y-3">
                            <h3 className="text-sm font-semibold text-rml-text">
                                {t.seller_details ?? common.company}
                            </h3>
                            <dl className="grid gap-2 text-sm sm:grid-cols-2">
                                <div>
                                    <dt className="text-rml-muted">
                                        {common.name}
                                    </dt>
                                    <dd>{lead.seller?.name ?? '—'}</dd>
                                </div>
                                <div>
                                    <dt className="text-rml-muted">
                                        {common.company}
                                    </dt>
                                    <dd>
                                        {lead.seller_company?.name ?? '—'}
                                    </dd>
                                </div>
                                <div className="sm:col-span-2">
                                    <dt className="text-rml-muted">
                                        {common.email}
                                    </dt>
                                    <dd>{lead.seller?.email ?? '—'}</dd>
                                </div>
                            </dl>
                        </section>

                        <section className="space-y-3">
                            <h3 className="text-sm font-semibold text-rml-text">
                                {t.evidence}
                            </h3>
                            {lead.evidence.length === 0 ? (
                                <p className="text-sm text-rml-muted">—</p>
                            ) : (
                                <ul className="divide-y divide-rml-border rounded-lg border border-rml-border">
                                    {lead.evidence.map((file) => (
                                        <li
                                            key={file.id}
                                            className="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-sm"
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate font-medium text-rml-text">
                                                    {file.original_name ??
                                                        file.file_type}
                                                </p>
                                                <p className="text-xs text-rml-muted">
                                                    {file.mime_type ?? '—'}
                                                </p>
                                            </div>
                                            <div className="flex shrink-0 gap-2">
                                                {file.view_url && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        type="button"
                                                        onClick={() => {
                                                            if (file.is_image) {
                                                                setPreviewUrl(
                                                                    file.view_url!,
                                                                );
                                                            } else {
                                                                window.open(
                                                                    file.view_url!,
                                                                    '_blank',
                                                                    'noopener,noreferrer',
                                                                );
                                                            }
                                                        }}
                                                    >
                                                        {t.view_file ??
                                                            common.view}
                                                    </Button>
                                                )}
                                                {file.download_url && (
                                                    <a
                                                        href={file.download_url}
                                                        className="inline-flex"
                                                    >
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            type="button"
                                                        >
                                                            {t.download_file ??
                                                                common.view}
                                                        </Button>
                                                    </a>
                                                )}
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>

                        <AuditChecklist
                            items={checklistItems}
                            onChange={handleChecklistChange}
                            title={t.checklist_title}
                            subtitle={t.checklist_subtitle}
                        />
                        <p className="text-xs text-rml-muted">
                            {t.checklist_progress
                                ?.replace(':done', String(checkedCount))
                                .replace(
                                    ':total',
                                    String(loadedAudit.checklist.length),
                                ) ??
                                `${checkedCount}/${loadedAudit.checklist.length}`}
                        </p>
                        {(checklistError || errors.checklist) && (
                            <p className="text-sm text-rml-red">
                                {checklistError ?? errors.checklist}
                            </p>
                        )}
                        <p className="text-xs text-rml-muted">
                            {checklistComplete
                                ? t.checklist_complete
                                : t.checklist_incomplete}
                        </p>

                        {pricing && (
                            <section className="space-y-3 rounded-xl border border-rml-border bg-rml-background/50 p-4">
                                <h3 className="text-sm font-semibold text-rml-text">
                                    {t.pricing}
                                </h3>
                                <dl className="grid gap-2 text-sm sm:grid-cols-2">
                                    <div>
                                        <dt className="text-rml-muted">
                                            {t.suggested_price}
                                        </dt>
                                        <dd>
                                            {formatMoney(
                                                pricing.suggested_selling_price,
                                            )}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-rml-muted">
                                            {t.expected_margin}
                                        </dt>
                                        <dd>
                                            {formatMoney(
                                                pricing.expected_margin,
                                            )}
                                        </dd>
                                    </div>
                                </dl>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <FormInput
                                        label={t.buying_price}
                                        name="buying_price"
                                        type="number"
                                        step="0.01"
                                        value={data.buying_price}
                                        error={errors.buying_price}
                                        onChange={(e) =>
                                            setData(
                                                'buying_price',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <FormInput
                                        label={t.selling_price}
                                        name="selling_price"
                                        type="number"
                                        step="0.01"
                                        value={data.selling_price}
                                        error={errors.selling_price}
                                        onChange={(e) =>
                                            setData(
                                                'selling_price',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <FormInput
                                    label={t.override_reason}
                                    name="override_reason"
                                    value={data.override_reason}
                                    error={errors.override_reason}
                                    onChange={(e) =>
                                        setData(
                                            'override_reason',
                                            e.target.value,
                                        )
                                    }
                                />
                            </section>
                        )}

                        <Textarea
                            label={t.audit_notes}
                            name="audit_notes"
                            value={data.audit_notes}
                            error={errors.audit_notes}
                            onChange={(e) =>
                                setData('audit_notes', e.target.value)
                            }
                        />
                    </div>
                )}
            </Shell>

            <Modal
                open={previewUrl != null}
                onClose={() => setPreviewUrl(null)}
                title={t.view_file ?? common.view}
                size="lg"
            >
                {previewUrl && (
                    <img
                        src={previewUrl}
                        alt=""
                        className="max-h-[70vh] w-full object-contain"
                    />
                )}
            </Modal>
        </>
    );
}
