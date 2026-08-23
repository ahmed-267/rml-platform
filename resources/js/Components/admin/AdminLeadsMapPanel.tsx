import { useEffect, useMemo, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { FilterX } from 'lucide-react';
import {
    AdminLeadsMap,
    type MapInstallerMarker,
    type MapLeadMarker,
    type MarkerSet,
    type NearbyMatchPayload,
} from '@/Components/admin/AdminLeadsMap';
import {
    Button,
    FilterBar,
    FormInput,
    MobileFilterDrawer,
    Select,
} from '@/Components/ui';
import { useInstantListFilters } from '@/hooks/use-instant-list-filters';
import { useIsMobile } from '@/hooks/use-media-query';
import { leadStatusLabel } from '@/lib/lead-status';
import type { PageProps } from '@/types';

type MatchInstallerOption = {
    id: number;
    name: string;
    city?: string | null;
    base_location?: string | null;
    matched_count?: number;
};

export type AdminMapPayload = {
    center: { lat: number; lng: number };
    default_radius_km?: number;
    radius_options_km?: number[];
    can_see_installers: boolean;
    show_installer_filter?: boolean;
    show_nearby_match?: boolean;
    can_manage_packages?: boolean;
    nearby?: NearbyMatchPayload | null;
    leads: MapLeadMarker[];
    installers: MapInstallerMarker[];
    hidden: { leads: number; installers: number };
    installer_options: MatchInstallerOption[];
    filters: {
        status?: string | null;
        scheme_id?: string | number | null;
        zone_code?: string | null;
        search?: string | null;
        installer_id?: string | number | null;
        match_installer_id?: string | number | null;
        radius_km?: string | number | null;
        payment_status?: string | null;
        release_status?: string | null;
        sold_from?: string | null;
        sold_to?: string | null;
        marker_set?: string | null;
    };
};

type Props = {
    tab: 'registered' | 'sold';
    map: AdminMapPayload;
    filterOptions: {
        statuses: string[];
        schemes: Array<{ id: number; name: string }>;
        zones: Array<{ id: number; code: string; name: string }>;
        installers?: Array<{ id: number; name: string }>;
        match_installers?: MatchInstallerOption[];
        radius_options_km?: number[];
        payment_statuses?: string[];
        release_statuses?: string[];
    };
};

export function AdminLeadsMapPanel({ tab, map, filterOptions }: Props) {
    const { translations } = usePage<PageProps>().props;
    const t = translations.admin.leads;
    const bought = translations.admin.leads_bought;
    const common = translations.admin.common;
    const locationT = translations.location;
    const leadStatuses = translations.lead_statuses;
    const statuses = translations.statuses;
    const paymentStatusLabels = translations.payment_statuses;
    const sold = translations.admin.leads_sold;
    const isMobile = useIsMobile();
    const isSold = tab === 'sold';
    const showNearbyMatch =
        !isSold && (map.show_nearby_match ?? map.can_see_installers);

    const radiusOptions =
        filterOptions.radius_options_km ??
        map.radius_options_km ??
        [10, 25, 50, 100, 200];
    const defaultRadius = String(
        map.filters.radius_km ??
            map.default_radius_km ??
            radiusOptions[2] ??
            50,
    );

    const [search, setSearch] = useState(map.filters.search ?? '');
    const [status, setStatus] = useState(map.filters.status ?? '');
    const [schemeId, setSchemeId] = useState(
        map.filters.scheme_id != null ? String(map.filters.scheme_id) : '',
    );
    const [zoneCode, setZoneCode] = useState(map.filters.zone_code ?? '');
    const [installerId, setInstallerId] = useState(
        map.filters.installer_id != null
            ? String(map.filters.installer_id)
            : '',
    );
    const [matchInstallerId, setMatchInstallerId] = useState(
        map.filters.match_installer_id != null
            ? String(map.filters.match_installer_id)
            : '',
    );
    const [radiusKm, setRadiusKm] = useState(defaultRadius);
    const [paymentStatus, setPaymentStatus] = useState(
        map.filters.payment_status ?? '',
    );
    const [releaseStatus, setReleaseStatus] = useState(
        map.filters.release_status ?? '',
    );
    const [soldFrom, setSoldFrom] = useState(map.filters.sold_from ?? '');
    const [soldTo, setSoldTo] = useState(map.filters.sold_to ?? '');
    const [markerSet, setMarkerSet] = useState<MarkerSet>(() => {
        const value = map.filters.marker_set;
        if (value === 'leads' || value === 'installers' || value === 'both') {
            return value;
        }
        return 'both';
    });
    const [filtersOpen, setFiltersOpen] = useState(false);

    useEffect(() => {
        const value = map.filters.marker_set;
        if (value === 'leads' || value === 'installers' || value === 'both') {
            setMarkerSet(value);
        }
    }, [map.filters.marker_set]);

    const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY as
        | string
        | undefined;

    const matchInstallers =
        filterOptions.match_installers ??
        (showNearbyMatch ? map.installer_options : []);

    const queryParams = (
        overrides: Record<string, string | number | undefined> = {},
    ) => {
        const base: Record<string, string | number | undefined> = {
            tab,
            view: 'map',
            search: search || undefined,
            scheme_id: schemeId || undefined,
            marker_set: markerSet,
            ...overrides,
        };

        if (isSold) {
            base.installer_id = installerId || undefined;
            base.payment_status = paymentStatus || undefined;
            base.release_status = releaseStatus || undefined;
            base.sold_from = soldFrom || undefined;
            base.sold_to = soldTo || undefined;
        } else {
            base.status = status || undefined;
            base.zone_code = zoneCode || undefined;
            if (showNearbyMatch) {
                base.match_installer_id = matchInstallerId || undefined;
                base.radius_km = radiusKm || undefined;
            }
        }

        return base;
    };

    const applyFilters = () => {
        router.get(route('admin.leads.index'), queryParams(), {
            preserveState: true,
            replace: true,
        });
    };

    useInstantListFilters(
        applyFilters,
        search,
        isSold
            ? [
                  schemeId,
                  installerId,
                  paymentStatus,
                  releaseStatus,
                  soldFrom,
                  soldTo,
              ]
            : [status, schemeId, zoneCode, matchInstallerId, radiusKm],
    );

    const handleMarkerSetChange = (value: MarkerSet) => {
        setMarkerSet(value);
        router.get(
            route('admin.leads.index'),
            queryParams({ marker_set: value }),
            { preserveState: true, replace: true },
        );
    };

    const resetFilters = () => {
        setSearch('');
        setStatus('');
        setSchemeId('');
        setZoneCode('');
        setInstallerId('');
        setMatchInstallerId('');
        setRadiusKm(String(map.default_radius_km ?? 50));
        setPaymentStatus('');
        setReleaseStatus('');
        setSoldFrom('');
        setSoldTo('');
        setMarkerSet('both');
        router.get(
            route('admin.leads.index'),
            {
                tab,
                view: 'map',
                marker_set: 'both',
                ...(showNearbyMatch
                    ? { radius_km: map.default_radius_km ?? 50 }
                    : {}),
            },
            { preserveState: true, replace: true },
        );
    };

    const clearInstallerMatch = () => {
        setMatchInstallerId('');
        setRadiusKm(String(map.default_radius_km ?? 50));
        router.get(
            route('admin.leads.index'),
            queryParams({
                match_installer_id: undefined,
                radius_km: map.default_radius_km ?? 50,
            }),
            { preserveState: true, replace: true },
        );
    };

    const handleMatchInstaller = (installerId: number) => {
        setMatchInstallerId(String(installerId));
    };

    const switchToTable = () => {
        router.get(
            route('admin.leads.index'),
            {
                tab,
                view: 'table',
                search: search || undefined,
                scheme_id: schemeId || undefined,
                ...(isSold
                    ? {}
                    : {
                          status: status || undefined,
                          zone_code: zoneCode || undefined,
                          match_installer_id: matchInstallerId || undefined,
                          radius_km: matchInstallerId
                              ? radiusKm || undefined
                              : undefined,
                      }),
            },
            { preserveState: false, replace: true },
        );
    };

    const mapLabels = useMemo(
        () => ({
            not_configured: t.map_not_configured ?? '',
            not_configured_help: t.map_not_configured_help ?? '',
            show_leads: t.map_show_leads ?? 'Leads',
            show_installers: t.map_show_installers ?? 'Installers',
            show_both: t.map_show_both ?? 'Leads + Installers',
            leads_list: t.map_leads_list ?? '',
            installers_list: t.map_installers_list ?? '',
            no_leads: t.map_no_leads ?? '',
            no_installers: t.map_no_installers ?? '',
            no_markers: t.map_no_markers ?? '',
            no_nearby_matches:
                t.map_no_nearby_matches ??
                'No eligible leads found within this radius.',
            hidden_notice: t.map_hidden_notice ?? ':count',
            filters_leads_hint: isSold
                ? (t.map_filters_sold_hint ?? '')
                : (t.map_filters_registered_hint ??
                  t.map_filters_leads_hint ??
                  ''),
            view_lead: t.map_view_lead ?? '',
            view_buyer: t.map_view_buyer ?? '',
            retry_geocoding: locationT?.retry ?? '',
            edit_location: locationT?.edit_coordinates ?? '',
            nearby_leads: t.map_nearby_leads ?? '',
            nearby_panel_title: t.map_nearby_panel_title ?? 'Nearby leads',
            nearby_avg_distance:
                t.map_nearby_avg_distance ?? 'Avg distance: :km km',
            nearby_total_size: t.map_nearby_total_size ?? 'Total size: :m2 m²',
            distance_from_installer:
                t.map_distance_from_installer ??
                'Distance from installer: :km km',
            select_all_eligible:
                t.map_select_all_eligible ?? 'Select all eligible',
            clear_selection: t.map_clear_selection ?? 'Clear selection',
            create_package: t.map_create_package ?? 'Create package',
            package_summary_title:
                t.map_package_summary_title ?? 'Package summary',
            selected_leads: t.map_selected_leads ?? ':count selected',
            schemes_included: t.map_schemes_included ?? 'Schemes',
            zones_included: t.map_zones_included ?? 'Zones',
            distance_range: t.map_distance_range ?? ':min – :max km',
            total_seller_payout:
                t.map_total_seller_payout ?? 'Seller payout',
            total_selling_price:
                t.map_total_selling_price ?? 'Selling price',
            estimated_margin: t.map_estimated_margin ?? 'Estimated margin',
            package_name: t.map_package_name ?? 'Package name',
            confirm_create_package:
                t.map_confirm_create_package ?? 'Confirm create package',
            manual_package:
                t.map_manual_package ?? 'Manual package (no installer yet)',
            cancel: common.cancel ?? 'Cancel',
            eligible_nearby:
                t.map_eligible_nearby ?? ':count eligible leads',
            select_for_package:
                t.map_select_for_package ?? 'Select leads for package',
            section_leads_for_package:
                t.map_section_leads_for_package ??
                t.map_select_for_package ??
                'Select leads for package',
            section_visible_installers:
                t.map_section_visible_installers ??
                t.map_installers_list ??
                'Visible installers',
            selected_installer:
                t.map_selected_installer ?? 'Selected installer',
            clear_installer:
                t.map_clear_installer ??
                t.map_clear_installer_match ??
                'Clear installer',
            change_installer:
                t.map_change_installer ?? 'Change installer',
            visible_eligible_summary:
                t.map_visible_eligible_summary ??
                ':visible visible · :eligible eligible',
            no_eligible_leads:
                t.map_no_eligible_leads ??
                'Visible leads are not currently eligible for packaging.',
            match_nearby:
                t.map_match_nearby ?? 'Match nearby leads',
            empty_leads_title:
                t.map_empty_leads_title ?? 'No leads visible',
            empty_installers_title:
                t.map_empty_installers_title ?? 'No installers visible',
            empty_nearby_title:
                t.map_empty_nearby_title ??
                'Installer has no nearby eligible leads',
            ineligible_reasons: {
                in_package:
                    t.map_ineligible_in_package ??
                    'Already in an active package',
                sold: t.map_ineligible_sold ?? 'Lead is sold',
                rejected: t.map_ineligible_rejected ?? 'Lead is rejected',
                cancelled: t.map_ineligible_cancelled ?? 'Lead is cancelled',
                needs_information:
                    t.map_ineligible_needs_information ??
                    'Needs more information',
                pending_review:
                    t.map_ineligible_pending_review ??
                    'Pending review / not listed yet',
                not_listed:
                    t.map_ineligible_not_listed ??
                    'Only listed / approved leads can be packaged',
            },
            scheme: common.scheme,
            zone: common.zone,
            size: t.map_size ?? 'Size',
            status: common.status,
            seller: bought.seller_company ?? bought.seller,
            buyer: sold.buyer ?? common.company,
            payment_status: sold.payment_status ?? common.status,
            company: common.company,
            base_location: t.map_base_location ?? '',
            focus: t.map_focus ?? '',
        }),
        [t, locationT, common, bought, sold, isSold],
    );

    const statusOptions = !isSold ? (filterOptions.statuses ?? []) : [];
    const zoneOptions = !isSold ? (filterOptions.zones ?? []) : [];
    const showInstallerFilter =
        isSold &&
        (map.show_installer_filter ?? map.can_see_installers) &&
        (filterOptions.installers ?? map.installer_options).length > 0;
    const paymentOptions = isSold
        ? (filterOptions.payment_statuses ?? [])
        : [];
    const releaseOptions = isSold
        ? (filterOptions.release_statuses ?? [])
        : [];

    const releaseLabel = (value: string) => {
        if (value === 'released') {
            return sold.release_released ?? value;
        }
        if (value === 'pending_release') {
            return sold.release_pending ?? value;
        }
        return value;
    };

    const matchInstallerLabel = (installer: MatchInstallerOption) => {
        const place = installer.city || installer.base_location;
        const count =
            installer.matched_count != null
                ? ` · ${installer.matched_count}`
                : '';
        return place
            ? `${installer.name} — ${place}${count}`
            : `${installer.name}${count}`;
    };

    const filterControls = (
        <>
            {statusOptions.length > 0 && (
                <div className="w-full sm:w-[12.5rem]">
                    <Select
                        label={bought.filter_status}
                        value={status}
                        onChange={(e) => setStatus(e.target.value)}
                        options={[
                            { label: common.all_statuses, value: '' },
                            ...statusOptions.map((s) => ({
                                label: leadStatusLabel(s, leadStatuses),
                                value: s,
                            })),
                        ]}
                    />
                </div>
            )}
            <div className="w-full sm:w-[12.5rem]">
                <Select
                    label={bought.filter_scheme}
                    value={schemeId}
                    onChange={(e) => setSchemeId(e.target.value)}
                    options={[
                        { label: bought.all_schemes, value: '' },
                        ...filterOptions.schemes.map((scheme) => ({
                            label: scheme.name,
                            value: String(scheme.id),
                        })),
                    ]}
                />
            </div>
            {zoneOptions.length > 0 && (
                <div className="w-full sm:w-[12.5rem]">
                    <Select
                        label={bought.filter_zone}
                        value={zoneCode}
                        onChange={(e) => setZoneCode(e.target.value)}
                        options={[
                            { label: bought.all_zones, value: '' },
                            ...zoneOptions.map((zone) => ({
                                label: `${zone.code} — ${zone.name}`,
                                value: zone.code,
                            })),
                        ]}
                    />
                </div>
            )}
            {showNearbyMatch && matchInstallers.length > 0 && (
                <>
                    <div className="w-full sm:w-[18rem]">
                        <Select
                            label={t.map_match_installer ?? 'Installer'}
                            value={matchInstallerId}
                            onChange={(e) =>
                                setMatchInstallerId(e.target.value)
                            }
                            options={[
                                {
                                    label:
                                        t.map_match_installer_placeholder ??
                                        'Select installer',
                                    value: '',
                                },
                                ...matchInstallers.map((installer) => ({
                                    label: matchInstallerLabel(installer),
                                    value: String(installer.id),
                                })),
                            ]}
                        />
                    </div>
                    <div className="w-full sm:w-[10rem]">
                        <Select
                            label={t.map_match_radius ?? 'Radius'}
                            value={radiusKm}
                            onChange={(e) => setRadiusKm(e.target.value)}
                            options={radiusOptions.map((km) => ({
                                label: `${km} km`,
                                value: String(km),
                            }))}
                        />
                    </div>
                    {matchInstallerId && (
                        <div className="flex items-end">
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={clearInstallerMatch}
                                className="inline-flex items-center gap-1.5"
                            >
                                <FilterX className="h-4 w-4" aria-hidden />
                                {t.map_clear_installer_match ??
                                    'Clear installer match'}
                            </Button>
                        </div>
                    )}
                </>
            )}
            {showInstallerFilter && (
                <div className="w-full sm:w-[14rem]">
                    <Select
                        label={t.map_filter_installer ?? ''}
                        value={installerId}
                        onChange={(e) => setInstallerId(e.target.value)}
                        options={[
                            {
                                label: t.map_all_installers ?? '',
                                value: '',
                            },
                            ...(filterOptions.installers ??
                                map.installer_options
                            ).map((installer) => ({
                                label: installer.name,
                                value: String(installer.id),
                            })),
                        ]}
                    />
                </div>
            )}
            {paymentOptions.length > 0 && (
                <div className="w-full sm:w-[12.5rem]">
                    <Select
                        label={sold.payment_status}
                        value={paymentStatus}
                        onChange={(e) => setPaymentStatus(e.target.value)}
                        options={[
                            {
                                label: common.all_payment_statuses,
                                value: '',
                            },
                            ...paymentOptions.map((s) => ({
                                label: paymentStatusLabels[s] ?? s,
                                value: s,
                            })),
                        ]}
                    />
                </div>
            )}
            {releaseOptions.length > 0 && (
                <div className="w-full sm:w-[12.5rem]">
                    <Select
                        label={sold.release_status}
                        value={releaseStatus}
                        onChange={(e) => setReleaseStatus(e.target.value)}
                        options={[
                            {
                                label: common.all_release_statuses,
                                value: '',
                            },
                            ...releaseOptions.map((s) => ({
                                label: releaseLabel(s),
                                value: s,
                            })),
                        ]}
                    />
                </div>
            )}
            {isSold && (
                <>
                    <div className="w-full sm:w-[11rem]">
                        <FormInput
                            type="date"
                            label={t.map_sold_from ?? sold.sold_date}
                            value={soldFrom}
                            onChange={(e) => setSoldFrom(e.target.value)}
                        />
                    </div>
                    <div className="w-full sm:w-[11rem]">
                        <FormInput
                            type="date"
                            label={t.map_sold_to ?? sold.sold_date}
                            value={soldTo}
                            onChange={(e) => setSoldTo(e.target.value)}
                        />
                    </div>
                </>
            )}
        </>
    );

    return (
        <div className="min-w-0 space-y-4 overflow-x-hidden">
            <FilterBar
                compact
                search={search}
                onSearchChange={setSearch}
                searchLabel={common.search}
                searchPlaceholder={
                    isSold
                        ? sold.search_placeholder
                        : bought.search_placeholder
                }
                onOpenMobileFilters={() => setFiltersOpen(true)}
                actions={
                    <Button size="sm" variant="ghost" onClick={resetFilters}>
                        {common.reset}
                    </Button>
                }
                endActions={
                    <div className="flex gap-2">
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={switchToTable}
                        >
                            {t.view_table ?? ''}
                        </Button>
                        <Button size="sm" variant="primary">
                            {t.view_map ?? ''}
                        </Button>
                    </div>
                }
            >
                {!isMobile && filterControls}
            </FilterBar>

            <MobileFilterDrawer
                open={filtersOpen}
                onClose={() => setFiltersOpen(false)}
                title={common.filters}
                onApply={() => {
                    setFiltersOpen(false);
                    applyFilters();
                }}
                onReset={resetFilters}
            >
                {filterControls}
            </MobileFilterDrawer>

            <AdminLeadsMap
                apiKey={apiKey?.trim() || undefined}
                center={map.center}
                tab={tab}
                leads={map.leads ?? []}
                installers={map.installers ?? []}
                hidden={map.hidden}
                canSeeInstallers={map.can_see_installers}
                canManagePackages={Boolean(map.can_manage_packages)}
                markerSet={
                    map.can_see_installers ? markerSet : 'leads'
                }
                onMarkerSetChange={handleMarkerSetChange}
                nearby={map.nearby}
                onMatchInstaller={
                    showNearbyMatch ? handleMatchInstaller : undefined
                }
                labels={mapLabels}
                leadStatuses={leadStatuses}
                approvalStatuses={statuses}
                paymentStatuses={paymentStatusLabels}
            />
        </div>
    );
}
