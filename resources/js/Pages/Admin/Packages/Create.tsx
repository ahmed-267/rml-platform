import { FormEvent, useEffect, useMemo, useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import {
    Alert,
    BackLink,
    Button,
    EmptyState,
    FormInput,
    Select,
    Stepper,
} from '@/Components/ui';
import { useScrollToFirstError } from '@/hooks/use-scroll-to-first-error';
import { formatMoney } from '@/lib/admin-helpers';
import { cn } from '@/lib/cn';
import type { PageProps } from '@/types';

type EligibleLead = {
    id: number;
    lead_reference: string;
    scheme: string | null;
    scheme_id: number | null;
    zone: string | null;
    seller_company: string | null;
    size_m2: number | null;
    selling_price: number;
    buying_price: number;
    distance_km?: number | null;
    city?: string | null;
};

type StepId = 'setup' | 'leads' | 'review';

const STEPS: StepId[] = ['setup', 'leads', 'review'];

export default function AdminPackageCreate({
    eligible_leads,
    installers,
    radius_options_km,
    default_radius_km,
    allow_without_buyer = true,
    allow_mixed_scheme = true,
    filters,
    nearby = null,
}: {
    eligible_leads: EligibleLead[];
    installers: Array<{
        id: number;
        name: string;
        city?: string | null;
        matched_count?: number;
    }>;
    radius_options_km: number[];
    default_radius_km: number;
    allow_without_buyer?: boolean;
    allow_mixed_scheme?: boolean;
    filters?: {
        match_installer_id?: number | null;
        radius_km?: number | null;
    };
    nearby?: {
        active?: boolean;
        summary?: {
            matched_count?: number;
            average_distance_km?: number | null;
            total_size_m2?: number;
        };
        installer?: { name?: string | null; city?: string | null } | null;
    } | null;
}) {
    const { translations } = usePage<PageProps>().props;
    const t = (translations.admin as Record<string, any>).packages ?? {};
    const common = translations.admin.common;

    const initialInstaller = filters?.match_installer_id
        ? String(filters.match_installer_id)
        : '';
    const initialRadius = String(
        filters?.radius_km ?? default_radius_km,
    );

    const [mode, setMode] = useState<'manual' | 'installer'>(
        initialInstaller || !allow_without_buyer ? 'installer' : 'manual',
    );
    const [step, setStep] = useState<StepId>('setup');
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [clientErrors, setClientErrors] = useState<Record<string, string>>(
        {},
    );

    const form = useForm({
        match_installer_id: initialInstaller,
        radius_km: initialRadius,
        lead_ids: [] as number[],
        name: '',
    });

    useScrollToFirstError({
        ...form.errors,
        ...clientErrors,
    });

    useEffect(() => {
        setSelectedIds((current) =>
            current.filter((id) =>
                eligible_leads.some((lead) => lead.id === id),
            ),
        );
    }, [eligible_leads]);

    const selectedLeads = useMemo(
        () => eligible_leads.filter((l) => selectedIds.includes(l.id)),
        [eligible_leads, selectedIds],
    );

    const summary = useMemo(() => {
        const totalSelling = selectedLeads.reduce(
            (sum, l) => sum + (l.selling_price ?? 0),
            0,
        );
        const totalPayout = selectedLeads.reduce(
            (sum, l) => sum + (l.buying_price ?? 0),
            0,
        );
        const totalSize = selectedLeads.reduce(
            (sum, l) => sum + (l.size_m2 ?? 0),
            0,
        );
        const schemes = [
            ...new Set(selectedLeads.map((l) => l.scheme).filter(Boolean)),
        ] as string[];
        const zones = [
            ...new Set(selectedLeads.map((l) => l.zone).filter(Boolean)),
        ] as string[];
        const distances = selectedLeads
            .map((l) => l.distance_km)
            .filter((d): d is number => typeof d === 'number');

        return {
            count: selectedLeads.length,
            schemes,
            zones,
            totalSelling: Math.round(totalSelling * 100) / 100,
            totalPayout: Math.round(totalPayout * 100) / 100,
            margin: Math.round((totalSelling - totalPayout) * 100) / 100,
            totalSize: Math.round(totalSize * 100) / 100,
            avgDistance:
                distances.length > 0
                    ? Math.round(
                          (distances.reduce((a, b) => a + b, 0) /
                              distances.length) *
                              10,
                      ) / 10
                    : null,
            minDistance:
                distances.length > 0 ? Math.min(...distances) : null,
            maxDistance:
                distances.length > 0 ? Math.max(...distances) : null,
        };
    }, [selectedLeads]);

    const mixedSchemeBlocked =
        !allow_mixed_scheme && summary.schemes.length > 1;

    const reloadNearby = (installerId: string, radiusKm: string) => {
        router.get(
            route('admin.packages.create'),
            {
                match_installer_id: installerId || undefined,
                radius_km: radiusKm || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const toggle = (id: number) => {
        setSelectedIds((current) =>
            current.includes(id)
                ? current.filter((item) => item !== id)
                : [...current, id],
        );
        setClientErrors((prev) => {
            const next = { ...prev };
            delete next.lead_ids;
            return next;
        });
    };

    const validateSetup = (): boolean => {
        const next: Record<string, string> = {};
        if (mode === 'installer' && !form.data.match_installer_id) {
            next.match_installer_id =
                t.buyer_required ?? 'Select a buyer / installer.';
        }
        setClientErrors(next);
        return Object.keys(next).length === 0;
    };

    const validateLeads = (): boolean => {
        const next: Record<string, string> = {};
        if (selectedIds.length === 0) {
            next.lead_ids =
                t.select_at_least_one ?? 'Select at least one eligible lead.';
        }
        if (mixedSchemeBlocked) {
            next.lead_ids =
                t.mixed_scheme_blocked ??
                'Mixed schemes are not allowed by current settings.';
        }
        setClientErrors(next);
        return Object.keys(next).length === 0;
    };

    const goNext = () => {
        if (step === 'setup') {
            if (!validateSetup()) {
                return;
            }
            setStep('leads');
            return;
        }
        if (step === 'leads') {
            if (!validateLeads()) {
                return;
            }
            setStep('review');
        }
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (!validateSetup() || !validateLeads()) {
            setStep(
                !form.data.match_installer_id && mode === 'installer'
                    ? 'setup'
                    : 'leads',
            );
            return;
        }
        form.transform((data) => ({
            ...data,
            match_installer_id:
                mode === 'installer' && data.match_installer_id
                    ? Number(data.match_installer_id)
                    : null,
            radius_km:
                mode === 'installer' ? Number(data.radius_km) : undefined,
            lead_ids: selectedIds,
        }));
        form.post(route('admin.packages.store'));
    };

    const fieldError = (key: keyof typeof form.data | 'lead_ids') =>
        clientErrors[key] ??
        (form.errors as Record<string, string | undefined>)[key];

    const stepItems = STEPS.map((id) => ({
        id,
        label:
            id === 'setup'
                ? (t.step_setup ?? 'Package setup')
                : id === 'leads'
                  ? (t.step_leads ?? 'Select leads')
                  : (t.step_review ?? 'Review'),
    }));

    return (
        <AppLayout
            title={t.create_package ?? 'Create package'}
            subtitle={
                t.create_subtitle ??
                'Build a manual or installer-based package from eligible leads'
            }
        >
            <Head title={t.create_package ?? 'Create package'} />
            <BackLink
                href={route('admin.leads.index', { tab: 'packages' })}
                label={common.back}
                showLabel
            />

            <form className="mt-4 space-y-4" onSubmit={submit}>
                <Stepper
                    steps={stepItems}
                    current={step}
                    onChange={(id) => {
                        const target = id as StepId;
                        if (
                            STEPS.indexOf(target) < STEPS.indexOf(step) ||
                            (target === 'leads' && validateSetup()) ||
                            (target === 'review' &&
                                validateSetup() &&
                                validateLeads())
                        ) {
                            setStep(target);
                        }
                    }}
                />

                {step === 'setup' && (
                    <div className="space-y-4">
                        <div className="rml-card flex flex-wrap gap-2 p-3">
                            <Button
                                type="button"
                                size="sm"
                                variant={
                                    mode === 'manual' ? 'primary' : 'outline'
                                }
                                disabled={!allow_without_buyer}
                                onClick={() => {
                                    setMode('manual');
                                    form.setData('match_installer_id', '');
                                    if (initialInstaller) {
                                        router.get(
                                            route('admin.packages.create'),
                                            {},
                                            {
                                                preserveState: true,
                                                replace: true,
                                            },
                                        );
                                    }
                                }}
                            >
                                {t.mode_manual ?? 'Manual package'}
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant={
                                    mode === 'installer'
                                        ? 'primary'
                                        : 'outline'
                                }
                                onClick={() => setMode('installer')}
                            >
                                {t.mode_installer ??
                                    'Installer-based package'}
                            </Button>
                        </div>

                        {!allow_without_buyer && mode === 'manual' && (
                            <Alert variant="warning">
                                {t.manual_disabled ??
                                    'Manual packages without a buyer are disabled in settings.'}
                            </Alert>
                        )}

                        <div className="rml-card grid gap-3 p-4 sm:grid-cols-2">
                            {mode === 'installer' && (
                                <>
                                    <Select
                                        label={
                                            t.buyer ?? 'Buyer / installer'
                                        }
                                        required
                                        value={form.data.match_installer_id}
                                        error={fieldError(
                                            'match_installer_id',
                                        )}
                                        onChange={(e) => {
                                            const value = e.target.value;
                                            form.setData(
                                                'match_installer_id',
                                                value,
                                            );
                                            setClientErrors((prev) => {
                                                const next = { ...prev };
                                                delete next.match_installer_id;
                                                return next;
                                            });
                                            reloadNearby(
                                                value,
                                                form.data.radius_km,
                                            );
                                        }}
                                        options={[
                                            { label: '—', value: '' },
                                            ...installers.map((item) => ({
                                                label: item.city
                                                    ? `${item.name} — ${item.city}${
                                                          item.matched_count !=
                                                          null
                                                              ? ` (${item.matched_count})`
                                                              : ''
                                                      }`
                                                    : item.name,
                                                value: String(item.id),
                                            })),
                                        ]}
                                    />
                                    <Select
                                        label={t.radius ?? 'Radius'}
                                        required
                                        value={form.data.radius_km}
                                        onChange={(e) => {
                                            const value = e.target.value;
                                            form.setData('radius_km', value);
                                            if (
                                                form.data.match_installer_id
                                            ) {
                                                reloadNearby(
                                                    form.data
                                                        .match_installer_id,
                                                    value,
                                                );
                                            }
                                        }}
                                        options={radius_options_km.map(
                                            (km) => ({
                                                label: `${km} km`,
                                                value: String(km),
                                            }),
                                        )}
                                    />
                                </>
                            )}
                            <FormInput
                                label={t.name ?? 'Package name'}
                                value={form.data.name}
                                error={form.errors.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                                className="sm:col-span-2"
                                placeholder={
                                    t.name_placeholder ??
                                    'Optional — auto-generated if empty'
                                }
                            />
                        </div>

                        {nearby?.active && (
                            <Alert variant="info">
                                {(
                                    t.nearby_summary ??
                                    ':count nearby eligible leads · avg :avg km · :size m²'
                                )
                                    .replace(
                                        ':count',
                                        String(
                                            nearby.summary?.matched_count ??
                                                eligible_leads.length,
                                        ),
                                    )
                                    .replace(
                                        ':avg',
                                        String(
                                            nearby.summary
                                                ?.average_distance_km ?? '—',
                                        ),
                                    )
                                    .replace(
                                        ':size',
                                        String(
                                            nearby.summary?.total_size_m2 ??
                                                0,
                                        ),
                                    )}
                            </Alert>
                        )}
                    </div>
                )}

                {step === 'leads' && (
                    <div className="rml-card p-4">
                        <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <h2 className="text-sm font-semibold">
                                {t.select_leads ?? 'Select eligible leads'}
                                <span className="ml-0.5 text-rml-red">*</span>
                            </h2>
                            <div className="flex gap-2">
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        setSelectedIds(
                                            eligible_leads.map((l) => l.id),
                                        )
                                    }
                                    disabled={eligible_leads.length === 0}
                                >
                                    {t.select_all ?? 'Select all'}
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    onClick={() => setSelectedIds([])}
                                >
                                    {t.clear_selection ?? 'Clear'}
                                </Button>
                            </div>
                        </div>

                        {mode === 'installer' &&
                            !form.data.match_installer_id && (
                                <Alert variant="warning">
                                    {t.select_installer_first ??
                                        'Select an installer first to load nearby eligible leads.'}
                                </Alert>
                            )}

                        {eligible_leads.length === 0 ? (
                            <EmptyState
                                title={
                                    t.no_eligible_leads ??
                                    'No eligible listed leads available.'
                                }
                                description={
                                    mode === 'installer'
                                        ? (t.no_nearby_leads ??
                                          'No eligible leads within the selected radius.')
                                        : undefined
                                }
                            />
                        ) : (
                            <ul className="max-h-[28rem] space-y-2 overflow-y-auto">
                                {eligible_leads.map((lead) => {
                                    const checked = selectedIds.includes(
                                        lead.id,
                                    );
                                    return (
                                        <li key={lead.id}>
                                            <label
                                                className={cn(
                                                    'flex cursor-pointer items-start gap-3 rounded-lg border px-3 py-2',
                                                    checked
                                                        ? 'border-rml-primary bg-rml-primary-light/40'
                                                        : 'border-rml-border',
                                                )}
                                            >
                                                <input
                                                    type="checkbox"
                                                    className="mt-1"
                                                    checked={checked}
                                                    onChange={() =>
                                                        toggle(lead.id)
                                                    }
                                                />
                                                <span className="min-w-0 flex-1 text-sm">
                                                    <span className="font-mono font-medium">
                                                        {lead.lead_reference}
                                                    </span>
                                                    <span className="mt-0.5 block text-xs text-rml-muted">
                                                        {[
                                                            lead.seller_company,
                                                            lead.scheme,
                                                            lead.zone,
                                                            lead.city,
                                                            lead.size_m2 !=
                                                            null
                                                                ? `${lead.size_m2} m²`
                                                                : null,
                                                            lead.distance_km !=
                                                            null
                                                                ? `${lead.distance_km} km`
                                                                : null,
                                                        ]
                                                            .filter(Boolean)
                                                            .join(' · ')}
                                                    </span>
                                                </span>
                                                <span className="text-xs tabular-nums">
                                                    {formatMoney(
                                                        lead.selling_price,
                                                    )}
                                                </span>
                                            </label>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                        {fieldError('lead_ids') && (
                            <p className="mt-2 text-sm text-rml-red">
                                {fieldError('lead_ids')}
                            </p>
                        )}
                    </div>
                )}

                {step === 'review' && (
                    <div className="space-y-4">
                        <div className="rml-card space-y-2 p-4 text-sm">
                            <p className="font-semibold">
                                {t.summary ?? 'Package summary'}
                            </p>
                            <p>
                                {(t.selected_leads ?? ':count leads').replace(
                                    ':count',
                                    String(summary.count),
                                )}{' '}
                                · {summary.totalSize} m²
                            </p>
                            <p>
                                {t.schemes ?? 'Schemes'}:{' '}
                                {summary.schemes.join(', ') || '—'}
                            </p>
                            <p>
                                {t.zones ?? 'Zones'}:{' '}
                                {summary.zones.join(', ') || '—'}
                            </p>
                            {mode === 'installer' && (
                                <p>
                                    {t.distance_range ?? 'Distance'}:{' '}
                                    {summary.minDistance != null &&
                                    summary.maxDistance != null
                                        ? `${summary.minDistance} – ${summary.maxDistance} km`
                                        : '—'}
                                    {summary.avgDistance != null
                                        ? ` · avg ${summary.avgDistance} km`
                                        : ''}
                                </p>
                            )}
                            <p>
                                {t.total_price ?? 'Selling'}:{' '}
                                {formatMoney(summary.totalSelling)}
                            </p>
                            <p>
                                {t.total_payout ?? 'Seller payout'}:{' '}
                                {formatMoney(summary.totalPayout)}
                            </p>
                            <p>
                                {t.margin ?? 'Estimated margin'}:{' '}
                                {formatMoney(summary.margin)}
                            </p>
                            <p>
                                {t.buyer ?? 'Buyer / installer'}:{' '}
                                {mode === 'installer'
                                    ? nearby?.installer?.name ??
                                      installers.find(
                                          (item) =>
                                              String(item.id) ===
                                              form.data.match_installer_id,
                                      )?.name ??
                                      '—'
                                    : (t.unassigned ?? 'Unassigned')}
                            </p>
                            <p>
                                {t.name ?? 'Package name'}:{' '}
                                {form.data.name ||
                                    (t.auto_name ?? 'Auto-generated')}
                            </p>
                        </div>
                        <Alert variant="info">
                            {t.review_hint ??
                                'Only listed, audited, unlocked leads are included. Sold, rejected, cancelled, and reserved leads cannot be packaged.'}
                        </Alert>
                    </div>
                )}

                <div className="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={() => {
                            if (step === 'setup') {
                                router.get(
                                    route('admin.leads.index', {
                                        tab: 'packages',
                                    }),
                                );
                                return;
                            }
                            setStep(
                                STEPS[
                                    Math.max(0, STEPS.indexOf(step) - 1)
                                ],
                            );
                        }}
                    >
                        {step === 'setup'
                            ? common.cancel
                            : (common.back ?? 'Back')}
                    </Button>
                    {step !== 'review' ? (
                        <Button type="button" onClick={goNext}>
                            {common.continue ??
                                common.next ??
                                'Continue'}
                        </Button>
                    ) : (
                        <Button
                            type="submit"
                            disabled={
                                form.processing ||
                                selectedIds.length === 0 ||
                                mixedSchemeBlocked
                            }
                        >
                            {t.create_package ?? 'Create package'}
                        </Button>
                    )}
                </div>
            </form>
        </AppLayout>
    );
}
