import {
    CreditCard,
    Package,
    ShoppingCart,
    Wallet,
} from 'lucide-react';
import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import {
    Button,
    DataTable,
    EmptyState,
    KpiCard,
    MobileCardList,
    StatusBadge,
    TableActionLink,
    tableActionIcons,
} from '@/Components/ui';
import { useIsMobile } from '@/hooks/use-media-query';
import {
    useBuyerDashboardQuery,
    type BuyerDashboardPayload,
} from '@/hooks/use-buyer-dashboard-query';
import { leadStatusLabel } from '@/lib/lead-status';
import {
    purchaseFlowStatus,
    purchaseFlowStatusTone,
} from '@/lib/purchase-status';
import type { PageProps } from '@/types';

function formatMoney(value: number): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'EUR',
        maximumFractionDigits: 0,
    }).format(value);
}

function formatDate(value: string | null, locale: string): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString(locale, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

export default function BuyerDashboard(props: BuyerDashboardPayload) {
    const { translations, app } = usePage<PageProps>().props;
    const t = translations.buyer?.dashboard ?? {};
    const common = translations.buyer?.common ?? {};
    const purchaseStatuses = translations.purchase_statuses ?? {};
    const paymentStatuses = translations.payment_statuses ?? {};
    const statusLabels = { ...purchaseStatuses, ...paymentStatuses };
    const isMobile = useIsMobile();
    const { data, isFetching } = useBuyerDashboardQuery(props);
    const kpis = data?.kpis ?? props.kpis;
    const recent_purchases = data?.recent_purchases ?? props.recent_purchases;
    const recommended_leads = data?.recommended_leads ?? props.recommended_leads;

    return (
        <AppLayout title={t.title} subtitle={t.subtitle}>
            <Head title={t.title} />

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="min-w-0" />
                <Link href={route('buyer.leads.index')} prefetch>
                    <Button>{t.browse_cta}</Button>
                </Link>
            </div>

            <div
                className={`grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 ${isFetching ? 'opacity-70' : ''}`}
            >
                <KpiCard
                    label={t.kpi_available}
                    value={kpis.available_leads}
                    icon={ShoppingCart}
                    tone="success"
                />
                <KpiCard
                    label={t.kpi_bought}
                    value={kpis.leads_bought}
                    icon={Package}
                    tone="info"
                />
                <KpiCard
                    label={t.kpi_pending_payments}
                    value={kpis.pending_payments}
                    icon={CreditCard}
                    tone="warning"
                />
                <KpiCard
                    label={t.kpi_pending_to_buy}
                    value={kpis.pending_to_buy}
                    icon={ShoppingCart}
                    tone="warning"
                />
                <KpiCard
                    label={t.kpi_total_spent}
                    value={formatMoney(kpis.total_spent)}
                    icon={Wallet}
                    tone="default"
                />
            </div>

            <section className="space-y-3">
                <h2 className="text-base font-semibold text-rml-text">
                    {t.recent_purchases}
                </h2>

                {recent_purchases.length === 0 ? (
                    <EmptyState title={t.no_purchases}>
                        <Link href={route('buyer.leads.index')}>
                            <Button>{t.browse_cta}</Button>
                        </Link>
                    </EmptyState>
                ) : isMobile ? (
                    <MobileCardList
                        emptyMessage={t.no_purchases}
                        items={recent_purchases.map((purchase) => ({
                            id: String(purchase.id),
                            title: (
                                <span className="font-mono text-sm">
                                    {purchase.display_reference}
                                </span>
                            ),
                            subtitle: purchase.scheme ?? undefined,
                            meta: (() => {
                                const status = purchaseFlowStatus({
                                    status: purchase.status,
                                    payment: {
                                        status: purchase.payment_status,
                                    },
                                });
                                return (
                                    <StatusBadge
                                        label={leadStatusLabel(
                                            status,
                                            statusLabels,
                                        )}
                                        tone={purchaseFlowStatusTone(status)}
                                    />
                                );
                            })(),
                            body: (
                                <div className="space-y-1 text-rml-muted">
                                    <p>
                                        {common.zone}: {purchase.zone ?? '—'}
                                    </p>
                                    <p>
                                        {t.purchased_date}:{' '}
                                        {formatDate(
                                            purchase.purchased_at,
                                            app.locale,
                                        )}
                                    </p>
                                </div>
                            ),
                            actions: (
                                <TableActionLink href={route(
                                        'buyer.purchases.show',
                                        purchase.id,
                                    )} label={common.view} icon={tableActionIcons.view} />
                            ),
                        }))}
                    />
                ) : (
                    <DataTable
                        data={recent_purchases}
                        getRowId={(row) => String(row.id)}
                        emptyMessage={t.no_purchases}
                        columns={[
                            {
                                id: 'reference',
                                header:
                                    translations.buyer?.purchases?.reference ??
                                    '',
                                cell: (row) => (
                                    <span className="font-mono text-sm font-medium">
                                        {row.display_reference}
                                    </span>
                                ),
                            },
                            {
                                id: 'scheme',
                                header: common.scheme,
                                cell: (row) => row.scheme ?? '—',
                            },
                            {
                                id: 'zone',
                                header: common.zone,
                                cell: (row) => row.zone ?? '—',
                            },
                            {
                                id: 'purchased_at',
                                header: t.purchased_date,
                                cell: (row) =>
                                    formatDate(row.purchased_at, app.locale),
                            },
                            {
                                id: 'status',
                                header: common.status,
                                cell: (row) => {
                                    const status = purchaseFlowStatus({
                                        status: row.status,
                                        payment: {
                                            status: row.payment_status,
                                        },
                                    });
                                    return (
                                        <StatusBadge
                                            label={leadStatusLabel(
                                                status,
                                                statusLabels,
                                            )}
                                            tone={purchaseFlowStatusTone(
                                                status,
                                            )}
                                        />
                                    );
                                },
                            },
                            {
                                id: 'actions',
                                header: common.actions,
                                cell: (row) => (
                                    <TableActionLink href={route(
                                            'buyer.purchases.show',
                                            row.id,
                                        )} label={common.view} icon={tableActionIcons.view} />
                                ),
                            },
                        ]}
                    />
                )}
            </section>

            <section className="space-y-3">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h2 className="text-base font-semibold text-rml-text">
                        {t.recommended}
                    </h2>
                    <Link href={route('buyer.leads.index')}>
                        <Button variant="outline" size="sm">
                            {t.browse_cta}
                        </Button>
                    </Link>
                </div>

                {recommended_leads.length === 0 ? (
                    <EmptyState title={t.no_recommended} />
                ) : isMobile ? (
                    <MobileCardList
                        emptyMessage={t.no_recommended}
                        items={recommended_leads.map((lead) => ({
                            id: String(lead.id),
                            title: (
                                <span className="font-mono text-sm">
                                    {lead.lead_reference}
                                </span>
                            ),
                            subtitle: lead.scheme?.name ?? undefined,
                            body: (
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
                                        {common.price}:{' '}
                                        {lead.total_price != null
                                            ? formatMoney(lead.total_price)
                                            : '—'}
                                    </p>
                                </div>
                            ),
                            actions: (
                                <TableActionLink
                                    href={route('buyer.leads.index')}
                                    label={common.buy}
                                    icon={tableActionIcons.buy}
                                />
                            ),
                        }))}
                    />
                ) : (
                    <DataTable
                        data={recommended_leads}
                        getRowId={(row) => String(row.id)}
                        emptyMessage={t.no_recommended}
                        columns={[
                            {
                                id: 'lead_reference',
                                header: common.lead_id,
                                cell: (row) => (
                                    <span className="font-mono text-sm font-medium">
                                        {row.lead_reference}
                                    </span>
                                ),
                            },
                            {
                                id: 'scheme',
                                header: common.scheme,
                                cell: (row) => row.scheme?.name ?? '—',
                            },
                            {
                                id: 'zone',
                                header: common.zone,
                                cell: (row) => row.zone?.code ?? '—',
                            },
                            {
                                id: 'size_m2',
                                header: common.size,
                                cell: (row) =>
                                    row.size_m2 != null
                                        ? `${row.size_m2} m²`
                                        : '—',
                            },
                            {
                                id: 'distance_km',
                                header: common.distance,
                                cell: (row) =>
                                    row.distance_km != null
                                        ? `${row.distance_km} km`
                                        : '—',
                            },
                            {
                                id: 'total_price',
                                header: common.price,
                                cell: (row) =>
                                    row.total_price != null
                                        ? formatMoney(row.total_price)
                                        : '—',
                            },
                            {
                                id: 'actions',
                                header: common.actions,
                                cell: () => (
                                    <TableActionLink
                                        href={route('buyer.leads.index')}
                                        label={common.buy}
                                        icon={tableActionIcons.buy}
                                    />
                                ),
                            },
                        ]}
                    />
                )}
            </section>
        </AppLayout>
    );
}
