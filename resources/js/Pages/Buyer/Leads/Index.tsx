import { useMemo, useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import {
    Alert,
    Button,
    Checkbox,
    DataTable,
    EmptyState,
    FormInput,
    MobileCardList,
    Modal,
    Pagination,
    PaymentSummaryBar,
    Select,
    SortableHeader,
} from '@/Components/ui';
import type { SortDirection } from '@/Components/ui/SortableHeader';
import {
    nextSortDirection,
    paginationLabels,
    paginationMeta,
    resolveSortDirection,
    type Paginator,
} from '@/lib/list-helpers';
import { useIsMobile } from '@/hooks/use-media-query';
import type { PageProps } from '@/types';

interface MarketplaceLead {
    id: number;
    lead_reference: string;
    scheme: { id: number; name: string; slug: string } | null;
    zone: { id: number; code: string; name: string } | null;
    size_m2: number | null;
    distance_km: number | null;
    price_per_m2: number | null;
    total_price: number | null;
}

interface Filters {
    scheme_id?: string | number | null;
    zone_id?: string | number | null;
    min_size?: string | number | null;
    max_size?: string | number | null;
    min_distance?: string | number | null;
    max_distance?: string | number | null;
    min_price?: string | number | null;
    max_price?: string | number | null;
    search?: string | null;
    sort?: string | null;
    direction?: string | null;
    per_page?: number | string | null;
}

interface FilterOptions {
    schemes: Array<{ id: number; name: string }>;
    zones: Array<{
        id: number;
        code: string;
        name: string;
        scheme_id: number;
    }>;
}

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

export default function BuyerLeadsIndex({
    leads,
    filters,
    filterOptions,
    card_configured = true,
    mollie_configured,
}: {
    leads: Paginator<MarketplaceLead>;
    filters: Filters;
    filterOptions: FilterOptions;
    card_configured?: boolean;
    mollie_configured?: boolean;
}) {
    const { translations } = usePage<PageProps>().props;
    const t = translations.buyer?.leads ?? {};
    const common = translations.buyer?.common ?? {};
    const summaryBar = translations.buyer?.summary_bar ?? {};
    const paymentMethods = translations.payment_methods ?? {};
    const paymentsT = translations.buyer?.payments ?? {};
    const isMobile = useIsMobile();
    const cardConfigured = card_configured ?? mollie_configured ?? true;

    const [search, setSearch] = useState(filters.search ?? '');
    const [schemeId, setSchemeId] = useState(
        filters.scheme_id != null ? String(filters.scheme_id) : '',
    );
    const [zoneId, setZoneId] = useState(
        filters.zone_id != null ? String(filters.zone_id) : '',
    );
    const [minSize, setMinSize] = useState(
        filters.min_size != null ? String(filters.min_size) : '',
    );
    const [maxSize, setMaxSize] = useState(
        filters.max_size != null ? String(filters.max_size) : '',
    );
    const [minDistance, setMinDistance] = useState(
        filters.min_distance != null ? String(filters.min_distance) : '',
    );
    const [maxDistance, setMaxDistance] = useState(
        filters.max_distance != null ? String(filters.max_distance) : '',
    );
    const [minPrice, setMinPrice] = useState(
        filters.min_price != null ? String(filters.min_price) : '',
    );
    const [maxPrice, setMaxPrice] = useState(
        filters.max_price != null ? String(filters.max_price) : '',
    );
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [paymentOpen, setPaymentOpen] = useState(false);
    const [pendingLeadIds, setPendingLeadIds] = useState<number[]>([]);

    const currentSort = filters.sort ?? 'date';
    const currentDirection: SortDirection = resolveSortDirection(
        filters.direction,
    );
    const { page, pageCount, perPage } = paginationMeta(leads);
    const currentPerPage = Number(filters.per_page ?? perPage ?? 10);

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

    const queryParams = (
        overrides: Record<string, string | number | undefined> = {},
    ) => ({
        search: search || undefined,
        scheme_id: schemeId || undefined,
        zone_id: zoneId || undefined,
        min_size: minSize || undefined,
        max_size: maxSize || undefined,
        min_distance: minDistance || undefined,
        max_distance: maxDistance || undefined,
        min_price: minPrice || undefined,
        max_price: maxPrice || undefined,
        sort: currentSort,
        direction: currentDirection,
        per_page: currentPerPage,
        ...overrides,
    });

    const applyFilters = (
        overrides: Record<string, string | number | undefined> = {},
    ) => {
        router.get(route('buyer.leads.index'), queryParams({ page: 1, ...overrides }), {
            preserveState: true,
            replace: true,
        });
    };

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
        router.get(
            route('buyer.leads.index'),
            { sort: 'date', direction: 'desc', per_page: currentPerPage },
            { preserveState: true, replace: true },
        );
    };

    const handleSort = (column: string) => {
        router.get(
            route('buyer.leads.index'),
            queryParams({
                sort: column,
                direction: nextSortDirection(
                    currentSort,
                    column,
                    currentDirection,
                ),
                page: 1,
            }),
            { preserveState: true, replace: true },
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

            <form
                className="rml-card-compact space-y-3"
                onSubmit={(e) => {
                    e.preventDefault();
                    applyFilters();
                }}
            >
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <FormInput
                        label={common.search}
                        name="search"
                        value={search}
                        placeholder={t.search_placeholder}
                        onChange={(e) => setSearch(e.target.value)}
                    />
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
                            ...zoneOptions.map((zone) => ({
                                value: String(zone.id),
                                label: `${zone.code} — ${zone.name}`,
                            })),
                        ]}
                    />
                    <FormInput
                        label={t.min_size}
                        name="min_size"
                        type="number"
                        value={minSize}
                        onChange={(e) => setMinSize(e.target.value)}
                    />
                    <FormInput
                        label={t.max_size}
                        name="max_size"
                        type="number"
                        value={maxSize}
                        onChange={(e) => setMaxSize(e.target.value)}
                    />
                </div>
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
                    <FormInput
                        label={t.min_distance}
                        name="min_distance"
                        type="number"
                        value={minDistance}
                        onChange={(e) => setMinDistance(e.target.value)}
                    />
                    <FormInput
                        label={t.max_distance}
                        name="max_distance"
                        type="number"
                        value={maxDistance}
                        onChange={(e) => setMaxDistance(e.target.value)}
                    />
                    <FormInput
                        label={t.min_price}
                        name="min_price"
                        type="number"
                        value={minPrice}
                        onChange={(e) => setMinPrice(e.target.value)}
                    />
                    <FormInput
                        label={t.max_price}
                        name="max_price"
                        type="number"
                        value={maxPrice}
                        onChange={(e) => setMaxPrice(e.target.value)}
                    />
                    <div className="flex items-end gap-2">
                        <Button type="submit" size="sm" className="flex-1 sm:flex-none">
                            {common.apply}
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={resetFilters}
                        >
                            {common.reset}
                        </Button>
                    </div>
                </div>
            </form>

            <div className={isMobile && selectedIds.length > 0 ? 'pb-28' : ''}>
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
                                <Button
                                    size="sm"
                                    onClick={() => openPayment([lead.id])}
                                >
                                    {t.buy_single ?? common.buy}
                                </Button>
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
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => openPayment([row.id])}
                                    >
                                        {t.buy_single ?? common.buy}
                                    </Button>
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
                    router.get(
                        route('buyer.leads.index'),
                        queryParams({ page: nextPage }),
                        { preserveState: true },
                    )
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
