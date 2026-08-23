import { FormEvent, useMemo, useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import {
    Alert,
    Button,
    FormInput,
    Modal,
    StatusBadge,
} from '@/Components/ui';
import type { PageProps } from '@/types';

export type CatastroSnapshotPayload = {
    id?: number;
    provider?: string | null;
    verification_status?: string | null;
    cadastral_reference?: string | null;
    cadastral_address?: string | null;
    province?: string | null;
    municipality?: string | null;
    postcode?: string | null;
    property_use?: string | null;
    constructed_area_m2?: number | null;
    parcel_area_m2?: number | null;
    construction_year?: number | null;
    floor?: string | null;
    door?: string | null;
    unit_label?: string | null;
    match_summary?: string | null;
    error_message?: string | null;
    warnings?: Array<{ code: string; message: string }>;
    warning_count?: number;
    lookup_at?: string | null;
    is_current?: boolean;
    is_selected?: boolean;
    auditor_notes?: string | null;
    read_only?: boolean;
    is_official_ownership_proof?: boolean;
};

export type CatastroPanelPayload = {
    current: CatastroSnapshotPayload | null;
    candidates: Array<Record<string, unknown>>;
    history?: CatastroSnapshotPayload[];
    enabled?: boolean;
    has_cadastral_reference?: boolean;
    seller_status?: { key: string; label_key: string } | null;
    lead_submitted?: {
        address_line_1?: string | null;
        city?: string | null;
        postcode?: string | null;
        cadastral_reference?: string | null;
        submitted_property_area_m2?: number | null;
    };
    disclaimer?: string;
};

type Props = {
    leadId: number;
    catastro: CatastroPanelPayload;
    routePrefix: 'admin' | 'seller' | 'auditor';
    canLookup?: boolean;
    canReview?: boolean;
};

const RETRY_STATUSES = new Set([
    'mismatch_detected',
    'partially_matched',
    'lookup_failed',
    'service_unavailable',
    'property_not_found',
]);

const ERROR_STATUSES = new Set([
    'lookup_failed',
    'service_unavailable',
    'property_not_found',
]);

function statusTone(status: string | null | undefined): 'success' | 'warning' | 'danger' | 'neutral' | 'info' {
    switch (status) {
        case 'matched':
            return 'success';
        case 'partially_matched':
        case 'multiple_properties_found':
        case 'manual_review_required':
        case 'lookup_in_progress':
            return 'warning';
        case 'mismatch_detected':
        case 'lookup_failed':
        case 'property_not_found':
            return 'danger';
        case 'service_unavailable':
        case 'regional_provider_required':
            return 'info';
        default:
            return 'neutral';
    }
}

export function CatastroVerificationCard({
    leadId,
    catastro,
    routePrefix,
    canLookup = false,
    canReview = false,
}: Props) {
    const { translations } = usePage<PageProps>().props;
    const t = (translations as PageProps['translations'] & {
        catastro?: Record<string, any>;
    }).catastro ?? {};
    const statuses = t.statuses ?? {};
    const statusHints = t.status_hints ?? {};
    const providers = t.providers ?? {};
    const warningMessages = t.warning_messages ?? {};
    const current = catastro.current;
    const status = current?.verification_status ?? 'not_checked';
    const enabled = catastro.enabled !== false;
    const storedReference =
        catastro.lead_submitted?.cadastral_reference ??
        current?.cadastral_reference ??
        null;
    const hasReference = Boolean(
        catastro.has_cadastral_reference ?? storedReference,
    );
    const statusHint =
        !enabled
            ? (statusHints.service_unavailable ??
              t.lookup_unavailable ??
              'Catastro lookup is currently unavailable.')
            : !hasReference
              ? (t.no_reference ?? 'No cadastral reference provided.')
              : (statusHints[status] ??
                (status === 'not_checked' ? 'Lookup not run yet.' : null));

    // Sellers never get technical Catastro actions on this card.
    const supportsTechnicalActions = routePrefix === 'admin' || routePrefix === 'auditor';
    const canEditReference = canLookup && supportsTechnicalActions;
    const canRunLookup = canLookup && supportsTechnicalActions && enabled && hasReference;
    const isRetry = RETRY_STATUSES.has(status) || Boolean(current?.lookup_at);
    const errorMessage =
        current?.error_message ??
        (ERROR_STATUSES.has(status) ? (current?.match_summary ?? null) : null);
    const summaryMessage =
        errorMessage ??
        (ERROR_STATUSES.has(status) ? null : (current?.match_summary ?? null));

    const [editOpen, setEditOpen] = useState(false);
    const [detailOpen, setDetailOpen] = useState(false);
    const [checking, setChecking] = useState(false);

    const editForm = useForm({
        cadastral_reference: storedReference ?? '',
    });

    const candidates = useMemo(
        () => catastro.candidates ?? [],
        [catastro.candidates],
    );

    const openEditReference = () => {
        editForm.setData('cadastral_reference', storedReference ?? '');
        editForm.clearErrors();
        setEditOpen(true);
    };

    const runCheck = () => {
        setChecking(true);
        router.post(
            route(`${routePrefix}.catastro.check`, leadId),
            {},
            {
                preserveScroll: true,
                onFinish: () => setChecking(false),
            },
        );
    };

    const submitReferenceEdit = (event: FormEvent) => {
        event.preventDefault();
        editForm.put(route(`${routePrefix}.catastro.reference.update`, leadId), {
            preserveScroll: true,
            onSuccess: () => setEditOpen(false),
        });
    };

    const selectCandidate = (cadastralReference: string) => {
        if (!current?.id) {
            return;
        }
        router.post(
            route(`${routePrefix}.catastro.select`, {
                lead: leadId,
                snapshot: current.id,
            }),
            { cadastral_reference: cadastralReference },
            { preserveScroll: true },
        );
    };

    const saveReview = (nextStatus: string) => {
        if (!current?.id || !canReview) {
            return;
        }
        router.post(
            route(`${routePrefix}.catastro.review`, {
                lead: leadId,
                snapshot: current.id,
            }),
            { status: nextStatus, notes: current.auditor_notes ?? '' },
            { preserveScroll: true },
        );
    };

    return (
        <section className="rml-card space-y-3 p-5 sm:p-6">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-base font-semibold text-rml-text">
                        {t.card_title ?? 'Catastro verification'}
                    </h2>
                    <p className="mt-1 text-sm text-rml-muted">
                        {t.card_subtitle ??
                            'Public cadastral characteristics — not ownership proof.'}
                    </p>
                </div>
                <div className="flex flex-col items-end gap-1">
                    <StatusBadge
                        label={statuses[status] ?? status}
                        tone={statusTone(status)}
                    />
                    {statusHint && (
                        <p className="max-w-xs text-right text-xs text-rml-muted">
                            {statusHint}
                        </p>
                    )}
                </div>
            </div>

            <Alert variant="info">
                {catastro.disclaimer ??
                    t.disclaimer ??
                    'Catastro describes cadastral characteristics. It is not proof of legal ownership.'}
            </Alert>

            {!hasReference ? (
                <div className="space-y-3 rounded-lg border border-dashed border-rml-border bg-rml-background p-4">
                    <p className="text-sm text-rml-muted">
                        {t.no_reference ?? 'No cadastral reference provided.'}
                    </p>
                    {canEditReference && (
                        <Button size="sm" variant="outline" onClick={openEditReference}>
                            {t.add_reference ?? 'Add cadastral reference'}
                        </Button>
                    )}
                </div>
            ) : (
                <>
                    <dl className="grid gap-3 sm:grid-cols-2">
                        <div>
                            <dt className="text-xs text-rml-muted">
                                {t.cadastral_reference ?? 'Cadastral reference'}
                            </dt>
                            <dd className="font-mono text-sm text-rml-text">
                                {storedReference ?? '—'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-rml-muted">
                                {t.provider ?? 'Provider'}
                            </dt>
                            <dd className="text-sm text-rml-text">
                                {current?.provider
                                    ? (providers[current.provider] ?? current.provider)
                                    : '—'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-rml-muted">
                                {t.status ?? 'Status'}
                            </dt>
                            <dd className="text-sm text-rml-text">
                                {statuses[status] ?? status}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-rml-muted">
                                {t.last_lookup ?? 'Last lookup'}
                            </dt>
                            <dd className="text-sm text-rml-text">
                                {current?.lookup_at
                                    ? new Date(current.lookup_at).toLocaleString()
                                    : '—'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-rml-muted">
                                {t.warnings ?? 'Warnings'}
                            </dt>
                            <dd className="text-sm text-rml-text">
                                {current?.warning_count ?? 0}
                            </dd>
                        </div>
                    </dl>

                    {current?.lookup_at && (
                        <dl className="grid gap-3 rounded-lg border border-rml-border bg-rml-background p-3 sm:grid-cols-2 lg:grid-cols-3">
                            {[
                                [
                                    t.cadastral_address ?? 'Matched address',
                                    current.cadastral_address,
                                ],
                                [t.municipality ?? 'Municipality', current.municipality],
                                [t.province ?? 'Province', current.province],
                                [t.postcode ?? 'Postcode', current.postcode],
                                [t.property_use ?? 'Property use', current.property_use],
                                [
                                    t.constructed_area ?? 'Constructed area',
                                    current.constructed_area_m2 != null
                                        ? `${current.constructed_area_m2} m²`
                                        : null,
                                ],
                                [
                                    t.construction_year ?? 'Construction year',
                                    current.construction_year,
                                ],
                            ].map(([label, value]) => (
                                <div key={String(label)}>
                                    <dt className="text-xs text-rml-muted">{label}</dt>
                                    <dd className="text-sm text-rml-text">
                                        {value ?? '—'}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    )}

                    {errorMessage && (
                        <Alert variant="error">
                            {errorMessage}
                        </Alert>
                    )}

                    {!errorMessage && summaryMessage && (
                        <p className="text-sm text-rml-muted">{summaryMessage}</p>
                    )}

                    {(current?.warnings?.length ?? 0) > 0 && (
                        <div className="space-y-1 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                            {current?.warnings?.map((warning) => (
                                <p key={`${warning.code}-${warning.message}`}>
                                    {warningMessages[warning.code] ?? warning.message}
                                </p>
                            ))}
                        </div>
                    )}

                    {candidates.length > 0 && (
                        <div className="space-y-2">
                            <p className="text-sm font-medium text-rml-text">
                                {t.select_property ??
                                    'Multiple properties found — select the correct unit'}
                            </p>
                            {candidates.map((candidate) => {
                                const ref = String(
                                    candidate.cadastral_reference ?? '',
                                );
                                return (
                                    <button
                                        key={ref}
                                        type="button"
                                        className="flex w-full flex-col rounded-lg border border-rml-border px-3 py-2 text-left hover:border-rml-primary"
                                        onClick={() => selectCandidate(ref)}
                                    >
                                        <span className="font-mono text-sm font-semibold">
                                            {ref}
                                        </span>
                                        <span className="text-xs text-rml-muted">
                                            {String(candidate.cadastral_address ?? '—')}
                                        </span>
                                        <span className="text-xs text-rml-muted">
                                            {[
                                                candidate.property_use,
                                                candidate.constructed_area_m2 != null
                                                    ? `${candidate.constructed_area_m2} m²`
                                                    : null,
                                                candidate.floor
                                                    ? `Floor ${candidate.floor}`
                                                    : null,
                                                candidate.door
                                                    ? `Door ${candidate.door}`
                                                    : null,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ') || '—'}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    )}
                </>
            )}

            <div className="flex flex-wrap gap-2">
                {canLookup && supportsTechnicalActions && !enabled && (
                    <p className="w-full text-sm text-rml-muted">
                        {t.lookup_unavailable ??
                            'Catastro lookup is currently unavailable.'}
                    </p>
                )}
                {canRunLookup && (
                    <Button
                        size="sm"
                        disabled={checking}
                        onClick={runCheck}
                    >
                        {isRetry && status !== 'not_checked'
                            ? (t.retry_catastro ??
                              t.refresh_lookup ??
                              'Retry Catastro check')
                            : (t.check_catastro ?? 'Check Catastro')}
                    </Button>
                )}
                {canEditReference && hasReference && (
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={openEditReference}
                    >
                        {t.edit_reference ?? 'Edit cadastral reference'}
                    </Button>
                )}
                {hasReference && current?.lookup_at && (
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() => setDetailOpen(true)}
                    >
                        {t.view_result ?? 'View Catastro result'}
                    </Button>
                )}
                {canReview && current?.id && (
                    <>
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => saveReview('matched')}
                        >
                            {t.confirm_match ?? 'Confirm match'}
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() =>
                                saveReview('manual_review_required')
                            }
                        >
                            {t.require_manual ?? 'Require manual verification'}
                        </Button>
                    </>
                )}
            </div>

            <Modal
                open={editOpen}
                onClose={() => setEditOpen(false)}
                title={
                    hasReference
                        ? (t.edit_reference ?? 'Edit cadastral reference')
                        : (t.add_reference ?? 'Add cadastral reference')
                }
                size="md"
            >
                <form onSubmit={submitReferenceEdit} className="space-y-3">
                    <FormInput
                        label={t.cadastral_reference ?? 'Cadastral reference'}
                        value={editForm.data.cadastral_reference}
                        onChange={(e) =>
                            editForm.setData(
                                'cadastral_reference',
                                e.target.value,
                            )
                        }
                        error={editForm.errors.cadastral_reference}
                        hint={
                            t.reference_hint ??
                            'Enter a 14 or 20 character Spanish cadastral reference.'
                        }
                        placeholder="9872023VH5797S0001WX"
                    />
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="submit"
                            disabled={editForm.processing}
                        >
                            {t.save_reference ?? 'Save reference'}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setEditOpen(false)}
                        >
                            {t.cancel ?? 'Cancel'}
                        </Button>
                    </div>
                </form>
            </Modal>

            <Modal
                open={detailOpen}
                onClose={() => setDetailOpen(false)}
                title={t.view_result ?? 'Catastro result'}
                size="lg"
            >
                <dl className="grid gap-3 sm:grid-cols-2 text-sm">
                    {[
                        [t.cadastral_address ?? 'Address', current?.cadastral_address],
                        [t.province ?? 'Province', current?.province],
                        [t.municipality ?? 'Municipality', current?.municipality],
                        [t.property_use ?? 'Property use', current?.property_use],
                        [
                            t.constructed_area ?? 'Constructed area',
                            current?.constructed_area_m2 != null
                                ? `${current.constructed_area_m2} m²`
                                : null,
                        ],
                        [
                            t.parcel_area ?? 'Parcel area',
                            current?.parcel_area_m2 != null
                                ? `${current.parcel_area_m2} m²`
                                : null,
                        ],
                        [
                            t.construction_year ?? 'Construction year',
                            current?.construction_year,
                        ],
                        [t.unit_label ?? 'Unit', current?.unit_label],
                    ].map(([label, value]) => (
                        <div key={String(label)}>
                            <dt className="text-xs text-rml-muted">{label}</dt>
                            <dd className="text-rml-text">{value ?? '—'}</dd>
                        </div>
                    ))}
                </dl>
            </Modal>
        </section>
    );
}
