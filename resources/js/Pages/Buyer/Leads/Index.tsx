import { useMemo, useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import AppLayout from '@/Layouts/AppLayout';
import { Search } from 'lucide-react';
import {
    Alert,
    Button,
    Checkbox,
    DataTable,
    EmptyState,
    MobileCardList,
    MobileFilterDrawer,
    Modal,
    Pagination,
    PaymentSummaryBar,
    RangeInput,
    Select,
    SortableHeader,
    TableActionButton,
    tableActionIcons,
} from '@/Components/ui';
import type { SortDirection } from '@/Components/ui/SortableHeader';
import {
    nextSortDirection,
    paginationLabels,
    paginationMeta,
    resolveSortDirection,
} from '@/lib/list-helpers';
import { useIsMobile } from '@/hooks/use-media-query';
import {
    useBuyerLeadsQuery,
    type BuyerLeadsFilters,
    type BuyerLeadsPayload,
} from '@/hooks/use-buyer-leads-query';
import { useInstantListFilters } from '@/hooks/use-instant-list-filters';
import type { PageProps } from '@/types';

function formatMoney(value: number | null | undefined): string {
    if (value == null) {
        return '—';
    }

    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'EUR',
        maximumFractionDigits: 2,
    }).format(value);
}

export default function BuyerLeadsIndex(props: BuyerLeadsPayload) {
    const { translations } = usePage<PageProps>().props;
    const t = translations.buyer?.leads ?? {};
    const common = translations.buyer?.common ?? {};
    const summaryBar = translations.buyer?.summary_bar ?? {};
    const paymentMethods = translations.payment_methods ?? {};
    const paymentsT = translations.buyer?.payments ?? {};
    const isMobile = useIsMobile();

    const initialApplied: BuyerLeadsFilters = {
        search: props.filters.search ?? '',
        scheme_id: props.filters.scheme_id ?? '',
        zone_id: props.filters.zone_id ?? '',
        min_size: props.filters.min_size ?? '',
        max_size: props.filters.max_size ?? '',
        min_distance: props.filters.min_distance ?? '',
        max_distance: props.filters.max_distance ?? '',
        min_price: props.filters.min_price ?? '',
        max_price: props.filters.max_price ?? '',
        sort: props.filters.sort ?? 'date',
        direction: resolveSortDirection(props.filters.direction),
        per_page: Number(props.filters.per_page ?? props.leads.per_page ?? 10),
        page: props.leads.current_page ?? 1,
    };

    const [search, setSearch] = useState(String(initialApplied.search ?? ''));
    const [schemeId, setSchemeId] = useState(
        initialApplied.scheme_id != null && initialApplied.scheme_id !== ''
            ? String(initialApplied.scheme_id)
            : '',
    );
    const [zoneId, setZoneId] = useState(
        initialApplied.zone_id != null && initialApplied.zone_id !== ''
            ? String(initialApplied.zone_id)
            : '',
    );
    const [minSize, setMinSize] = useState(
        initialApplied.min_size != null && initialApplied.min_size !== ''
            ? String(initialApplied.min_size)
            : '',
    );
    const [maxSize, setMaxSize] = useState(
        initialApplied.max_size != null && initialApplied.max_size !== ''
            ? String(initialApplied.max_size)
            : '',
    );
    const [minDistance, setMinDistance] = useState(
        initialApplied.min_distance != null &&
            initialApplied.min_distance !== ''
            ? String(initialApplied.min_distance)
            : '',
    );
    const [maxDistance, setMaxDistance] = useState(
        initialApplied.max_distance != null &&
            initialApplied.max_distance !== ''
            ? String(initialApplied.max_distance)
            : '',
    );
    const [minPrice, setMinPrice] = useState(
        initialApplied.min_price != null && initialApplied.min_price !== ''
            ? String(initialApplied.min_price)
            : '',
    );
    const [maxPrice, setMaxPrice] = useState(
        initialApplied.max_price != null && initialApplied.max_price !== ''
            ? String(initialApplied.max_price)
            : '',
    );
    const [applied, setApplied] = useState<BuyerLeadsFilters>(initialApplied);
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [paymentOpen, setPaymentOpen] = useState(false);
    const [pendingLeadIds, setPendingLeadIds] = useState<number[]>([]);
    const [filtersOpen, setFiltersOpen] = useState(false);

    const queryClient = useQueryClient();
    const { data, isFetching, isError } = useBuyerLeadsQuery(applied, props);

    const leads = data?.leads ?? props.leads;
    const filterOptions = data?.filterOptions ?? props.filterOptions;
    const cardConfigured =
        data?.card_configured ??
        data?.mollie_configured ??
        props.card_configured ??
        props.mollie_configured ??
        true;

    const currentSort = String(applied.sort ?? 'date');
    const currentDirection: SortDirection = resolveSortDirection(
        applied.direction,
    );
    const { pageCount } = paginationMeta(leads);
    const currentPerPage = Number(applied.per_page ?? 10);
    const page = Number(applied.page ?? 1);

    const {
        data: paymentData,
        setData: setPaymentData,
        post: postPurchase,
        processing,
        errors: paymentErrors,
        reset: resetPayment,
    } = useForm({
        lead_ids: [] as number[],
        payment_method: cardConfigured ? 'card' : 'manual_bank_transfer',
    });

    const draftFilters = (
        overrides: Partial<BuyerLeadsFilters> = {},
    ): BuyerLeadsFilters => ({
        search,
        scheme_id: schemeId,
        zone_id: zoneId,
        min_size: minSize,
        max_size: maxSize,
        min_distance: minDistance,
        max_distance: maxDistance,
        min_price: minPrice,
        max_price: maxPrice,
        sort: currentSort,
        direction: currentDirection,
        per_page: currentPerPage,
        page: 1,
        ...overrides,
    });

    const applyFilters = (overrides: Partial<BuyerLeadsFilters> = {}) => {
        setApplied(draftFilters(overrides));
    };

    useInstantListFilters(
        () => applyFilters(),
        search,
        [
            schemeId,
            zoneId,
            minSize,
            maxSize,
            minDistance,
            maxDistance,
            minPrice,
            maxPrice,
        ],
    );

    const resetFilters = () => {
        setSearch('');
        setSchemeId('');
        setZoneId('');
        setMinSize('');
        setMaxSize('');
        setMinDistance('');
        setMaxDistance('');
        setMinPrice('');
        setMaxPrice('');
        setApplied({
            search: '',
            scheme_id: '',
            zone_id: '',
            min_size: '',
            max_size: '',
            min_distance: '',
            max_distance: '',
            min_price: '',
            max_price: '',
            sort: 'date',
            direction: 'desc',
            per_page: currentPerPage,
            page: 1,
        });
    };

    const handleSort = (column: string) => {
        setApplied(
            draftFilters({
                sort: column,
                direction: nextSortDirection(
                    currentSort,
                    column,
                    currentDirection,
                ),
                page: 1,
            }),
        );
    };

    const sortableHeader = (label: string, column: string) => (
        <SortableHeader
            label={label}
            column={column}
            currentSort={currentSort}
            currentDirection={currentDirection}
            onSort={handleSort}
            sortAscLabel={common.sort_asc}
            sortDescLabel={common.sort_desc}
        />
    );

    const toggleLead = (id: number) => {
        setSelectedIds((current) =>
            current.includes(id)
                ? current.filter((value) => value !== id)
                : [...current, id],
        );
    };

    const selectionTotals = useMemo(() => {
        const selected = leads.data.filter((lead) =>
            selectedIds.includes(lead.id),
        );

        return {
            count: selected.length,
            size: selected.reduce(
                (sum, lead) => sum + (lead.size_m2 ?? 0),
                0,
            ),
            price: selected.reduce(
                (sum, lead) => sum + (lead.total_price ?? 0),
                0,
            ),
        };
    }, [leads.data, selectedIds]);

    const openPayment = (leadIds: number[]) => {
        setPendingLeadIds(leadIds);
        setPaymentData('lead_ids', leadIds);
        setPaymentData(
            'payment_method',
            cardConfigured ? 'card' : 'manual_bank_transfer',
        );
        setPaymentOpen(true);
    };

    const submitPurchase = () => {
        if (processing) {
            return;
        }

        postPurchase(route('buyer.leads.purchase'), {
            preserveScroll: true,
            onSuccess: () => {
                setPaymentOpen(false);
                setSelectedIds([]);
                resetPayment();
                void queryClient.invalidateQueries({ queryKey: ['buyer', 'leads'] });
                void queryClient.invalidateQueries({ queryKey: ['buyer', 'dashboard'] });
            },
            onError: () => {
                // Keep modal open so validation / Stripe errors are visible.
            },
        });
    };

    const zoneOptions = filterOptions.zones.filter(
        (zone) => !schemeId || String(zone.scheme_id) === schemeId,
    );

    const paymentMethodOptions = [
        { value: 'card', label: paymentMethods.card ?? 'card' },
        {
            value: 'manual_bank_transfer',
            label: paymentMethods.manual_bank_transfer ?? 'manual_bank_transfer',
        },
    ];

    const summaryLabels = {
        selected: summaryBar.selected,
        leads: summaryBar.leads,
        totalSize: summaryBar.total_size,
        total: summaryBar.total,
        clear: summaryBar.clear ?? common.clear,
        payNow: summaryBar.pay_now ?? common.pay_now,
    };

    return (
        <AppLayout title={t.title} subtitle={t.subtitle}>
            <Head title={t.title} />

            <Alert variant="info">{t.hidden_notice}</Alert>
            {isError && (
                <Alert variant="error">
                    {common.load_error ??
                        'Could not refresh leads. Showing last results.'}
                </Alert>
            )}

            <section className="rounded-xl border border-rml-border bg-white p-3 shadow-sm">
                <div className="flex flex-col gap-2.5 lg:gap-3">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
                        <div className="relative min-w-0 flex-1">
                            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-rml-muted" />
                            <input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder={t.search_placeholder}
                                aria-label={common.search}
                                className="block w-full rounded-lg border border-rml-border bg-white py-2 pl-9 pr-3 text-sm text-rml-text shadow-sm placeholder:text-rml-muted/70 rml-focus-ring focus:border-rml-primary"
                            />
                        </div>
                        <div className="flex shrink-0 items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                className="lg:hidden"
                                onClick={() => setFiltersOpen(true)}
                            >
                                {common.filters}
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={resetFilters}
                            >
                                {common.reset}
                            </Button>
                        </div>
                    </div>

                    <div className="hidden items-end gap-2 lg:grid lg:grid-cols-5">
                        <Select
                            label={common.scheme}
                            aria-label={common.scheme}
                            value={schemeId}
                            onChange={(e) => {
                                setSchemeId(e.target.value);
                                setZoneId('');
                            }}
                            options={[
                                {
                                    value: '',
                                    label: common.all_schemes ?? common.all,
                                },
                                ...filterOptions.schemes.map((scheme) => ({
                                    value: String(scheme.id),
                                    label: scheme.name,
                                })),
                            ]}
                        />
                        <Select
                            label={common.zone}
                            aria-label={common.zone}
                            value={zoneId}
                            onChange={(e) => setZoneId(e.target.value)}
                            options={[
                                {
                                    value: '',
                                    label: common.all_zones ?? common.all,
                                },
                                ...Array.from(
                                    new Map(
                                        zoneOptions.map((zone) => [
                                            zone.code,
                                            zone,
                                        ]),
                                    ).values(),
                                ).map((zone) => ({
                                    value: String(zone.id),
                                    label: zone.code,
                                })),
                            ]}
                        />
                        <RangeInput
                            label={common.size}
                            unit="m²"
                            minName="min_size"
                            maxName="max_size"
                            minValue={minSize}
                            maxValue={maxSize}
                            onMinChange={setMinSize}
                            onMaxChange={setMaxSize}
                        />
                        <RangeInput
                            label={common.distance}
                            unit="km"
                            minName="min_distance"
                            maxName="max_distance"
                            minValue={minDistance}
                            maxValue={maxDistance}
                            onMinChange={setMinDistance}
                            onMaxChange={setMaxDistance}
                        />
                        <RangeInput
                            label={common.price}
                            unit="€"
                            minName="min_price"
                            maxName="max_price"
                            minValue={minPrice}
                            maxValue={maxPrice}
                            onMinChange={setMinPrice}
                            onMaxChange={setMaxPrice}
                        />
                    </div>
                </div>
            </section>

            <MobileFilterDrawer
                open={filtersOpen}
                onClose={() => setFiltersOpen(false)}
                onApply={() => applyFilters()}
                onReset={resetFilters}
            >
                <Select
                    label={common.scheme}
                    aria-label={common.scheme}
                    value={schemeId}
                    onChange={(e) => {
                        setSchemeId(e.target.value);
                        setZoneId('');
                    }}
                    options={[
                        {
                            value: '',
                            label: common.all_schemes ?? common.all,
                        },
                        ...filterOptions.schemes.map((scheme) => ({
                            value: String(scheme.id),
                            label: scheme.name,
                        })),
                    ]}
                />
                <Select
                    label={common.zone}
                    aria-label={common.zone}
                    value={zoneId}
                    onChange={(e) => setZoneId(e.target.value)}
                    options={[
                        {
                            value: '',
                            label: common.all_zones ?? common.all,
                        },
                        ...Array.from(
                            new Map(
                                zoneOptions.map((zone) => [zone.code, zone]),
                            ).values(),
                        ).map((zone) => ({
                            value: String(zone.id),
                            label: zone.code,
                        })),
                    ]}
                />
                <RangeInput
                    label={common.size}
                    unit="m²"
                    minName="min_size"
                    maxName="max_size"
                    minValue={minSize}
                    maxValue={maxSize}
                    onMinChange={setMinSize}
                    onMaxChange={setMaxSize}
                />
                <RangeInput
                    label={common.distance}
                    unit="km"
                    minName="min_distance"
                    maxName="max_distance"
                    minValue={minDistance}
                    maxValue={maxDistance}
                    onMinChange={setMinDistance}
                    onMaxChange={setMaxDistance}
                />
                <RangeInput
                    label={common.price}
                    unit="€"
                    minName="min_price"
                    maxName="max_price"
                    minValue={minPrice}
                    maxValue={maxPrice}
                    onMinChange={setMinPrice}
                    onMaxChange={setMaxPrice}
                />
            </MobileFilterDrawer>

            <div
                className={`${isMobile && selectedIds.length > 0 ? 'pb-28' : ''} ${isFetching ? 'opacity-60 transition-opacity' : ''}`}
            >
                {leads.data.length === 0 ? (
                    <EmptyState title={t.empty ?? common.empty} />
                ) : isMobile ? (
                    <MobileCardList
                        emptyMessage={t.empty ?? common.empty}
                        items={leads.data.map((lead) => ({
                            id: String(lead.id),
                            title: (
                                <span className="font-mono text-sm">
                                    {lead.lead_reference}
                                </span>
                            ),
                            subtitle: lead.scheme?.name ?? undefined,
                            body: (
                                <div className="space-y-2">
                                    <div className="space-y-1 text-rml-muted">
                                        <p>
                                            {common.zone}:{' '}
                                            {lead.zone?.code ?? '—'}
                                        </p>
                                        <p>
                                            {common.size}:{' '}
                                            {lead.size_m2 != null
                                                ? `${lead.size_m2} m²`
                                                : '—'}
                                        </p>
                                        <p>
                                            {common.distance}:{' '}
                                            {lead.distance_km != null
                                                ? `${lead.distance_km} km`
                                                : '—'}
                                        </p>
                                        <p>
                                            {common.unit_price}:{' '}
                                            {formatMoney(lead.price_per_m2)}
                                        </p>
                                        <p>
                                            {common.price}:{' '}
                                            {formatMoney(lead.total_price)}
                                        </p>
                                    </div>
                                    <Checkbox
                                        label={t.select}
                                        checked={selectedIds.includes(lead.id)}
                                        onChange={() => toggleLead(lead.id)}
                                    />
                                </div>
                            ),
                            actions: (
                                <TableActionButton
                                    label={t.buy_single ?? common.buy}
                                    icon={tableActionIcons.buy}
                                    tone="success"
                                    onClick={() => openPayment([lead.id])}
                                />
                            ),
                        }))}
                    />
                ) : (
                    <DataTable
                        data={leads.data}
                        getRowId={(row) => String(row.id)}
                        emptyMessage={t.empty ?? common.empty}
                        columns={[
                            {
                                id: 'select',
                                header: t.select,
                                cell: (row) => (
                                    <Checkbox
                                        checked={selectedIds.includes(row.id)}
                                        onChange={() => toggleLead(row.id)}
                                        aria-label={t.select}
                                    />
                                ),
                            },
                            {
                                id: 'lead_reference',
                                header: sortableHeader(
                                    common.lead_id ?? '',
                                    'reference',
                                ),
                                cell: (row) => (
                                    <span className="font-mono text-sm font-medium">
                                        {row.lead_reference}
                                    </span>
                                ),
                            },
                            {
                                id: 'scheme',
                                header: sortableHeader(
                                    common.scheme ?? '',
                                    'scheme',
                                ),
                                cell: (row) => row.scheme?.name ?? '—',
                            },
                            {
                                id: 'zone',
                                header: sortableHeader(
                                    common.zone ?? '',
                                    'zone',
                                ),
                                cell: (row) => row.zone?.code ?? '—',
                            },
                            {
                                id: 'size_m2',
                                header: sortableHeader(
                                    common.size ?? '',
                                    'size_m2',
                                ),
                                cell: (row) =>
                                    row.size_m2 != null
                                        ? `${row.size_m2} m²`
                                        : '—',
                            },
                            {
                                id: 'distance_km',
                                header: sortableHeader(
                                    common.distance ?? '',
                                    'distance_km',
                                ),
                                cell: (row) =>
                                    row.distance_km != null
                                        ? `${row.distance_km} km`
                                        : '—',
                            },
                            {
                                id: 'price_per_m2',
                                header: common.unit_price,
                                cell: (row) => formatMoney(row.price_per_m2),
                            },
                            {
                                id: 'total_price',
                                header: sortableHeader(
                                    common.price ?? '',
                                    'price',
                                ),
                                cell: (row) => formatMoney(row.total_price),
                            },
                            {
                                id: 'buy',
                                header: common.actions,
                                cell: (row) => (
                                    <TableActionButton
                                        label={t.buy_single ?? common.buy}
                                        icon={tableActionIcons.buy}
                                        tone="success"
                                        onClick={() => openPayment([row.id])}
                                    />
                                ),
                            },
                        ]}
                    />
                )}
            </div>

            <Pagination
                page={page}
                pageCount={pageCount}
                perPage={currentPerPage}
                onPerPageChange={(next) =>
                    applyFilters({ per_page: next, page: 1 })
                }
                onPageChange={(nextPage) =>
                    setApplied((current) => ({
                        ...current,
                        page: nextPage,
                    }))
                }
                labels={paginationLabels(common)}
            />

            <PaymentSummaryBar
                selectedCount={selectionTotals.count}
                totalSize={selectionTotals.size}
                totalPrice={selectionTotals.price}
                labels={summaryLabels}
                onClear={() => setSelectedIds([])}
                onPay={() => openPayment(selectedIds)}
                disabled={processing}
            />

            <Modal
                open={paymentOpen}
                onClose={() => {
                    if (!processing) {
                        setPaymentOpen(false);
                    }
                }}
                title={t.payment_method}
                footer={
                    <>
                        <Button
                            variant="outline"
                            disabled={processing}
                            onClick={() => setPaymentOpen(false)}
                        >
                            {common.cancel}
                        </Button>
                        <Button
                            onClick={submitPurchase}
                            disabled={
                                processing || pendingLeadIds.length === 0
                            }
                        >
                            {processing
                                ? (paymentsT.redirecting_to_checkout ??
                                  common.processing ??
                                  common.pay_now)
                                : common.pay_now}
                        </Button>
                    </>
                }
            >
                <div className="space-y-4">
                    <p className="text-sm text-rml-muted">
                        {t.selected_count?.replace(
                            ':count',
                            String(pendingLeadIds.length),
                        )}
                    </p>
                    {!cardConfigured && (
                        <Alert variant="warning">
                            {paymentsT.provider_not_configured}
                        </Alert>
                    )}
                    <Select
                        label={t.payment_method}
                        name="payment_method"
                        value={paymentData.payment_method}
                        options={paymentMethodOptions}
                        error={paymentErrors.payment_method}
                        disabled={processing}
                        onChange={(e) =>
                            setPaymentData('payment_method', e.target.value)
                        }
                    />
                    {paymentErrors.lead_ids && (
                        <p className="text-sm text-rml-red">
                            {paymentErrors.lead_ids}
                        </p>
                    )}
                    {paymentErrors.payment_method && (
                        <p className="text-sm text-rml-red">
                            {paymentErrors.payment_method}
                        </p>
                    )}
                </div>
            </Modal>
        </AppLayout>
    );
}
