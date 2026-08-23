import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, Select, StatusBadge } from '@/Components/ui';
import { formatDate, formatMoney } from '@/lib/admin-helpers';
import type { PageProps } from '@/types';

type PackageDetail = {
    id: number;
    package_reference: string;
    name: string;
    status: string | null;
    package_type: string | null;
    scheme: string | null;
    buyer_company: { id: number; name: string; city: string | null } | null;
    created_by: string | null;
    created_at: string | null;
    lead_count: number;
    zone_mix: Record<string, number> | null;
    distance_range_min: number | null;
    distance_range_max: number | null;
    total_size_m2: number;
    total_selling_price: number;
    total_seller_payout: number;
    estimated_margin: number;
    estimated_total: number;
    can_cancel: boolean;
    can_assign_buyer?: boolean;
    leads: Array<{
        id: number;
        lead_reference: string;
        scheme: string | null;
        zone: string | null;
        seller_company?: string | null;
        size_m2: number | null;
        selling_price: number;
        buying_price: number;
        distance_km: number | null;
        view_url: string;
    }>;
};

export default function AdminPackageShow({
    package: pkg,
    can_manage = false,
    can_sell = false,
    installer_options = [],
}: {
    package: PackageDetail;
    can_manage?: boolean;
    can_sell?: boolean;
    installer_options?: Array<{
        id: number;
        name: string;
        city?: string | null;
    }>;
}) {
    const { translations, app } = usePage<PageProps>().props;
    const t = (translations.admin as Record<string, any>).packages ?? {};
    const common = translations.admin.common;
    const statusLabels =
        (translations as Record<string, any>).package_statuses ?? {};

    const assignForm = useForm({
        buyer_company_id: '',
    });

    const cancelPackage = () => {
        if (!confirm(t.cancel_confirm ?? 'Cancel this package?')) {
            return;
        }
        router.post(route('admin.packages.cancel', pkg.id));
    };

    return (
        <AppLayout title={pkg.package_reference} subtitle={pkg.name}>
            <Head title={pkg.package_reference} />

            <div className="mb-4 flex flex-wrap items-center gap-2">
                <Link
                    href={route('admin.leads.index', { tab: 'packages' })}
                    className="text-sm text-rml-primary"
                >
                    {common.back ?? 'Back'}
                </Link>
                {can_sell &&
                    (pkg.status === 'available' || pkg.status === 'draft') && (
                        <Button
                            size="sm"
                            variant="primary"
                            onClick={() =>
                                router.get(
                                    route('admin.sales.create', {
                                        type: 'package',
                                        package_id: pkg.id,
                                        return_to: 'package',
                                    }),
                                )
                            }
                        >
                            {t.sell_package ?? t.buy_package ?? 'Buy package'}
                        </Button>
                    )}
                {can_manage && pkg.can_cancel && (
                    <Button size="sm" variant="outline" onClick={cancelPackage}>
                        {t.cancel_package ?? 'Cancel package'}
                    </Button>
                )}
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <div className="rml-card space-y-2 p-4 lg:col-span-1">
                    <div className="flex items-center justify-between gap-2">
                        <h2 className="text-sm font-semibold">
                            {t.details ?? 'Package details'}
                        </h2>
                        {pkg.status && (
                            <StatusBadge
                                label={statusLabels[pkg.status] ?? pkg.status}
                                tone="neutral"
                            />
                        )}
                    </div>
                    <p className="font-mono text-sm">{pkg.package_reference}</p>
                    <p className="text-sm">{pkg.name}</p>
                    <dl className="space-y-1 text-sm text-rml-muted">
                        <div>
                            <dt className="inline font-medium text-rml-text">
                                {t.buyer ?? 'Installer'}:{' '}
                            </dt>
                            <dd className="inline">
                                {pkg.buyer_company?.name ?? '—'}
                            </dd>
                        </div>
                        <div>
                            <dt className="inline font-medium text-rml-text">
                                {t.lead_count ?? 'Leads'}:{' '}
                            </dt>
                            <dd className="inline">{pkg.lead_count}</dd>
                        </div>
                        <div>
                            <dt className="inline font-medium text-rml-text">
                                {t.total_size ?? 'Total size'}:{' '}
                            </dt>
                            <dd className="inline">{pkg.total_size_m2} m²</dd>
                        </div>
                        <div>
                            <dt className="inline font-medium text-rml-text">
                                {t.seller_payout ?? 'Seller payout'}:{' '}
                            </dt>
                            <dd className="inline">
                                {formatMoney(pkg.total_seller_payout)}
                            </dd>
                        </div>
                        <div>
                            <dt className="inline font-medium text-rml-text">
                                {t.total_price ?? 'Selling price'}:{' '}
                            </dt>
                            <dd className="inline">
                                {formatMoney(pkg.total_selling_price)}
                            </dd>
                        </div>
                        <div>
                            <dt className="inline font-medium text-rml-text">
                                {t.margin ?? 'Margin'}:{' '}
                            </dt>
                            <dd className="inline">
                                {formatMoney(pkg.estimated_margin)}
                            </dd>
                        </div>
                        <div>
                            <dt className="inline font-medium text-rml-text">
                                {t.created_by ?? 'Created by'}:{' '}
                            </dt>
                            <dd className="inline">{pkg.created_by ?? '—'}</dd>
                        </div>
                        <div>
                            <dt className="inline font-medium text-rml-text">
                                {t.created_at ?? 'Created at'}:{' '}
                            </dt>
                            <dd className="inline">
                                {formatDate(pkg.created_at, app.locale)}
                            </dd>
                        </div>
                    </dl>

                    {can_manage &&
                        pkg.can_assign_buyer &&
                        installer_options.length > 0 && (
                            <form
                                className="mt-4 space-y-2 border-t border-rml-border pt-3"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    assignForm.post(
                                        route(
                                            'admin.packages.assign-buyer',
                                            pkg.id,
                                        ),
                                    );
                                }}
                            >
                                <Select
                                    label={t.assign_buyer ?? 'Assign buyer'}
                                    value={assignForm.data.buyer_company_id}
                                    onChange={(e) =>
                                        assignForm.setData(
                                            'buyer_company_id',
                                            e.target.value,
                                        )
                                    }
                                    options={[
                                        { label: '—', value: '' },
                                        ...installer_options.map((item) => ({
                                            label: item.city
                                                ? `${item.name} — ${item.city}`
                                                : item.name,
                                            value: String(item.id),
                                        })),
                                    ]}
                                />
                                <Button
                                    type="submit"
                                    size="sm"
                                    variant="primary"
                                    disabled={
                                        assignForm.processing ||
                                        !assignForm.data.buyer_company_id
                                    }
                                >
                                    {t.assign_buyer ?? 'Assign buyer'}
                                </Button>
                            </form>
                        )}
                </div>

                <div className="rml-card overflow-hidden lg:col-span-2">
                    <div className="border-b border-rml-border px-4 py-3">
                        <h2 className="text-sm font-semibold">
                            {t.leads_included ?? 'Leads included'}
                        </h2>
                    </div>
                    <div className="divide-y divide-rml-border">
                        {pkg.leads.map((lead) => (
                            <div
                                key={lead.id}
                                className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm"
                            >
                                <div>
                                    <Link
                                        href={lead.view_url}
                                        className="font-mono font-medium text-rml-primary"
                                    >
                                        {lead.lead_reference}
                                    </Link>
                                    <p className="text-xs text-rml-muted">
                                        {[
                                            lead.seller_company,
                                            lead.scheme,
                                            lead.zone,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ') || '—'}
                                        {lead.size_m2 != null
                                            ? ` · ${lead.size_m2} m²`
                                            : ''}
                                        {lead.distance_km != null
                                            ? ` · ${lead.distance_km} km`
                                            : ''}
                                    </p>
                                </div>
                                <div className="text-right text-xs text-rml-muted">
                                    <p>{formatMoney(lead.selling_price)}</p>
                                    <p>
                                        {t.payout ?? 'Payout'}:{' '}
                                        {formatMoney(lead.buying_price)}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
