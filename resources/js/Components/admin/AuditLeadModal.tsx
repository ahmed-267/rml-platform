import { FormEvent, ReactNode, useEffect, useMemo, useRef, useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import {
    AuditChecklist,
    Button,
    Drawer,
    FormInput,
    Modal,
    TableActionButton,
    TableActionLink,
    TableActions,
    Textarea,
    tableActionIcons,
    Select,
} from '@/Components/ui';
import { cn } from '@/lib/cn';
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
    suggested_buying_price?: number | null;
    seller_payout_per_m2?: number | null;
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

export interface AuditAuditorOption {
    id: number;
    name: string;
    email: string;
    active_assigned_count: number;
}

export interface AuditPayload {
    checklist: AuditChecklistItemPayload[];
    pricing: AuditPricingPayload;
    lead: AuditLeadPayload;
    assigned_auditor_id?: number | null;
    can_assign_auditor?: boolean;
    auditors?: AuditAuditorOption[];
}

export interface AuditLeadModalProps {
    open: boolean;
    onClose: () => void;
    leadId: number;
    /** Optional preloaded payload; modal will refetch when missing. */
    audit?: AuditPayload | null;
}

type DecisionMode = 'accept' | 'reject' | 'request_info' | null;

function AuditPanel({
    title,
    description,
    children,
    className,
}: {
    title: string;
    description?: string;
    children: ReactNode;
    className?: string;
}) {
    return (
        <section
            className={cn(
                'rounded-2xl border border-rml-border bg-rml-background/60 p-4 sm:p-5',
                className,
            )}
        >
            <header className="mb-4 space-y-1">
                <h3 className="text-sm font-semibold tracking-tight text-rml-text">
                    {title}
                </h3>
                {description ? (
                    <p className="text-xs text-rml-muted">{description}</p>
                ) : null}
            </header>
            <div className="space-y-3">{children}</div>
        </section>
    );
}

function AuditField({
    label,
    children,
    className,
}: {
    label: string;
    children: ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('min-w-0', className)}>
            <dt className="text-xs font-medium uppercase tracking-wide text-rml-muted">
                {label}
            </dt>
            <dd className="mt-1 text-sm font-medium text-rml-text">
                {children}
            </dd>
        </div>
    );
}

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
    const checklistSectionRef = useRef<HTMLDivElement | null>(null);
    const pricingSectionRef = useRef<HTMLDivElement | null>(null);
    const scrollContainerRef = useRef<HTMLDivElement | null>(null);

    const scrollToSection = (ref: { current: HTMLDivElement | null }) => {
        const node = ref.current;
        if (!node) return;
        node.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };


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
    const [selectedAuditorId, setSelectedAuditorId] = useState<string>('');
    const [assigning, setAssigning] = useState(false);

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
        if (!loadedAudit) {
            setSelectedAuditorId('');
            return;
        }
        setSelectedAuditorId(
            loadedAudit.assigned_auditor_id
                ? String(loadedAudit.assigned_auditor_id)
                : '',
        );
    }, [loadedAudit]);

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

    const requiredChecklistComplete = useMemo(() => {
        if (!loadedAudit || loadedAudit.checklist.length === 0) {
            return true;
        }
        const required = loadedAudit.checklist.filter((item) => item.required);
        const items = required.length > 0 ? required : loadedAudit.checklist;
        return items.every((item) => checklistState[String(item.id)]);
    }, [loadedAudit, checklistState]);

    const pricingComplete = useMemo(() => {
        const buy = String(data.buying_price ?? '').trim();
        const sell = String(data.selling_price ?? '').trim();
        return buy !== '' && sell !== '' && Number(buy) >= 0 && Number(sell) > 0;
    }, [data.buying_price, data.selling_price]);

    const canAcceptLead = requiredChecklistComplete && pricingComplete;

    const leadStatus = loadedAudit?.lead?.status ?? null;
    const canRejectLead =
        leadStatus !== 'sold' &&
        leadStatus !== 'cancelled' &&
        leadStatus !== 'listed';
    const canRequestInfoLead =
        leadStatus !== 'sold' && leadStatus !== 'cancelled';
    // Rejected leads can still be rejected again (update reason) or accepted.
    const showRejectAction = canRejectLead || leadStatus === 'rejected';
    const showAcceptAction =
        leadStatus !== 'sold' && leadStatus !== 'cancelled';

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

    const handleCheckAll = (checked: boolean) => {
        if (!loadedAudit) return;
        const next = Object.fromEntries(
            loadedAudit.checklist.map((item) => [String(item.id), checked]),
        );
        setChecklistState(next);
        setData('checklist', buildChecklistPayload(next));
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

        if (!requiredChecklistComplete) {
            setChecklistError(t.checklist_incomplete);
            setDecision(null);
            scrollToSection(checklistSectionRef);
            return;
        }
        if (!pricingComplete) {
            setChecklistError(t.sections_incomplete ?? t.checklist_incomplete);
            setDecision(null);
            scrollToSection(pricingSectionRef);
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
                        scrollToSection(checklistSectionRef);
                    }
                    if (formErrors.buying_price || formErrors.selling_price || formErrors.override_reason) {
                        setDecision('accept');
                        scrollToSection(pricingSectionRef);
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

    const submitAssignAuditor = (event: FormEvent) => {
        event.preventDefault();
        if (!selectedAuditorId) {
            return;
        }
        setAssigning(true);
        router.post(
            route('admin.leads.audit.assign', leadId),
            { auditor_user_id: Number(selectedAuditorId) },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setLoadedAudit((prev) =>
                        prev
                            ? {
                                  ...prev,
                                  assigned_auditor_id: Number(selectedAuditorId),
                              }
                            : prev,
                    );
                },
                onFinish: () => setAssigning(false),
            },
        );
    };

    const lead = loadedAudit?.lead;
    const pricing = loadedAudit?.pricing;
    const ready = !loadingAudit && !!loadedAudit && !!lead;

    const footer = (
        <div className="flex w-full flex-col gap-3">
            {decision === 'accept' && ready && (
                <form onSubmit={submitAccept} className="space-y-3 rounded-xl border border-rml-primary/30 bg-rml-primary-light/40 p-3">
                    <div className="space-y-1">
                        <p className="text-sm font-semibold text-rml-text">
                            {t.accept_confirm}
                        </p>
                        <p className="text-sm text-rml-muted">
                            {t.accept_confirm_body ?? t.accept_confirm}
                        </p>
                    </div>
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
                            disabled={submitting || !canAcceptLead}
                        >
                            {t.accept_confirm_action ?? t.accept}
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
                    {canRequestInfoLead && (
                        <Button
                            variant="outline"
                            onClick={() => setDecision('request_info')}
                            disabled={submitting || !ready}
                        >
                            {t.request_info}
                        </Button>
                    )}
                    {showRejectAction && (
                        <Button
                            variant="danger"
                            onClick={() => setDecision('reject')}
                            disabled={submitting || !ready}
                        >
                            {t.reject}
                        </Button>
                    )}
                    {showAcceptAction && (
                        <Button
                            onClick={() => {
                                if (!requiredChecklistComplete) {
                                    setChecklistError(t.checklist_incomplete);
                                    scrollToSection(checklistSectionRef);
                                    return;
                                }
                                if (!pricingComplete) {
                                    setChecklistError(
                                        t.sections_incomplete ??
                                            t.checklist_incomplete,
                                    );
                                    scrollToSection(pricingSectionRef);
                                    return;
                                }
                                setDecision('accept');
                            }}
                            disabled={submitting || !ready || !canAcceptLead}
                        >
                            {t.accept}
                        </Button>
                    )}
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
                    <div
                        ref={scrollContainerRef}
                        className="max-h-[65vh] space-y-4 overflow-y-auto py-1 pr-1"
                    >
                        <AuditPanel title={t.summary}>
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="font-mono text-sm font-semibold">
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
                            <dl className="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                                <AuditField label={common.scheme}>
                                    {lead.scheme?.name ?? '—'}
                                </AuditField>
                                <AuditField label={common.zone}>
                                    {lead.zone?.code ?? '—'}
                                </AuditField>
                                <AuditField label={common.date}>
                                    {formatDate(
                                        lead.created_at ??
                                            lead.submitted_at ??
                                            null,
                                        app.locale,
                                    )}
                                </AuditField>
                                <AuditField
                                    label={t.seller_payout ?? t.buying_price}
                                >
                                    {formatMoney(
                                        lead.buying_price ??
                                            pricing?.buying_price,
                                    )}
                                </AuditField>
                                <AuditField label={t.selling_price}>
                                    {formatMoney(
                                        lead.selling_price ??
                                            pricing?.selling_price,
                                    )}
                                </AuditField>
                            </dl>
                        </AuditPanel>

                        {loadedAudit.can_assign_auditor ? (
                            <AuditPanel
                                title={t.assign_auditor ?? 'Assign auditor'}
                                description={
                                    t.assign_auditor_hint ??
                                    'Pick Internal Auditor for this lead.'
                                }
                            >
                                <form
                                    onSubmit={submitAssignAuditor}
                                    className="flex flex-col gap-3 sm:flex-row sm:items-end"
                                >
                                    <div className="min-w-0 flex-1">
                                        <Select
                                            label={t.auditor ?? 'Auditor'}
                                            value={selectedAuditorId}
                                            onChange={(e) =>
                                                setSelectedAuditorId(
                                                    e.target.value,
                                                )
                                            }
                                            options={[
                                                {
                                                    label:
                                                        t.select_auditor ??
                                                        'Select auditor',
                                                    value: '',
                                                },
                                                ...(
                                                    loadedAudit.auditors ?? []
                                                ).map((auditor) => ({
                                                    label: `${auditor.name} · ${auditor.email} · ${auditor.active_assigned_count} ${t.active_assigned_short ?? 'active'}`,
                                                    value: String(auditor.id),
                                                })),
                                            ]}
                                        />
                                    </div>
                                    <Button
                                        type="submit"
                                        size="sm"
                                        disabled={
                                            assigning ||
                                            !selectedAuditorId ||
                                            Number(selectedAuditorId) ===
                                                loadedAudit.assigned_auditor_id
                                        }
                                    >
                                        {t.assign ?? common.save}
                                    </Button>
                                </form>
                            </AuditPanel>
                        ) : null}

                        <AuditPanel title={t.customer}>
                            <dl className="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                                <AuditField label={common.name}>
                                    {`${lead.customer_first_name} ${lead.customer_last_name}`.trim()}
                                </AuditField>
                                <AuditField label={common.phone}>
                                    {lead.customer_phone}
                                </AuditField>
                                <AuditField
                                    label={common.email}
                                    className="sm:col-span-2"
                                >
                                    {lead.customer_email ?? '—'}
                                </AuditField>
                                <AuditField
                                    label={common.details}
                                    className="sm:col-span-2"
                                >
                                    {[
                                        lead.address_line_1,
                                        lead.city,
                                        lead.postcode,
                                    ]
                                        .filter(Boolean)
                                        .join(', ') || '—'}
                                </AuditField>
                            </dl>
                        </AuditPanel>

                        <AuditPanel
                            title={t.seller_details ?? common.company}
                        >
                            <dl className="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                                <AuditField label={common.name}>
                                    {lead.seller?.name ?? '—'}
                                </AuditField>
                                <AuditField label={common.company}>
                                    {lead.seller_company?.name ?? '—'}
                                </AuditField>
                                <AuditField
                                    label={common.email}
                                    className="sm:col-span-2"
                                >
                                    {lead.seller?.email ?? '—'}
                                </AuditField>
                            </dl>
                        </AuditPanel>

                        <AuditPanel title={t.evidence}>
                            {lead.evidence.length === 0 ? (
                                <p className="text-sm text-rml-muted">—</p>
                            ) : (
                                <ul className="space-y-2">
                                    {lead.evidence.map((file) => (
                                        <li
                                            key={file.id}
                                            className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-rml-border bg-white px-3.5 py-3"
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium text-rml-text">
                                                    {file.original_name ??
                                                        file.file_type}
                                                </p>
                                                <p className="mt-0.5 text-xs text-rml-muted">
                                                    {file.mime_type ?? '—'}
                                                </p>
                                            </div>
                                            <TableActions>
                                                {file.view_url && (
                                                    <TableActionButton
                                                        label={
                                                            t.view_file ??
                                                            common.view
                                                        }
                                                        icon={
                                                            tableActionIcons.view
                                                        }
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
                                                    />
                                                )}
                                                {file.download_url && (
                                                    <TableActionLink
                                                        href={file.download_url}
                                                        label={
                                                            t.download_file ??
                                                            'Download'
                                                        }
                                                        icon={
                                                            tableActionIcons.download
                                                        }
                                                        external
                                                    />
                                                )}
                                            </TableActions>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </AuditPanel>

                        <div ref={checklistSectionRef}>
                        <AuditPanel
                            title={t.checklist_title}
                            description={t.checklist_subtitle}
                        >
                            <AuditChecklist
                                items={checklistItems}
                                onChange={handleChecklistChange}
                                onCheckAll={() => handleCheckAll(true)}
                                onUncheckAll={() => handleCheckAll(false)}
                                checkAllLabel={t.check_all}
                                uncheckAllLabel={t.uncheck_all}
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
                                {canAcceptLead
                                    ? t.checklist_complete
                                    : t.checklist_incomplete}
                            </p>
                        </AuditPanel>
                        </div>

                        {pricing && (
                            <div ref={pricingSectionRef}>
                            <AuditPanel title={t.pricing}>
                                <dl className="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                                    <AuditField label={t.suggested_price}>
                                        {formatMoney(
                                            pricing.suggested_selling_price,
                                        )}
                                    </AuditField>
                                    <AuditField
                                        label={
                                            t.suggested_buying ??
                                            t.seller_payout ??
                                            t.buying_price
                                        }
                                    >
                                        {formatMoney(
                                            pricing.suggested_buying_price ??
                                                pricing.buying_price,
                                        )}
                                    </AuditField>
                                    <AuditField label={t.expected_margin}>
                                        {formatMoney(pricing.expected_margin)}
                                    </AuditField>
                                </dl>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <FormInput
                                        label={t.seller_payout ?? t.buying_price}
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
                            </AuditPanel>
                            </div>
                        )}

                        <AuditPanel title={t.audit_notes}>
                            <Textarea
                                name="audit_notes"
                                value={data.audit_notes}
                                error={errors.audit_notes}
                                onChange={(e) =>
                                    setData('audit_notes', e.target.value)
                                }
                            />
                        </AuditPanel>
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
