import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import {
    Alert,
    BackLink,
    Button,
    DataTable,
    StatusBadge,
} from '@/Components/ui';
import { leadStatusLabel, leadStatusTone } from '@/lib/lead-status';
import type { PageProps } from '@/types';

interface EvidenceFile {
    id: number;
    file_type: string | null;
    original_name: string | null;
    mime_type: string | null;
    size: number | null;
    status: string | null;
    created_at: string | null;
}

interface PurchaseLead {
    id: number;
    lead_reference: string;
    scheme: { id: number; name: string; slug: string } | null;
    zone: { id: number; code: string; name: string } | null;
    size_m2: number | null;
    distance_km: number | null;
    price_per_m2: number | null;
    total_price: number | null;
    details_released: boolean;
    customer_first_name?: string;
    customer_last_name?: string;
    customer_phone?: string | null;
    customer_whatsapp?: string | null;
    customer_email?: string | null;
    address_line_1?: string | null;
    address_line_2?: string | null;
    city?: string | null;
    postcode?: string | null;
    country?: string | null;
    property_type?: string | null;
    epc_rating?: string | null;
    notes?: string | null;
    evidence?: EvidenceFile[];
}

interface PurchaseDetail {
    id: number;
    purchase_reference: string;
    display_reference: string;
    status: string | null;
    total_amount: number | null;
    total_size_m2: number | null;
    purchased_at: string | null;
    created_at: string | null;
    details_released: boolean;
    scheme: string | null;
    zone: string | null;
    item_count: number;
    package: {
        id: number;
        package_reference: string;
        name: string;
        zone_mix: Record<string, number> | null;
    } | null;
    payment: {
        id: number;
        payment_reference: string;
        status: string | null;
        method: string | null;
        amount: number | null;
        due_date: string | null;
        paid_at: string | null;
        can_pay?: boolean;
        card_provider_missing?: boolean;
        bank_instructions?: {
            account_name?: string | null;
            iban?: string | null;
            bic?: string | null;
            bank_name?: string | null;
            instructions?: string | null;
            payment_reference?: string;
            amount?: number;
            currency?: string;
        } | null;
    } | null;
    invoice_id?: number | null;
    receipt_id?: number | null;
    can_download_invoice?: boolean;
    can_download_receipt?: boolean;
    can_send_whatsapp?: boolean;
    leads: PurchaseLead[];
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

function customerName(lead: PurchaseLead): string {
    if (!lead.details_released) {
        return '—';
    }

    return `${lead.customer_first_name ?? ''} ${lead.customer_last_name ?? ''}`.trim();
}

export default function BuyerPurchasesShow({
    purchase,
    whatsapp_configured,
    card_configured = true,
    mollie_configured,
    card_provider_admin_hint = null,
}: {
    purchase: PurchaseDetail;
    whatsapp_configured?: boolean;
    card_configured?: boolean;
    mollie_configured?: boolean;
    card_provider_admin_hint?: string | null;
}) {
    const { translations, app } = usePage<PageProps>().props;
    const t = translations.buyer?.purchases ?? {};
    const paymentsT = translations.buyer?.payments ?? {};
    const common = translations.buyer?.common ?? {};
    const docs = translations.documents ?? {};
    const wa = translations.whatsapp ?? {};
    const purchaseStatuses = translations.purchase_statuses ?? {};
    const paymentStatuses = translations.payment_statuses ?? {};
    const paymentMethods = translations.payment_methods ?? {};
    const statusLabels = {
        ...purchaseStatuses,
        ...paymentStatuses,
        ...paymentMethods,
    };
    const cardConfigured = card_configured ?? mollie_configured ?? true;

    const showCardProviderWarning =
        Boolean(purchase.payment?.card_provider_missing) ||
        (purchase.payment?.method === 'card' &&
            purchase.payment?.status === 'pending' &&
            !cardConfigured);

    return (
        <AppLayout
            title={t.show_title}
            subtitle={purchase.display_reference}
        >
            <Head title={t.show_title} />

            {showCardProviderWarning && (
                <Alert variant="warning">
                    <p>{paymentsT.provider_not_configured}</p>
                    {card_provider_admin_hint ? (
                        <p className="mt-1 text-sm opacity-90">
                            {card_provider_admin_hint}
                        </p>
                    ) : null}
                </Alert>
            )}

            <div className="flex flex-wrap items-center gap-3">
                <BackLink
                    href={route('buyer.purchases.index')}
                    label={common.back}
                />
                <p className="font-mono text-sm text-rml-muted">
                    {purchase.purchase_reference}
                </p>
                {purchase.status && (
                    <StatusBadge
                        label={leadStatusLabel(
                            purchase.status,
                            statusLabels,
                        )}
                        tone={leadStatusTone(purchase.status)}
                    />
                )}
                {purchase.payment?.status && (
                    <StatusBadge
                        label={leadStatusLabel(
                            purchase.payment.status,
                            statusLabels,
                        )}
                        tone={leadStatusTone(purchase.payment.status)}
                    />
                )}
            </div>

            <div className="flex flex-wrap gap-2">
                {purchase.payment?.can_pay && (
                    <Button
                        size="sm"
                        onClick={() =>
                            router.post(
                                route('buyer.payments.pay', purchase.payment!.id),
                            )
                        }
                    >
                        {paymentsT.pay_now ?? paymentsT.continue_payment}
                    </Button>
                )}
                {purchase.can_download_invoice && purchase.invoice_id && (
                    <a href={route('invoices.download', purchase.invoice_id)}>
                        <Button size="sm" variant="outline">
                            {t.download_invoice ?? docs.download_invoice}
                        </Button>
                    </a>
                )}
                {purchase.can_download_receipt && purchase.receipt_id && (
                    <a href={route('invoices.download', purchase.receipt_id)}>
                        <Button size="sm" variant="outline">
                            {t.download_receipt ?? docs.download_receipt}
                        </Button>
                    </a>
                )}
                {purchase.can_send_whatsapp && (
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() =>
                            router.post(
                                route('buyer.purchases.whatsapp', purchase.id),
                            )
                        }
                    >
                        {t.send_whatsapp}
                    </Button>
                )}
                {purchase.can_send_whatsapp && !whatsapp_configured && (
                    <p className="text-sm text-rml-muted">{wa.not_configured}</p>
                )}
            </div>

            {purchase.payment?.bank_instructions && (
                <section className="rml-card space-y-2 p-5 sm:p-6">
                    <h2 className="text-base font-semibold text-rml-text">
                        {paymentsT.bank_details}
                    </h2>
                    <p className="text-sm text-rml-muted">
                        {paymentsT.release_after_confirm}
                    </p>
                    <dl className="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                        <div>
                            <dt className="text-rml-muted">
                                {paymentsT.account_name}
                            </dt>
                            <dd>
                                {purchase.payment.bank_instructions.account_name}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-rml-muted">{paymentsT.iban}</dt>
                            <dd className="font-mono">
                                {purchase.payment.bank_instructions.iban}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-rml-muted">{paymentsT.bic}</dt>
                            <dd className="font-mono">
                                {purchase.payment.bank_instructions.bic}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-rml-muted">
                                {paymentsT.bank_name}
                            </dt>
                            <dd>
                                {purchase.payment.bank_instructions.bank_name}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-rml-muted">
                                {paymentsT.payment_reference_label}
                            </dt>
                            <dd className="font-mono">
                                {
                                    purchase.payment.bank_instructions
                                        .payment_reference
                                }
                            </dd>
                        </div>
                        <div>
                            <dt className="text-rml-muted">
                                {paymentsT.amount}
                            </dt>
                            <dd>
                                {formatMoney(
                                    purchase.payment.bank_instructions.amount ??
                                        purchase.payment.amount,
                                )}
                            </dd>
                        </div>
                    </dl>
                    <p className="text-sm text-rml-muted">
                        {purchase.payment.bank_instructions.instructions}
                    </p>
                </section>
            )}

            <section className="rml-card grid grid-cols-1 gap-4 p-5 sm:grid-cols-2 sm:p-6">
                <div>
                    <p className="text-xs uppercase text-rml-muted">
                        {t.lead_or_package}
                    </p>
                    <p className="font-mono text-sm font-medium text-rml-text">
                        {purchase.display_reference}
                    </p>
                </div>
                <div>
                    <p className="text-xs uppercase text-rml-muted">
                        {common.scheme}
                    </p>
                    <p className="text-sm text-rml-text">
                        {purchase.scheme ?? '—'}
                    </p>
                </div>
                <div>
                    <p className="text-xs uppercase text-rml-muted">
                        {common.zone}
                    </p>
                    <p className="text-sm text-rml-text">
                        {purchase.zone ?? '—'}
                    </p>
                </div>
                <div>
                    <p className="text-xs uppercase text-rml-muted">
                        {common.price}
                    </p>
                    <p className="text-sm font-semibold text-rml-primary">
                        {formatMoney(purchase.total_amount)}
                    </p>
                </div>
                <div>
                    <p className="text-xs uppercase text-rml-muted">
                        {t.payment_status}
                    </p>
                    <p className="text-sm text-rml-text">
                        {purchase.payment?.status
                            ? leadStatusLabel(
                                  purchase.payment.status,
                                  statusLabels,
                              )
                            : '—'}
                    </p>
                </div>
                <div>
                    <p className="text-xs uppercase text-rml-muted">
                        {t.release_status}
                    </p>
                    <p className="text-sm text-rml-text">
                        {purchase.details_released
                            ? t.released
                            : t.not_released}
                    </p>
                </div>
            </section>

            {!purchase.details_released && (
                <Alert variant="warning" title={t.not_released}>
                    {t.pending_notice}
                </Alert>
            )}

            {(purchase.leads ?? []).map((lead) => (
                <section
                    key={lead.id}
                    className="rml-card space-y-4 p-5 sm:p-6"
                >
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h2 className="font-mono text-base font-semibold text-rml-text">
                            {lead.lead_reference}
                        </h2>
                        <p className="text-sm text-rml-muted">
                            {formatMoney(lead.total_price)}
                        </p>
                    </div>

                    <dl className="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <dt className="text-rml-muted">{common.scheme}</dt>
                            <dd>{lead.scheme?.name ?? '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-rml-muted">{common.zone}</dt>
                            <dd>{lead.zone?.code ?? '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-rml-muted">{common.size}</dt>
                            <dd>
                                {lead.size_m2 != null
                                    ? `${lead.size_m2} m²`
                                    : '—'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-rml-muted">
                                {common.distance}
                            </dt>
                            <dd>
                                {lead.distance_km != null
                                    ? `${lead.distance_km} km`
                                    : '—'}
                            </dd>
                        </div>
                    </dl>

                    {lead.details_released && (
                        <>
                            <div>
                                <h3 className="mb-3 text-sm font-semibold text-rml-text">
                                    {t.customer_details}
                                </h3>
                                <dl className="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                                    <div>
                                        <dt className="text-rml-muted">
                                            {translations.buyer?.profile?.name ??
                                                ''}
                                        </dt>
                                        <dd>{customerName(lead)}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-rml-muted">
                                            {translations.buyer?.profile?.phone ??
                                                ''}
                                        </dt>
                                        <dd>{lead.customer_phone ?? '—'}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-rml-muted">
                                            {translations.buyer?.profile
                                                ?.whatsapp ?? ''}
                                        </dt>
                                        <dd>{lead.customer_whatsapp ?? '—'}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-rml-muted">
                                            {translations.buyer?.profile?.email ??
                                                ''}
                                        </dt>
                                        <dd>{lead.customer_email ?? '—'}</dd>
                                    </div>
                                    <div className="sm:col-span-2">
                                        <dt className="text-rml-muted">
                                            {translations.buyer?.profile
                                                ?.address ?? ''}
                                        </dt>
                                        <dd>
                                            {[
                                                lead.address_line_1,
                                                lead.address_line_2,
                                                lead.city,
                                                lead.postcode,
                                                lead.country,
                                            ]
                                                .filter(Boolean)
                                                .join(', ') || '—'}
                                        </dd>
                                    </div>
                                </dl>
                            </div>

                            {(lead.evidence ?? []).length > 0 && (
                                <div>
                                    <h3 className="mb-3 text-sm font-semibold text-rml-text">
                                        {t.evidence}
                                    </h3>
                                    <DataTable
                                        data={lead.evidence ?? []}
                                        getRowId={(row) => String(row.id)}
                                        columns={[
                                            {
                                                id: 'name',
                                                header: t.evidence,
                                                cell: (row) =>
                                                    row.original_name ?? '—',
                                            },
                                            {
                                                id: 'type',
                                                header: common.status,
                                                cell: (row) =>
                                                    row.file_type ?? '—',
                                            },
                                            {
                                                id: 'created',
                                                header:
                                                    translations.buyer?.payments
                                                        ?.date ?? '',
                                                cell: (row) =>
                                                    row.created_at
                                                        ? new Date(
                                                              row.created_at,
                                                          ).toLocaleDateString(
                                                              app.locale,
                                                          )
                                                        : '—',
                                            },
                                        ]}
                                    />
                                </div>
                            )}
                        </>
                    )}
                </section>
            ))}
        </AppLayout>
    );
}
