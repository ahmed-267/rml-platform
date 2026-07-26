import { FormEvent, useEffect, useMemo, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import {
    Button,
    Checkbox,
    EmptyState,
    FormInput,
    Modal,
    Select,
    StatusBadge,
} from '@/Components/ui';
import { leadStatusLabel, leadStatusTone } from '@/lib/lead-status';
import type { PageProps } from '@/types';

export type AssignableLeadRow = {
    id: number;
    lead_reference: string;
    status: string | null;
    scheme: string | null;
    zone: string | null;
    seller_display: string | null;
    already_assigned?: boolean;
    current_auditor?: {
        id: number;
        name: string;
        email: string;
    } | null;
};

type StatusOption = {
    value: string;
    label: string;
};

type FilterOptions = {
    statuses: StatusOption[];
    schemes: Array<{ id: number; name: string }>;
    zones: Array<{ code: string }>;
};

type AuditorSummary = {
    id: number;
    name: string;
    email: string;
    assigned_audits_count: number;
    in_review_audits_count: number;
};

type Props = {
    open: boolean;
    onClose: () => void;
    auditor: AuditorSummary | null;
};

function useDebouncedValue<T>(value: T, delayMs = 300): T {
    const [debounced, setDebounced] = useState(value);

    useEffect(() => {
        const timer = window.setTimeout(() => setDebounced(value), delayMs);
        return () => window.clearTimeout(timer);
    }, [value, delayMs]);

    return debounced;
}

export function AssignAuditorAuditsModal({ open, onClose, auditor }: Props) {
    const { translations } = usePage<PageProps>().props;
    const t = translations.admin.users;
    const common = translations.admin.common;
    const leadStatuses = translations.lead_statuses;

    const [loading, setLoading] = useState(false);
    const [loadError, setLoadError] = useState<string | null>(null);
    const [leads, setLeads] = useState<AssignableLeadRow[]>([]);
    const [filterOptions, setFilterOptions] = useState<FilterOptions>({
        statuses: [],
        schemes: [],
        zones: [],
    });
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [submitting, setSubmitting] = useState(false);

    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('');
    const [schemeId, setSchemeId] = useState('');
    const [zoneCode, setZoneCode] = useState('');
    const [seller, setSeller] = useState('');

    const debouncedSearch = useDebouncedValue(search);
    const debouncedSeller = useDebouncedValue(seller);

    const title = auditor
        ? (t.assign_audit_title ?? 'Assign audit to :name').replace(
              ':name',
              auditor.name,
          )
        : (t.assign_audit ?? 'Assign audit');

    const queryString = useMemo(() => {
        const params = new URLSearchParams();
        if (debouncedSearch.trim()) {
            params.set('search', debouncedSearch.trim());
        }
        if (status) params.set('status', status);
        if (schemeId) params.set('scheme_id', schemeId);
        if (zoneCode) params.set('zone_code', zoneCode);
        if (debouncedSeller.trim()) {
            params.set('seller', debouncedSeller.trim());
        }
        return params.toString();
    }, [debouncedSearch, status, schemeId, zoneCode, debouncedSeller]);

    useEffect(() => {
        if (!open || !auditor) {
            return;
        }

        let cancelled = false;
        setLoading(true);
        setLoadError(null);

        const url = `${route('admin.users.eligible-audits', auditor.id)}${
            queryString ? `?${queryString}` : ''
        }`;

        fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                return response.json();
            })
            .then(
                (payload: {
                    leads?: AssignableLeadRow[];
                    filterOptions?: FilterOptions;
                }) => {
                    if (cancelled) return;
                    setLeads(payload.leads ?? []);
                    if (payload.filterOptions) {
                        setFilterOptions(payload.filterOptions);
                    }
                },
            )
            .catch(() => {
                if (!cancelled) {
                    setLeads([]);
                    setLoadError(t.assign_leads_load_error ?? common.retry);
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [open, auditor, queryString]);

    useEffect(() => {
        if (!open) {
            setSearch('');
            setStatus('');
            setSchemeId('');
            setZoneCode('');
            setSeller('');
            setSelectedIds([]);
            setLeads([]);
            setLoadError(null);
        }
    }, [open]);

    useEffect(() => {
        setSelectedIds((current) =>
            current.filter((id) => leads.some((lead) => lead.id === id)),
        );
    }, [leads]);

    const toggleLead = (id: number) => {
        setSelectedIds((current) =>
            current.includes(id)
                ? current.filter((value) => value !== id)
                : [...current, id],
        );
    };

    const toggleAll = () => {
        if (selectedIds.length === leads.length && leads.length > 0) {
            setSelectedIds([]);
            return;
        }
        setSelectedIds(leads.map((lead) => lead.id));
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (!auditor || selectedIds.length === 0) {
            return;
        }

        setSubmitting(true);
        router.post(
            route('admin.users.assign-audits', auditor.id),
            { lead_ids: selectedIds },
            {
                preserveScroll: true,
                onSuccess: () => {
                    onClose();
                },
                onFinish: () => setSubmitting(false),
            },
        );
    };

    const emptyTitle =
        t.assign_leads_empty ?? 'No eligible leads available';

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={title}
            size="xl"
            footer={
                <>
                    <Button variant="ghost" size="sm" onClick={onClose}>
                        {common.cancel}
                    </Button>
                    <Button
                        size="sm"
                        onClick={submit}
                        disabled={submitting || selectedIds.length === 0}
                    >
                        {t.assign_selected ?? t.assign_audit ?? 'Assign'}
                        {selectedIds.length > 0
                            ? ` (${selectedIds.length})`
                            : ''}
                    </Button>
                </>
            }
        >
            {auditor ? (
                <div className="space-y-4">
                    <section className="grid gap-3 rounded-xl border border-rml-border bg-rml-background/50 p-3 sm:grid-cols-2">
                        <div>
                            <p className="text-xs font-medium uppercase tracking-wide text-rml-muted">
                                {common.name}
                            </p>
                            <p className="mt-1 text-sm font-medium text-rml-text">
                                {auditor.name}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs font-medium uppercase tracking-wide text-rml-muted">
                                {common.email}
                            </p>
                            <p className="mt-1 text-sm font-medium text-rml-text">
                                {auditor.email}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs font-medium uppercase tracking-wide text-rml-muted">
                                {t.col_assigned_audits ?? 'Assigned audits'}
                            </p>
                            <p className="mt-1 text-sm font-medium text-rml-text">
                                {auditor.assigned_audits_count}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs font-medium uppercase tracking-wide text-rml-muted">
                                {t.col_in_review_audits ?? 'In-review audits'}
                            </p>
                            <p className="mt-1 text-sm font-medium text-rml-text">
                                {auditor.in_review_audits_count}
                            </p>
                        </div>
                    </section>

                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <FormInput
                            label={common.lead_id}
                            value={search}
                            placeholder={
                                t.assign_search_placeholder ?? common.search
                            }
                            onChange={(e) => setSearch(e.target.value)}
                        />
                        <Select
                            label={common.scheme}
                            value={schemeId}
                            onChange={(e) => setSchemeId(e.target.value)}
                            options={[
                                { label: common.all, value: '' },
                                ...filterOptions.schemes.map((scheme) => ({
                                    label: scheme.name,
                                    value: String(scheme.id),
                                })),
                            ]}
                        />
                        <Select
                            label={common.zone}
                            value={zoneCode}
                            onChange={(e) => setZoneCode(e.target.value)}
                            options={[
                                { label: common.all, value: '' },
                                ...filterOptions.zones.map((zone) => ({
                                    label: zone.code,
                                    value: zone.code,
                                })),
                            ]}
                        />
                        <FormInput
                            label={t.assign_seller_filter ?? 'Seller company'}
                            value={seller}
                            onChange={(e) => setSeller(e.target.value)}
                        />
                        <Select
                            label={common.status}
                            value={status}
                            onChange={(e) => setStatus(e.target.value)}
                            options={[
                                { label: common.all_statuses, value: '' },
                                ...filterOptions.statuses.map((option) => ({
                                    label: option.label,
                                    value: option.value,
                                })),
                            ]}
                        />
                    </div>

                    {loading ? (
                        <p className="py-8 text-center text-sm text-rml-muted">
                            {common.loading}
                        </p>
                    ) : loadError ? (
                        <p className="py-8 text-center text-sm text-rml-red">
                            {loadError}
                        </p>
                    ) : leads.length === 0 ? (
                        <EmptyState title={emptyTitle} />
                    ) : (
                        <div className="space-y-2">
                            <div className="flex items-center justify-between gap-2">
                                <Checkbox
                                    label={
                                        t.select_all_leads ?? 'Select all'
                                    }
                                    checked={
                                        selectedIds.length > 0 &&
                                        selectedIds.length === leads.length
                                    }
                                    onChange={toggleAll}
                                />
                                <p className="text-xs text-rml-muted">
                                    {leads.length}{' '}
                                    {t.eligible_leads ?? 'eligible'}
                                </p>
                            </div>
                            <ul className="max-h-[40vh] space-y-2 overflow-y-auto pr-1">
                                {leads.map((lead) => {
                                    const alreadyAssigned = Boolean(
                                        lead.already_assigned,
                                    );

                                    return (
                                        <li
                                            key={lead.id}
                                            className="rounded-xl border border-rml-border bg-white p-3"
                                        >
                                            <Checkbox
                                                checked={selectedIds.includes(
                                                    lead.id,
                                                )}
                                                onChange={() =>
                                                    toggleLead(lead.id)
                                                }
                                                label={lead.lead_reference}
                                                description={[
                                                    lead.scheme,
                                                    lead.zone,
                                                    lead.seller_display,
                                                    alreadyAssigned
                                                        ? (t.already_assigned ??
                                                          'Already assigned')
                                                        : lead.current_auditor
                                                          ? `${t.currently_assigned ?? 'Assigned'}: ${lead.current_auditor.name}`
                                                          : null,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ')}
                                            />
                                            {lead.status ? (
                                                <div className="mt-2 pl-7">
                                                    <StatusBadge
                                                        label={leadStatusLabel(
                                                            lead.status,
                                                            leadStatuses,
                                                        )}
                                                        tone={leadStatusTone(
                                                            lead.status,
                                                        )}
                                                    />
                                                </div>
                                            ) : null}
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                    )}
                </div>
            ) : null}
        </Modal>
    );
}
