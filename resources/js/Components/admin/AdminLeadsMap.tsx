import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import { importLibrary, setOptions } from '@googlemaps/js-api-loader';
import { Button, EmptyState, FormInput, Modal, StatusBadge, Tooltip } from '@/Components/ui';
import { leadStatusLabel, leadStatusTone } from '@/lib/lead-status';
import { approvalStatusLabel } from '@/lib/admin-helpers';
import { cn } from '@/lib/cn';
import { formatMoney } from '@/lib/admin-helpers';

export type MapLeadMarker = {
    id: number;
    type: 'lead';
    lead_reference: string;
    status: string | null;
    scheme: string | null;
    zone: string | null;
    size_m2: number | null;
    seller_company: string | null;
    city?: string | null;
    approximate_location?: string | null;
    buyer_company?: string | null;
    buyer_name?: string | null;
    payment_status?: string | null;
    distance_km?: number | null;
    matched?: boolean;
    packagable?: boolean;
    packagable_reason?: string | null;
    selling_price?: number | null;
    buying_price?: number | null;
    expected_margin?: number | null;
    latitude: number;
    longitude: number;
    view_url: string;
    geocode_url?: string;
    location_url?: string;
};

export type MapInstallerMarker = {
    id: number;
    type: 'installer';
    company_name: string;
    base_location: string | null;
    status: string | null;
    nearby_leads_count: number;
    nearby_radius_km: number;
    selected?: boolean;
    latitude: number;
    longitude: number;
    buyer_user_id?: number | null;
    view_url: string | null;
    geocode_url?: string | null;
    location_url?: string | null;
};

export type MarkerSet = 'leads' | 'installers' | 'both';

export type NearbyMatchPayload = {
    active: boolean;
    installer: {
        id: number;
        company_name: string;
    city?: string | null;
    base_location?: string | null;
    latitude: number;
    longitude: number;
    view_url?: string | null;
    } | null;
    radius_km: number;
    leads: MapLeadMarker[];
    summary: {
        matched_count: number;
        average_distance_km: number | null;
        total_size_m2: number;
    };
};

type MatchInstallerHandler = (installerId: number) => void;

type Labels = {
    not_configured: string;
    not_configured_help: string;
    show_leads: string;
    show_installers: string;
    show_both: string;
    leads_list: string;
    installers_list: string;
    no_leads: string;
    no_installers: string;
    no_markers: string;
    no_nearby_matches: string;
    hidden_notice: string;
    filters_leads_hint: string;
    view_lead: string;
    view_buyer: string;
    retry_geocoding: string;
    edit_location: string;
    nearby_leads: string;
    nearby_panel_title: string;
    nearby_avg_distance: string;
    nearby_total_size: string;
    distance_from_installer: string;
    select_all_eligible: string;
    clear_selection: string;
    create_package: string;
    package_summary_title: string;
    selected_leads: string;
    schemes_included: string;
    zones_included: string;
    distance_range: string;
    total_seller_payout: string;
    total_selling_price: string;
    estimated_margin: string;
    package_name: string;
    confirm_create_package: string;
    manual_package?: string;
    cancel: string;
    eligible_nearby: string;
    select_for_package: string;
    section_leads_for_package?: string;
    section_visible_installers?: string;
    selected_installer?: string;
    clear_installer?: string;
    change_installer?: string;
    visible_eligible_summary?: string;
    no_eligible_leads?: string;
    match_nearby?: string;
    empty_leads_title?: string;
    empty_installers_title?: string;
    empty_nearby_title?: string;
    ineligible_reasons?: Record<string, string>;
    scheme: string;
    zone: string;
    size: string;
    status: string;
    seller: string;
    buyer: string;
    payment_status: string;
    company: string;
    base_location: string;
    focus: string;
    city?: string;
};

type Props = {
    apiKey: string | undefined;
    center: { lat: number; lng: number };
    tab: 'registered' | 'sold';
    leads: MapLeadMarker[];
    installers: MapInstallerMarker[];
    hidden: { leads: number; installers: number };
    canSeeInstallers: boolean;
    canManagePackages?: boolean;
    markerSet: MarkerSet;
    onMarkerSetChange: (value: MarkerSet) => void;
    nearby?: NearbyMatchPayload | null;
    onMatchInstaller?: MatchInstallerHandler;
    labels: Labels;
    leadStatuses: Record<string, string>;
    approvalStatuses: Record<string, string>;
    paymentStatuses?: Record<string, string>;
};

type Selection =
    | { type: 'lead'; id: number }
    | { type: 'installer'; id: number }
    | null;

const LEAD_COLOR = '#0F766E';
const INSTALLER_COLOR = '#C2410C';

function escapeHtml(value: string | number | null | undefined): string {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function pinSvg(color: string): string {
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="36" height="48" viewBox="0 0 36 48"><path fill="${color}" stroke="#fff" stroke-width="2" d="M18 1C9.2 1 2 8.2 2 17c0 12.4 16 30 16 30s16-17.6 16-30C34 8.2 26.8 1 18 1z"/><circle cx="18" cy="17" r="6" fill="#fff"/></svg>`;
    return `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`;
}

/** Square “warehouse” pin so installers stay distinct from teardrop lead pins. */
function installerPinSvg(color: string): string {
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="36" height="48" viewBox="0 0 36 48"><path fill="${color}" stroke="#fff" stroke-width="2" d="M18 2L4 14v30h28V14L18 2z"/><rect x="12" y="22" width="12" height="14" fill="#fff" rx="1"/></svg>`;
    return `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`;
}

function LeadSideCard({
    lead,
    active,
    selected,
    showCheckbox,
    checkboxEnabled,
    checkboxDisabledReason,
    labels,
    leadStatuses,
    paymentStatuses,
    showBuyerContext,
    onFocus,
    onToggleSelect,
    onRetry,
}: {
    lead: MapLeadMarker;
    active: boolean;
    selected: boolean;
    showCheckbox: boolean;
    checkboxEnabled: boolean;
    checkboxDisabledReason?: string | null;
    labels: Labels;
    leadStatuses: Record<string, string>;
    paymentStatuses?: Record<string, string>;
    showBuyerContext: boolean;
    onFocus: () => void;
    onToggleSelect?: () => void;
    onRetry: (url?: string) => void;
}) {
    const checkbox = showCheckbox ? (
        <input
            type="checkbox"
            className={cn(
                'mt-1 h-4 w-4 rounded border-rml-border text-rml-primary focus:ring-rml-primary',
                !checkboxEnabled && 'cursor-not-allowed opacity-40',
            )}
            checked={selected && checkboxEnabled}
            disabled={!checkboxEnabled}
            onChange={(e) => {
                e.stopPropagation();
                if (checkboxEnabled) {
                    onToggleSelect?.();
                }
            }}
            aria-label={lead.lead_reference}
            title={checkboxDisabledReason ?? undefined}
        />
    ) : null;

    return (
        <div
            className={cn(
                'w-full rounded-lg border px-3 py-2 text-left transition',
                selected && checkboxEnabled
                    ? 'border-rml-primary bg-rml-primary-light/50 ring-1 ring-rml-primary/30'
                    : active
                      ? 'border-rml-primary bg-rml-primary-light/40'
                      : 'border-rml-border hover:border-rml-primary/40',
            )}
        >
            <div className="flex items-start gap-2">
                {showCheckbox &&
                    (checkboxDisabledReason ? (
                        <Tooltip label={checkboxDisabledReason} side="bottom">
                            {checkbox}
                        </Tooltip>
                    ) : (
                        checkbox
                    ))}
                <button
                    type="button"
                    onClick={onFocus}
                    className="min-w-0 flex-1 text-left"
                >
                    <div className="flex items-start justify-between gap-2">
                        <p className="font-mono text-sm font-semibold text-rml-text">
                            {lead.lead_reference}
                        </p>
                        {lead.status && (
                            <StatusBadge
                                label={leadStatusLabel(lead.status, leadStatuses)}
                                tone={leadStatusTone(lead.status)}
                            />
                        )}
                    </div>
                    <p className="mt-1 text-xs text-rml-muted">
                        {[lead.scheme, lead.zone].filter(Boolean).join(' · ') ||
                            '—'}
                        {lead.size_m2 != null ? ` · ${lead.size_m2} m²` : ''}
                    </p>
                    <p className="mt-0.5 text-xs text-rml-muted">
                        {labels.seller}: {lead.seller_company ?? '—'}
                    </p>
                    {(lead.city || lead.approximate_location) && (
                        <p className="mt-0.5 text-xs text-rml-muted">
                            {lead.city || lead.approximate_location}
                        </p>
                    )}
                    {lead.distance_km != null && (
                        <p className="mt-0.5 text-xs font-medium text-rml-primary">
                            {labels.distance_from_installer.replace(
                                ':km',
                                String(lead.distance_km),
                            )}
                        </p>
                    )}
                    {showBuyerContext && (
                        <>
                            <p className="mt-0.5 text-xs text-rml-muted">
                                {labels.buyer}:{' '}
                                {lead.buyer_company ?? lead.buyer_name ?? '—'}
                            </p>
                            {lead.payment_status && (
                                <p className="mt-0.5 text-xs text-rml-muted">
                                    {labels.payment_status}:{' '}
                                    {paymentStatuses?.[lead.payment_status] ??
                                        lead.payment_status}
                                </p>
                            )}
                        </>
                    )}
                </button>
            </div>
            <div className="mt-2 flex flex-wrap gap-2 pl-6">
                <Link
                    href={lead.view_url}
                    className="text-xs font-medium text-rml-primary"
                    onClick={(e) => e.stopPropagation()}
                >
                    {labels.view_lead}
                </Link>
                {(lead.location_url || lead.view_url) && (
                    <Link
                        href={lead.location_url || lead.view_url}
                        className="text-xs font-medium text-rml-muted underline"
                        onClick={(e) => e.stopPropagation()}
                    >
                        {labels.edit_location}
                    </Link>
                )}
                {lead.geocode_url && (
                    <button
                        type="button"
                        className="text-xs font-medium text-rml-muted underline"
                        onClick={(e) => {
                            e.stopPropagation();
                            onRetry(lead.geocode_url);
                        }}
                    >
                        {labels.retry_geocoding}
                    </button>
                )}
            </div>
        </div>
    );
}

function InstallerSideCard({
    installer,
    active,
    labels,
    approvalStatuses,
    canMatch,
    onFocus,
    onMatch,
    onRetry,
}: {
    installer: MapInstallerMarker;
    active: boolean;
    labels: Labels;
    approvalStatuses: Record<string, string>;
    canMatch: boolean;
    onFocus: () => void;
    onMatch?: () => void;
    onRetry: (url?: string | null) => void;
}) {
    return (
        <div
            className={cn(
                'w-full rounded-lg border px-3 py-2 text-left transition',
                active || installer.selected
                    ? 'border-orange-300 bg-orange-50'
                    : 'border-rml-border hover:border-orange-200',
            )}
        >
            <button type="button" onClick={onFocus} className="w-full text-left">
                <p className="text-sm font-semibold text-rml-text">
                    {installer.company_name}
                </p>
                <p className="mt-1 text-xs text-rml-muted">
                    {installer.base_location ?? '—'}
                </p>
                {installer.status && (
                    <p className="mt-1 text-xs text-rml-muted">
                        {labels.status}:{' '}
                        {approvalStatusLabel(
                            installer.status,
                            approvalStatuses,
                        )}
                    </p>
                )}
                <p className="mt-1 text-xs text-rml-muted">
                    {labels.nearby_leads}: {installer.nearby_leads_count}
                    {installer.nearby_radius_km
                        ? ` (≤ ${installer.nearby_radius_km} km)`
                        : ''}
                </p>
            </button>
            <div className="mt-2 flex flex-wrap gap-2">
                {canMatch && onMatch && (
                    <button
                        type="button"
                        className="text-xs font-semibold text-orange-700"
                        onClick={(e) => {
                            e.stopPropagation();
                            onMatch();
                        }}
                    >
                        {labels.match_nearby ?? 'Match nearby leads'}
                    </button>
                )}
                {installer.view_url && (
                    <Link
                        href={installer.view_url}
                        className="text-xs font-medium text-orange-700"
                        onClick={(e) => e.stopPropagation()}
                    >
                        {labels.view_buyer}
                    </Link>
                )}
                {(installer.location_url || installer.view_url) && (
                    <Link
                        href={
                            installer.location_url ||
                            installer.view_url ||
                            '#'
                        }
                        className="text-xs font-medium text-rml-muted underline"
                        onClick={(e) => e.stopPropagation()}
                    >
                        {labels.edit_location}
                    </Link>
                )}
                {installer.geocode_url && (
                    <button
                        type="button"
                        className="text-xs font-medium text-rml-muted underline"
                        onClick={(e) => {
                            e.stopPropagation();
                            onRetry(installer.geocode_url);
                        }}
                    >
                        {labels.retry_geocoding}
                    </button>
                )}
            </div>
        </div>
    );
}

function SideSection({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <div className="space-y-2">
            <h4 className="sticky top-0 z-10 bg-white/95 px-1 py-1 text-xs font-semibold uppercase tracking-wide text-rml-muted backdrop-blur">
                {title}
            </h4>
            <div className="space-y-2">{children}</div>
        </div>
    );
}

export function AdminLeadsMap({
    apiKey,
    center,
    tab,
    leads = [],
    installers = [],
    hidden,
    canSeeInstallers,
    canManagePackages = false,
    markerSet,
    onMarkerSetChange,
    nearby = null,
    onMatchInstaller,
    labels,
    leadStatuses,
    approvalStatuses,
    paymentStatuses,
}: Props) {
    const mapNodeRef = useRef<HTMLDivElement | null>(null);
    const mapRef = useRef<google.maps.Map | null>(null);
    const markersRef = useRef<google.maps.Marker[]>([]);
    const infoRef = useRef<google.maps.InfoWindow | null>(null);
    const circleRef = useRef<google.maps.Circle | null>(null);
    const [ready, setReady] = useState(false);
    const [loadError, setLoadError] = useState(false);
    const [selection, setSelection] = useState<Selection>(null);
    const [selectedLeadIds, setSelectedLeadIds] = useState<number[]>([]);
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [packageName, setPackageName] = useState('');

    const matchActive = Boolean(nearby?.active && nearby.installer);
    const packageMode = canManagePackages && tab === 'registered';
    const showLeads = markerSet === 'leads' || markerSet === 'both';
    const showInstallers =
        canSeeInstallers &&
        (markerSet === 'installers' || markerSet === 'both');
    const combinedMode = showLeads && showInstallers;

    const visibleLeads = useMemo(
        () => (showLeads ? leads : []),
        [showLeads, leads],
    );
    const visibleInstallers = useMemo(
        () => (showInstallers ? installers : []),
        [showInstallers, installers],
    );
    const packagableLeads = useMemo(
        () => visibleLeads.filter((lead) => Boolean(lead.packagable)),
        [visibleLeads],
    );

    const ineligibleReason = (lead: MapLeadMarker): string | null => {
        if (lead.packagable) {
            return null;
        }
        const key = lead.packagable_reason ?? 'not_listed';
        return (
            labels.ineligible_reasons?.[key] ??
            labels.ineligible_reasons?.not_listed ??
            'Lead is not eligible for packaging.'
        );
    };

    useEffect(() => {
        setSelectedLeadIds([]);
        setConfirmOpen(false);
        setPackageName('');
    }, [nearby?.installer?.id, nearby?.radius_km, nearby?.active]);

    const selectedLeads = useMemo(
        () =>
            visibleLeads.filter(
                (lead) =>
                    selectedLeadIds.includes(lead.id) &&
                    Boolean(lead.packagable),
            ),
        [visibleLeads, selectedLeadIds],
    );

    const packageSummary = useMemo(() => {
        if (selectedLeads.length === 0) {
            return null;
        }
        const schemes = [
            ...new Set(selectedLeads.map((l) => l.scheme).filter(Boolean)),
        ] as string[];
        const zones = [
            ...new Set(selectedLeads.map((l) => l.zone).filter(Boolean)),
        ] as string[];
        const distances = selectedLeads
            .map((l) => l.distance_km)
            .filter((km): km is number => km != null);
        const totalSize = selectedLeads.reduce(
            (sum, l) => sum + (l.size_m2 ?? 0),
            0,
        );
        const totalSelling = selectedLeads.reduce(
            (sum, l) => sum + (l.selling_price ?? 0),
            0,
        );
        const totalPayout = selectedLeads.reduce(
            (sum, l) => sum + (l.buying_price ?? 0),
            0,
        );
        const avgDistance =
            distances.length > 0
                ? Math.round(
                      (distances.reduce((a, b) => a + b, 0) / distances.length) *
                          10,
                  ) / 10
                : null;

        return {
            count: selectedLeads.length,
            schemes,
            zones,
            totalSize: Math.round(totalSize * 100) / 100,
            avgDistance,
            distanceMin:
                distances.length > 0 ? Math.min(...distances) : null,
            distanceMax:
                distances.length > 0 ? Math.max(...distances) : null,
            totalSelling: Math.round(totalSelling * 100) / 100,
            totalPayout: Math.round(totalPayout * 100) / 100,
            margin: Math.round((totalSelling - totalPayout) * 100) / 100,
        };
    }, [selectedLeads]);

    const suggestedPackageName = useMemo(() => {
        if (selectedLeads.length === 0) {
            return '';
        }
        const city =
            nearby?.installer?.city ||
            nearby?.installer?.base_location?.split(',')[0] ||
            selectedLeads
                .map((l) => l.zone)
                .filter(Boolean)[0] ||
            'Regional';
        const schemes = [
            ...new Set(selectedLeads.map((l) => l.scheme).filter(Boolean)),
        ];
        const schemeLabel =
            schemes.length === 0
                ? 'Energy'
                : schemes.length === 1
                  ? String(schemes[0])
                  : 'Mixed Energy';
        return `${String(city).trim()} ${schemeLabel} Leads Package`;
    }, [nearby?.installer, selectedLeads]);

    const createForm = useForm({
        match_installer_id: null as number | null,
        lead_ids: [] as number[],
        name: '',
        radius_km: undefined as number | undefined,
    });

    const toggleLead = (id: number) => {
        const lead = visibleLeads.find((item) => item.id === id);
        if (!lead?.packagable) {
            return;
        }
        setSelectedLeadIds((current) =>
            current.includes(id)
                ? current.filter((item) => item !== id)
                : [...current, id],
        );
    };

    const selectAllEligible = () => {
        setSelectedLeadIds(packagableLeads.map((lead) => lead.id));
    };

    const clearSelection = () => setSelectedLeadIds([]);

    const openConfirm = () => {
        setPackageName(suggestedPackageName);
        setConfirmOpen(true);
    };

    const submitPackage = () => {
        createForm.setData({
            match_installer_id: nearby?.installer?.id ?? null,
            lead_ids: selectedLeadIds,
            name: packageName || suggestedPackageName,
            radius_km: nearby?.radius_km,
        });
        createForm.post(route('admin.packages.store'), {
            preserveScroll: true,
            onSuccess: () => {
                setConfirmOpen(false);
                setSelectedLeadIds([]);
            },
        });
    };

    const clearMarkers = () => {
        markersRef.current.forEach((marker) => marker.setMap(null));
        markersRef.current = [];
        infoRef.current?.close();
    };

    const focusMarker = useCallback(
        (type: 'lead' | 'installer', id: number) => {
            setSelection({ type, id });
            const marker = markersRef.current.find(
                (item) =>
                    item.get('rmlType') === type && item.get('rmlId') === id,
            );
            const map = mapRef.current;
            if (!marker || !map) {
                return;
            }
            const position = marker.getPosition();
            if (position) {
                map.panTo(position);
                map.setZoom(Math.max(map.getZoom() ?? 8, 12));
            }
            google.maps.event.trigger(marker, 'click');
        },
        [],
    );

    useEffect(() => {
        if (!apiKey || !mapNodeRef.current) {
            return;
        }

        let cancelled = false;

        const boot = async () => {
            try {
                setOptions({ key: apiKey, v: 'weekly' });
                await importLibrary('maps');
                await importLibrary('marker');
                if (cancelled || !mapNodeRef.current) {
                    return;
                }

                mapRef.current = new google.maps.Map(mapNodeRef.current, {
                    center,
                    zoom: 6,
                    mapTypeControl: false,
                    streetViewControl: false,
                    fullscreenControl: true,
                    clickableIcons: false,
                });
                infoRef.current = new google.maps.InfoWindow();
                setReady(true);
                setLoadError(false);
            } catch {
                if (!cancelled) {
                    setLoadError(true);
                    setReady(false);
                }
            }
        };

        void boot();

        return () => {
            cancelled = true;
            clearMarkers();
            mapRef.current = null;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [apiKey]);

    useEffect(() => {
        const map = mapRef.current;
        if (!ready || !map || !apiKey) {
            return;
        }

        clearMarkers();
        infoRef.current?.close();
        setSelection(null);
        circleRef.current?.setMap(null);
        circleRef.current = null;
        const bounds = new google.maps.LatLngBounds();
        let hasPoints = false;

        const leadIcon = {
            url: pinSvg(LEAD_COLOR),
            scaledSize: new google.maps.Size(32, 42),
            anchor: new google.maps.Point(16, 42),
        };
        const installerIcon = {
            url: installerPinSvg(INSTALLER_COLOR),
            scaledSize: new google.maps.Size(34, 46),
            anchor: new google.maps.Point(17, 46),
        };
        const selectedInstallerIcon = {
            url: installerPinSvg('#9A3412'),
            scaledSize: new google.maps.Size(40, 52),
            anchor: new google.maps.Point(20, 52),
        };

        if (matchActive && nearby?.installer) {
            const circle = new google.maps.Circle({
                map,
                center: {
                    lat: nearby.installer.latitude,
                    lng: nearby.installer.longitude,
                },
                radius: nearby.radius_km * 1000,
                strokeColor: INSTALLER_COLOR,
                strokeOpacity: 0.7,
                strokeWeight: 2,
                fillColor: INSTALLER_COLOR,
                fillOpacity: 0.08,
                clickable: false,
            });
            circleRef.current = circle;
            const circleBounds = circle.getBounds();
            if (circleBounds) {
                bounds.union(circleBounds);
                hasPoints = true;
            }
        }

        // Leads first (lower z-index). Installers always layered above so
        // combined mode never “eats” installer pins under lead pins.
        visibleLeads.forEach((lead) => {
            if (
                !Number.isFinite(lead.latitude) ||
                !Number.isFinite(lead.longitude)
            ) {
                return;
            }
            const position = { lat: lead.latitude, lng: lead.longitude };
            const marker = new google.maps.Marker({
                map,
                position,
                title: lead.lead_reference,
                icon: leadIcon,
                zIndex: 2,
                optimized: false,
            });
            marker.set('rmlType', 'lead');
            marker.set('rmlId', lead.id);
            marker.addListener('click', () => {
                setSelection({ type: 'lead', id: lead.id });
                const statusLabel = leadStatusLabel(lead.status, leadStatuses);
                const buyerBlock =
                    tab === 'sold'
                        ? `<div>${escapeHtml(labels.buyer)}: ${escapeHtml(lead.buyer_company ?? lead.buyer_name ?? '—')}</div>
                           <div>${escapeHtml(labels.payment_status)}: ${escapeHtml(
                               paymentStatuses?.[lead.payment_status ?? ''] ??
                                   lead.payment_status ??
                                   '—',
                           )}</div>`
                        : '';
                const distanceBlock =
                    lead.distance_km != null
                        ? `<div style="margin-top:4px;font-weight:600;color:#0F766E">${escapeHtml(
                              labels.distance_from_installer.replace(
                                  ':km',
                                  String(lead.distance_km),
                              ),
                          )}</div>`
                        : '';
                const content = `
                  <div style="max-width:260px;font-family:system-ui,sans-serif;font-size:13px;line-height:1.4">
                    <div style="font-weight:700;margin-bottom:6px">${escapeHtml(lead.lead_reference)}</div>
                    <div>${escapeHtml(labels.scheme)}: ${escapeHtml(lead.scheme ?? '—')}</div>
                    <div>${escapeHtml(labels.zone)}: ${escapeHtml(lead.zone ?? '—')}</div>
                    <div>${escapeHtml(labels.size)}: ${
                        lead.size_m2 != null
                            ? `${escapeHtml(lead.size_m2)} m²`
                            : '—'
                    }</div>
                    <div>${escapeHtml(labels.status)}: ${escapeHtml(statusLabel)}</div>
                    <div>${escapeHtml(labels.seller)}: ${escapeHtml(lead.seller_company ?? '—')}</div>
                    ${buyerBlock}
                    ${distanceBlock}
                    <div style="margin-top:8px">
                      <a href="${escapeHtml(lead.view_url)}" style="color:#0F766E;font-weight:600">${escapeHtml(labels.view_lead)}</a>
                    </div>
                  </div>
                `;
                infoRef.current?.setContent(content);
                infoRef.current?.open({ map, anchor: marker });
            });
            markersRef.current.push(marker);
            bounds.extend(position);
            hasPoints = true;
        });

        visibleInstallers.forEach((installer) => {
            if (
                !Number.isFinite(installer.latitude) ||
                !Number.isFinite(installer.longitude)
            ) {
                return;
            }
            const isSelected = Boolean(
                installer.selected ||
                    (matchActive && nearby?.installer?.id === installer.id),
            );
            const position = {
                lat: installer.latitude,
                lng: installer.longitude,
            };
            const marker = new google.maps.Marker({
                map,
                position,
                title: installer.company_name,
                icon: isSelected ? selectedInstallerIcon : installerIcon,
                // Keep installers above leads in combined mode.
                zIndex: isSelected ? 30 : 20,
                opacity: matchActive && !isSelected ? 0.55 : 1,
                optimized: false,
            });
            marker.set('rmlType', 'installer');
            marker.set('rmlId', installer.id);
            marker.addListener('click', () => {
                setSelection({ type: 'installer', id: installer.id });
                if (tab === 'registered' && onMatchInstaller) {
                    onMatchInstaller(installer.id);
                }
                const statusLabel = approvalStatusLabel(
                    installer.status ?? '',
                    approvalStatuses,
                );
                const viewLink = installer.view_url
                    ? `<div style="margin-top:8px"><a href="${escapeHtml(installer.view_url)}" style="color:#C2410C;font-weight:600">${escapeHtml(labels.view_buyer)}</a></div>`
                    : '';
                const content = `
                  <div style="max-width:260px;font-family:system-ui,sans-serif;font-size:13px;line-height:1.4">
                    <div style="font-weight:700;margin-bottom:6px">${escapeHtml(installer.company_name)}</div>
                    <div>${escapeHtml(labels.base_location)}: ${escapeHtml(installer.base_location ?? '—')}</div>
                    <div>${escapeHtml(labels.status)}: ${escapeHtml(statusLabel)}</div>
                    <div>${escapeHtml(labels.nearby_leads)}: ${escapeHtml(installer.nearby_leads_count)} (≤ ${escapeHtml(installer.nearby_radius_km)} km)</div>
                    ${viewLink}
                  </div>
                `;
                infoRef.current?.setContent(content);
                infoRef.current?.open({ map, anchor: marker });
            });
            markersRef.current.push(marker);
            bounds.extend(position);
            hasPoints = true;
        });

        if (hasPoints) {
            map.fitBounds(bounds, 48);
            const zoom = map.getZoom();
            if (zoom != null && zoom > 14) {
                map.setZoom(14);
            }
        } else {
            map.setCenter(center);
            map.setZoom(6);
        }

        return () => {
            clearMarkers();
            circleRef.current?.setMap(null);
            circleRef.current = null;
        };
    }, [
        ready,
        apiKey,
        tab,
        markerSet,
        visibleLeads,
        visibleInstallers,
        center,
        labels,
        leadStatuses,
        approvalStatuses,
        paymentStatuses,
        matchActive,
        nearby,
        onMatchInstaller,
        tab,
    ]);

    if (!apiKey || loadError) {
        return (
            <div className="rml-card p-6">
                <EmptyState
                    title={labels.not_configured}
                    description={labels.not_configured_help}
                />
            </div>
        );
    }

    const hiddenTotal =
        (showLeads ? hidden.leads : 0) +
        (showInstallers ? hidden.installers : 0);

    const retryGeocode = (url?: string | null) => {
        if (!url) {
            return;
        }
        router.post(url, {}, { preserveScroll: true });
    };

    return (
        <div className="min-w-0 space-y-3 overflow-x-hidden">
            <div className="flex flex-wrap items-center gap-2">
                <Button
                    size="sm"
                    variant={markerSet === 'leads' ? 'primary' : 'outline'}
                    onClick={() => onMarkerSetChange('leads')}
                >
                    {labels.show_leads}
                </Button>
                {canSeeInstallers && (
                    <>
                        <Button
                            size="sm"
                            variant={
                                markerSet === 'installers'
                                    ? 'primary'
                                    : 'outline'
                            }
                            onClick={() => onMarkerSetChange('installers')}
                        >
                            {labels.show_installers}
                        </Button>
                        <Button
                            size="sm"
                            variant={
                                markerSet === 'both' ? 'primary' : 'outline'
                            }
                            onClick={() => onMarkerSetChange('both')}
                        >
                            {labels.show_both}
                        </Button>
                    </>
                )}
                <div className="ml-auto flex flex-wrap items-center gap-3 text-xs text-rml-muted">
                    {showLeads && (
                        <span className="inline-flex items-center gap-1.5">
                            <span
                                className="inline-block h-2.5 w-2.5 rounded-full"
                                style={{ backgroundColor: LEAD_COLOR }}
                            />
                            {labels.show_leads}
                            <span className="tabular-nums text-rml-text">
                                ({visibleLeads.length})
                            </span>
                        </span>
                    )}
                    {showInstallers && (
                        <span className="inline-flex items-center gap-1.5">
                            <span
                                className="inline-block h-2.5 w-2.5 rounded-sm"
                                style={{ backgroundColor: INSTALLER_COLOR }}
                            />
                            {labels.show_installers}
                            <span className="tabular-nums text-rml-text">
                                ({visibleInstallers.length})
                            </span>
                        </span>
                    )}
                </div>
            </div>

            {canSeeInstallers && markerSet === 'both' && (
                <p className="text-xs text-rml-muted">
                    {labels.filters_leads_hint}
                </p>
            )}

            {hiddenTotal > 0 && !matchActive && (
                <p className="text-sm text-rml-muted">
                    {labels.hidden_notice.replace(
                        ':count',
                        String(hiddenTotal),
                    )}
                </p>
            )}

            <div className="grid min-w-0 gap-3 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-stretch">
                <div className="rml-card min-w-0 overflow-hidden">
                    <div
                        ref={mapNodeRef}
                        className="h-[22rem] w-full min-h-[18rem] sm:h-[28rem] lg:h-[36rem]"
                    />
                </div>

                <aside className="rml-card flex max-h-[28rem] min-w-0 flex-col overflow-hidden sm:max-h-[32rem] lg:max-h-[36rem]">
                    {matchActive && nearby?.installer && (
                        <div className="space-y-2 border-b border-rml-border px-4 py-3">
                            <div className="flex items-start justify-between gap-2">
                                <div className="min-w-0 space-y-1 text-xs text-rml-muted">
                                    <p className="text-[11px] font-semibold uppercase tracking-wide text-rml-muted">
                                        {labels.selected_installer ??
                                            'Selected installer'}
                                    </p>
                                    <p className="truncate font-medium text-rml-text">
                                        {nearby.installer.company_name}
                                    </p>
                                    {(nearby.installer.city ||
                                        nearby.installer.base_location) && (
                                        <p className="truncate">
                                            {nearby.installer.city ||
                                                nearby.installer.base_location}
                                        </p>
                                    )}
                                    <p>
                                        {nearby.radius_km} km ·{' '}
                                        {labels.eligible_nearby.replace(
                                            ':count',
                                            String(
                                                nearby.summary.matched_count,
                                            ),
                                        )}
                                    </p>
                                </div>
                                <div className="flex shrink-0 flex-col gap-1">
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => {
                                            if (showInstallers && nearby.installer) {
                                                focusMarker(
                                                    'installer',
                                                    nearby.installer.id,
                                                );
                                                return;
                                            }
                                            router.get(
                                                route('admin.leads.index'),
                                                {
                                                    tab,
                                                    view: 'map',
                                                    marker_set: 'both',
                                                    match_installer_id:
                                                        nearby.installer?.id,
                                                    match_radius_km:
                                                        nearby.radius_km,
                                                },
                                                {
                                                    preserveState: true,
                                                    replace: true,
                                                },
                                            );
                                        }}
                                    >
                                        {labels.change_installer ??
                                            'Change installer'}
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() =>
                                            router.get(
                                                route('admin.leads.index'),
                                                {
                                                    tab,
                                                    view: 'map',
                                                    marker_set: markerSet,
                                                    match_installer_id:
                                                        undefined,
                                                    match_radius_km: undefined,
                                                },
                                                {
                                                    preserveState: false,
                                                    replace: true,
                                                },
                                            )
                                        }
                                    >
                                        {labels.clear_installer ??
                                            'Clear installer'}
                                    </Button>
                                </div>
                            </div>
                        </div>
                    )}

                    {packageMode &&
                        showLeads &&
                        visibleLeads.length > 0 && (
                        <div className="flex flex-wrap gap-2 border-b border-rml-border px-3 py-2">
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={selectAllEligible}
                                disabled={packagableLeads.length === 0}
                            >
                                {labels.select_all_eligible}
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={clearSelection}
                                disabled={selectedLeadIds.length === 0}
                            >
                                {labels.clear_selection}
                            </Button>
                            <span className="ml-auto self-center text-xs text-rml-muted">
                                {labels.selected_leads.replace(
                                    ':count',
                                    String(selectedLeadIds.length),
                                )}
                            </span>
                        </div>
                    )}

                    <div
                        className={cn(
                            'flex-1 overflow-y-auto p-3',
                            combinedMode && 'space-y-3',
                        )}
                    >
                        {showLeads && (
                            <section
                                className={cn(
                                    'space-y-2',
                                    combinedMode &&
                                        'max-h-[48%] overflow-y-auto rounded-lg border border-rml-border bg-rml-background/40 p-2',
                                )}
                            >
                                <div className="flex items-center justify-between gap-2 px-1">
                                    <h3 className="text-sm font-semibold text-rml-text">
                                        {labels.section_leads_for_package ??
                                            labels.select_for_package}
                                    </h3>
                                    <span className="text-xs text-rml-muted">
                                        {visibleLeads.length}
                                    </span>
                                </div>
                                {visibleLeads.length === 0 ? (
                                    <EmptyState
                                        className="!py-6"
                                        title={
                                            matchActive
                                                ? (labels.empty_nearby_title ??
                                                  labels.no_nearby_matches)
                                                : (labels.empty_leads_title ??
                                                  labels.no_leads)
                                        }
                                        description={
                                            matchActive
                                                ? labels.no_nearby_matches
                                                : labels.no_leads
                                        }
                                    />
                                ) : packagableLeads.length === 0 &&
                                  packageMode ? (
                                    <>
                                        <p className="px-1 text-xs text-rml-muted">
                                            {(
                                                labels.visible_eligible_summary ??
                                                ':visible visible · :eligible eligible'
                                            )
                                                .replace(
                                                    ':visible',
                                                    String(visibleLeads.length),
                                                )
                                                .replace(
                                                    ':eligible',
                                                    String(
                                                        packagableLeads.length,
                                                    ),
                                                )}
                                        </p>
                                        <EmptyState
                                            title={
                                                labels.no_eligible_leads ??
                                                'No eligible leads'
                                            }
                                            description={
                                                labels.no_eligible_leads ??
                                                labels.no_leads
                                            }
                                        />
                                        {visibleLeads.map((lead) => {
                                            const reason =
                                                ineligibleReason(lead);
                                            return (
                                                <LeadSideCard
                                                    key={`lead-${lead.id}`}
                                                    lead={lead}
                                                    active={
                                                        selection?.type ===
                                                            'lead' &&
                                                        selection.id === lead.id
                                                    }
                                                    selected={selectedLeadIds.includes(
                                                        lead.id,
                                                    )}
                                                    showCheckbox={packageMode}
                                                    checkboxEnabled={false}
                                                    checkboxDisabledReason={
                                                        reason
                                                    }
                                                    labels={labels}
                                                    leadStatuses={leadStatuses}
                                                    paymentStatuses={
                                                        paymentStatuses
                                                    }
                                                    showBuyerContext={false}
                                                    onFocus={() =>
                                                        focusMarker(
                                                            'lead',
                                                            lead.id,
                                                        )
                                                    }
                                                    onToggleSelect={() =>
                                                        toggleLead(lead.id)
                                                    }
                                                    onRetry={retryGeocode}
                                                />
                                            );
                                        })}
                                    </>
                                ) : (
                                    <>
                                        <p className="px-1 text-xs text-rml-muted">
                                            {(
                                                labels.visible_eligible_summary ??
                                                ':visible visible · :eligible eligible'
                                            )
                                                .replace(
                                                    ':visible',
                                                    String(visibleLeads.length),
                                                )
                                                .replace(
                                                    ':eligible',
                                                    String(
                                                        packagableLeads.length,
                                                    ),
                                                )}
                                        </p>
                                        {visibleLeads.map((lead) => {
                                            const reason = packageMode
                                                ? ineligibleReason(lead)
                                                : null;
                                            return (
                                                <LeadSideCard
                                                    key={`lead-${lead.id}`}
                                                    lead={lead}
                                                    active={
                                                        selection?.type ===
                                                            'lead' &&
                                                        selection.id === lead.id
                                                    }
                                                    selected={selectedLeadIds.includes(
                                                        lead.id,
                                                    )}
                                                    showCheckbox={packageMode}
                                                    checkboxEnabled={Boolean(
                                                        lead.packagable,
                                                    )}
                                                    checkboxDisabledReason={
                                                        reason
                                                    }
                                                    labels={labels}
                                                    leadStatuses={leadStatuses}
                                                    paymentStatuses={
                                                        paymentStatuses
                                                    }
                                                    showBuyerContext={
                                                        tab === 'sold'
                                                    }
                                                    onFocus={() =>
                                                        focusMarker(
                                                            'lead',
                                                            lead.id,
                                                        )
                                                    }
                                                    onToggleSelect={() =>
                                                        toggleLead(lead.id)
                                                    }
                                                    onRetry={retryGeocode}
                                                />
                                            );
                                        })}
                                    </>
                                )}
                            </section>
                        )}

                        {showInstallers && (
                            <section
                                className={cn(
                                    'space-y-2',
                                    combinedMode &&
                                        'max-h-[48%] overflow-y-auto rounded-lg border border-rml-border bg-rml-background/40 p-2',
                                )}
                            >
                                <div className="flex items-center justify-between gap-2 px-1">
                                    <h3 className="text-sm font-semibold text-rml-text">
                                        {labels.section_visible_installers ??
                                            labels.installers_list}
                                    </h3>
                                    <span className="text-xs text-rml-muted">
                                        {visibleInstallers.length}
                                    </span>
                                </div>
                                {visibleInstallers.length === 0 ? (
                                    <EmptyState
                                        title={
                                            labels.empty_installers_title ??
                                            labels.no_installers
                                        }
                                        description={labels.no_installers}
                                    />
                                ) : (
                                    visibleInstallers.map((installer) => (
                                        <InstallerSideCard
                                            key={`installer-${installer.id}`}
                                            installer={installer}
                                            active={
                                                selection?.type ===
                                                    'installer' &&
                                                selection.id === installer.id
                                            }
                                            labels={labels}
                                            approvalStatuses={approvalStatuses}
                                            canMatch={
                                                tab === 'registered' &&
                                                Boolean(onMatchInstaller)
                                            }
                                            onFocus={() => {
                                                focusMarker(
                                                    'installer',
                                                    installer.id,
                                                );
                                            }}
                                            onMatch={() => {
                                                onMatchInstaller?.(
                                                    installer.id,
                                                );
                                            }}
                                            onRetry={retryGeocode}
                                        />
                                    ))
                                )}
                            </section>
                        )}

                        {!showLeads && !showInstallers && (
                            <p className="px-1 py-6 text-center text-sm text-rml-muted">
                                {labels.no_markers}
                            </p>
                        )}
                    </div>

                    {packageMode && showLeads && packageSummary && (
                        <div className="sticky bottom-0 space-y-2 border-t border-rml-border bg-white p-3 text-xs">
                            <p className="font-semibold text-rml-text">
                                {labels.package_summary_title}
                            </p>
                            <p>
                                {nearby?.installer?.company_name
                                    ? `${nearby.installer.company_name} · `
                                    : ''}
                                {labels.selected_leads.replace(
                                    ':count',
                                    String(packageSummary.count),
                                )}
                            </p>
                            <p>
                                {labels.schemes_included}:{' '}
                                {packageSummary.schemes.join(', ') || '—'}
                            </p>
                            <p>
                                {labels.zones_included}:{' '}
                                {packageSummary.zones.join(', ') || '—'}
                            </p>
                            <p>
                                {labels.nearby_total_size.replace(
                                    ':m2',
                                    String(packageSummary.totalSize),
                                )}
                            </p>
                            {packageSummary.distanceMin != null &&
                                packageSummary.distanceMax != null && (
                                    <p>
                                        {labels.distance_range
                                            .replace(
                                                ':min',
                                                String(
                                                    packageSummary.distanceMin,
                                                ),
                                            )
                                            .replace(
                                                ':max',
                                                String(
                                                    packageSummary.distanceMax,
                                                ),
                                            )}
                                    </p>
                                )}
                            <p>
                                {labels.total_seller_payout}:{' '}
                                {formatMoney(packageSummary.totalPayout)}
                            </p>
                            <p>
                                {labels.total_selling_price}:{' '}
                                {formatMoney(packageSummary.totalSelling)}
                            </p>
                            <p>
                                {labels.estimated_margin}:{' '}
                                {formatMoney(packageSummary.margin)}
                            </p>
                            <Button
                                size="sm"
                                variant="primary"
                                className="w-full"
                                onClick={openConfirm}
                            >
                                {labels.create_package}
                            </Button>
                        </div>
                    )}
                </aside>
            </div>

            <Modal
                open={confirmOpen}
                onClose={() => setConfirmOpen(false)}
                title={labels.create_package}
                size="lg"
                footer={
                    <div className="flex justify-end gap-2">
                        <Button
                            variant="ghost"
                            onClick={() => setConfirmOpen(false)}
                        >
                            {labels.cancel}
                        </Button>
                        <Button
                            variant="primary"
                            onClick={submitPackage}
                            disabled={createForm.processing}
                        >
                            {labels.confirm_create_package}
                        </Button>
                    </div>
                }
            >
                <div className="space-y-3 text-sm">
                    <p>
                        <span className="font-medium">
                            {nearby?.installer?.company_name ??
                                labels.manual_package ??
                                'Manual package'}
                        </span>
                    </p>
                    <FormInput
                        label={labels.package_name}
                        value={packageName}
                        onChange={(e) => setPackageName(e.target.value)}
                    />
                    <ul className="max-h-40 space-y-1 overflow-y-auto rounded border border-rml-border p-2 text-xs">
                        {selectedLeads.map((lead) => (
                            <li key={lead.id} className="font-mono">
                                {lead.lead_reference}
                                {lead.scheme ? ` · ${lead.scheme}` : ''}
                                {lead.zone ? ` · ${lead.zone}` : ''}
                                {lead.size_m2 != null
                                    ? ` · ${lead.size_m2} m²`
                                    : ''}
                            </li>
                        ))}
                    </ul>
                    {packageSummary && (
                        <div className="space-y-1 text-xs text-rml-muted">
                            <p>
                                {labels.nearby_total_size.replace(
                                    ':m2',
                                    String(packageSummary.totalSize),
                                )}
                            </p>
                            <p>
                                {labels.total_selling_price}:{' '}
                                {formatMoney(packageSummary.totalSelling)}
                            </p>
                            <p>
                                {labels.estimated_margin}:{' '}
                                {formatMoney(packageSummary.margin)}
                            </p>
                        </div>
                    )}
                    {createForm.errors.lead_ids && (
                        <p className="text-sm text-red-600">
                            {createForm.errors.lead_ids}
                        </p>
                    )}
                </div>
            </Modal>
        </div>
    );
}
