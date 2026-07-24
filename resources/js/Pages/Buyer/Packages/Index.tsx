import { FormEvent, useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import {
    Alert,
    Button,
    Checkbox,
    EmptyState,
    FormInput,
    Modal,
    Select,
    StatusBadge,
} from '@/Components/ui';
import { useIsMobile } from '@/hooks/use-media-query';
import type { PageProps } from '@/types';

interface MarketplaceLead {
    id: number;
    lead_reference: string;
    scheme: { id: number; name: string; slug: string } | null;
    zone: { id: number; code: string; name: string } | null;
    size_m2: number | null;
    total_price: number | null;
}

interface PreviewPayload {
    enough: boolean;
    requested_count: number;
    matched_count: number;
    estimated_total: number;
    total_size_m2: number;
    avg_price_per_m2: number | null;
    avg_size_m2: number | null;
    zone_mix: Record<string, number>;
    lead_ids: number[];
    leads: MarketplaceLead[];
    mix?: Record<string, number>;
    shortfalls?: Record<string, number>;
    package_type?: string;
    name?: string;
}

interface PrebuiltPackage {
    id: number;
    package_reference: string;
    name: string;
    package_type: string | null;
    scheme: string | null;
    zone_mix: Record<string, number> | null;
    lead_count: number;
    avg_size_m2: number | null;
    avg_price_per_m2: number | null;
    estimated_total: number;
    buyable: boolean;
    leads: MarketplaceLead[];
}

interface BuilderFilters {
    lead_count?: number;
    scheme_id?: number | null;
    zone_codes?: string[];
    min_size?: number | null;
    max_size?: number | null;
    min_distance?: number | null;
    max_distance?: number | null;
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

function zoneMixLabel(mix: Record<string, number> | null | undefined): string {
    if (!mix) {
        return '—';
    }

    return Object.entries(mix)
        .map(([code, count]) => `${count}×${code}`)
        .join(' · ');
}

export default function BuyerPackagesIndex({
    prebuilt,
    mixed_zone,
    schemes,
    zone_options,
    preview,
    builder_filters,
}: {
    prebuilt: PrebuiltPackage[];
    mixed_zone: PreviewPayload;
    schemes: Array<{ id: number; name: string; slug: string }>;
    zone_options: string[];
    preview?: PreviewPayload;
    builder_filters?: BuilderFilters;
}) {
    const { translations } = usePage<PageProps>().props;
    const t = translations.buyer?.packages ?? {};
    const common = translations.buyer?.common ?? {};
    const paymentMethods = translations.payment_methods ?? {};
    const isMobile = useIsMobile();

    const activePreview = preview ?? null;

    const [paymentOpen, setPaymentOpen] = useState(false);

    const builderForm = useForm({
        lead_count: builder_filters?.lead_count ?? 10,
        scheme_id: builder_filters?.scheme_id ?? '',
        zone_codes: builder_filters?.zone_codes ?? ([] as string[]),
        min_size: builder_filters?.min_size ?? '',
        max_size: builder_filters?.max_size ?? '',
        min_distance: builder_filters?.min_distance ?? '',
        max_distance: builder_filters?.max_distance ?? '',
    });

    const purchaseForm = useForm({
        type: 'custom' as 'prebuilt' | 'custom' | 'mixed_zone',
        package_id: null as number | null,
        payment_method: 'card',
        lead_count: builderForm.data.lead_count,
        scheme_id: builderForm.data.scheme_id,
        zone_codes: builderForm.data.zone_codes,
        min_size: builderForm.data.min_size,
        max_size: builderForm.data.max_size,
        min_distance: builderForm.data.min_distance,
        max_distance: builderForm.data.max_distance,
    });

    const toggleZone = (code: string) => {
        const current = builderForm.data.zone_codes;
        builderForm.setData(
            'zone_codes',
            current.includes(code)
                ? current.filter((value) => value !== code)
                : [...current, code],
        );
    };

    const calculatePreview = (event: FormEvent) => {
        event.preventDefault();
        router.post(route('buyer.packages.preview'), builderForm.data, {
            preserveScroll: true,
        });
    };

    const openPurchase = (
        type: 'prebuilt' | 'custom' | 'mixed_zone',
        packageId?: number,
    ) => {
        purchaseForm.setData({
            type,
            package_id: packageId ?? null,
            payment_method: 'card',
            lead_count: builderForm.data.lead_count,
            scheme_id: builderForm.data.scheme_id,
            zone_codes: builderForm.data.zone_codes,
            min_size: builderForm.data.min_size,
            max_size: builderForm.data.max_size,
            min_distance: builderForm.data.min_distance,
            max_distance: builderForm.data.max_distance,
        });
        setPaymentOpen(true);
    };

    const submitPurchase = () => {
        if (purchaseForm.processing) {
            return;
        }

        purchaseForm.post(route('buyer.packages.purchase'), {
            preserveScroll: true,
            onSuccess: () => setPaymentOpen(false),
        });
    };

    const paymentMethodOptions = [
        { value: 'card', label: paymentMethods.card ?? 'card' },
        {
            value: 'manual_bank_transfer',
            label: paymentMethods.manual_bank_transfer ?? 'manual_bank_transfer',
        },
    ];

    const renderPreviewCard = (
        payload: PreviewPayload,
        onBuy?: () => void,
        buyDisabled?: boolean,
    ) => (
        <div className="rml-card space-y-4 p-5 sm:p-6">
            <div className="flex flex-wrap items-center gap-2">
                <StatusBadge
                    label={
                        payload.enough ? t.enough ?? '' : t.not_enough ?? ''
                    }
                    tone={payload.enough ? 'success' : 'warning'}
                />
                <p className="text-sm text-rml-muted">
                    {t.matched
                        ?.replace(':matched', String(payload.matched_count))
                        ?.replace(
                            ':requested',
                            String(payload.requested_count),
                        )}
                </p>
            </div>
            <dl className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                <div>
                    <dt className="text-rml-muted">{t.lead_count_col}</dt>
                    <dd className="font-semibold text-rml-text">
                        {payload.matched_count}
                    </dd>
                </div>
                <div>
                    <dt className="text-rml-muted">{t.avg_m2}</dt>
                    <dd className="font-semibold text-rml-text">
                        {payload.avg_size_m2 != null
                            ? `${payload.avg_size_m2} m²`
                            : '—'}
                    </dd>
                </div>
                <div>
                    <dt className="text-rml-muted">{t.unit_rate}</dt>
                    <dd className="font-semibold text-rml-text">
                        {formatMoney(payload.avg_price_per_m2)}
                    </dd>
                </div>
                <div>
                    <dt className="text-rml-muted">{t.estimated_total}</dt>
                    <dd className="font-semibold text-rml-primary">
                        {formatMoney(payload.estimated_total)}
                    </dd>
                </div>
            </dl>
            {Object.keys(payload.zone_mix ?? {}).length > 0 && (
                <p className="text-sm text-rml-muted">
                    {t.mix}: {zoneMixLabel(payload.zone_mix)}
                </p>
            )}
            {onBuy && (
                <Button
                    onClick={onBuy}
                    disabled={buyDisabled || !payload.enough}
                    fullWidth={isMobile}
                >
                    {t.buy_package}
                </Button>
            )}
        </div>
    );

    return (
        <AppLayout title={t.title} subtitle={t.subtitle}>
            <Head title={t.title} />

            <section className="rml-card space-y-4 p-5 sm:p-6">
                <h2 className="text-base font-semibold text-rml-text">
                    {t.builder_title}
                </h2>
                <form
                    onSubmit={calculatePreview}
                    className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <FormInput
                        label={t.lead_count}
                        name="lead_count"
                        type="number"
                        min={1}
                        required
                        value={String(builderForm.data.lead_count)}
                        error={builderForm.errors.lead_count}
                        onChange={(e) =>
                            builderForm.setData(
                                'lead_count',
                                Number(e.target.value) || 1,
                            )
                        }
                    />
                    <Select
                        label={common.scheme}
                        name="scheme_id"
                        value={
                            builderForm.data.scheme_id !== ''
                                ? String(builderForm.data.scheme_id)
                                : ''
                        }
                        options={[
                            { value: '', label: common.all },
                            ...schemes.map((scheme) => ({
                                value: String(scheme.id),
                                label: scheme.name,
                            })),
                        ]}
                        error={builderForm.errors.scheme_id}
                        onChange={(e) =>
                            builderForm.setData(
                                'scheme_id',
                                e.target.value ? Number(e.target.value) : '',
                            )
                        }
                    />
                    <FormInput
                        label={t.min_size ?? translations.buyer?.leads?.min_size}
                        name="min_size"
                        type="number"
                        value={String(builderForm.data.min_size)}
                        error={builderForm.errors.min_size}
                        onChange={(e) =>
                            builderForm.setData('min_size', e.target.value)
                        }
                    />
                    <FormInput
                        label={t.max_size ?? translations.buyer?.leads?.max_size}
                        name="max_size"
                        type="number"
                        value={String(builderForm.data.max_size)}
                        error={builderForm.errors.max_size}
                        onChange={(e) =>
                            builderForm.setData('max_size', e.target.value)
                        }
                    />
                    <FormInput
                        label={
                            translations.buyer?.leads?.min_distance ?? ''
                        }
                        name="min_distance"
                        type="number"
                        value={String(builderForm.data.min_distance)}
                        error={builderForm.errors.min_distance}
                        onChange={(e) =>
                            builderForm.setData('min_distance', e.target.value)
                        }
                    />
                    <FormInput
                        label={
                            translations.buyer?.leads?.max_distance ?? ''
                        }
                        name="max_distance"
                        type="number"
                        value={String(builderForm.data.max_distance)}
                        error={builderForm.errors.max_distance}
                        onChange={(e) =>
                            builderForm.setData('max_distance', e.target.value)
                        }
                    />
                    <div className="space-y-2 sm:col-span-2 lg:col-span-3">
                        <p className="text-sm font-medium text-rml-text">
                            {t.zones}
                        </p>
                        <div className="flex flex-wrap gap-4">
                            {zone_options.map((code) => (
                                <Checkbox
                                    key={code}
                                    label={code}
                                    checked={builderForm.data.zone_codes.includes(
                                        code,
                                    )}
                                    onChange={() => toggleZone(code)}
                                />
                            ))}
                        </div>
                    </div>
                    <div className="sm:col-span-2 lg:col-span-3">
                        <Button type="submit" disabled={builderForm.processing}>
                            {t.calculate}
                        </Button>
                    </div>
                </form>

                {activePreview &&
                    renderPreviewCard(activePreview, () =>
                        openPurchase('custom'),
                    )}
            </section>

            <section className="space-y-4">
                <h2 className="text-base font-semibold text-rml-text">
                    {t.mixed_title}
                </h2>
                <p className="text-sm text-rml-muted">{t.mixed_subtitle}</p>
                {renderPreviewCard(mixed_zone, () => openPurchase('mixed_zone'), !mixed_zone.enough)}
            </section>

            <section className="space-y-4">
                <h2 className="text-base font-semibold text-rml-text">
                    {t.prebuilt_title}
                </h2>

                {prebuilt.length === 0 ? (
                    <EmptyState title={common.empty} />
                ) : (
                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        {prebuilt.map((pkg) => (
                            <article
                                key={pkg.id}
                                className="rml-card flex flex-col p-5 sm:p-6"
                            >
                                <div className="mb-3 flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <p className="font-mono text-xs text-rml-muted">
                                            {pkg.package_reference}
                                        </p>
                                        <h3 className="text-base font-semibold text-rml-text">
                                            {pkg.name}
                                        </h3>
                                        {pkg.scheme && (
                                            <p className="text-sm text-rml-muted">
                                                {pkg.scheme}
                                            </p>
                                        )}
                                    </div>
                                    <StatusBadge
                                        label={
                                            pkg.buyable
                                                ? common.available ?? ''
                                                : t.unavailable ?? ''
                                        }
                                        tone={pkg.buyable ? 'success' : 'warning'}
                                    />
                                </div>
                                <dl className="grid grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <dt className="text-rml-muted">
                                            {t.lead_count_col}
                                        </dt>
                                        <dd className="font-semibold text-rml-text">
                                            {pkg.lead_count}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-rml-muted">
                                            {t.avg_m2}
                                        </dt>
                                        <dd className="font-semibold text-rml-text">
                                            {pkg.avg_size_m2 != null
                                                ? `${pkg.avg_size_m2} m²`
                                                : '—'}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-rml-muted">
                                            {t.unit_rate}
                                        </dt>
                                        <dd className="font-semibold text-rml-text">
                                            {formatMoney(pkg.avg_price_per_m2)}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-rml-muted">
                                            {t.estimated_total}
                                        </dt>
                                        <dd className="font-semibold text-rml-primary">
                                            {formatMoney(pkg.estimated_total)}
                                        </dd>
                                    </div>
                                </dl>
                                <p className="mt-3 text-sm text-rml-muted">
                                    {t.mix}: {zoneMixLabel(pkg.zone_mix)}
                                </p>
                                {!pkg.buyable && (
                                    <Alert variant="warning" className="mt-4">
                                        {t.not_enough_leads}
                                    </Alert>
                                )}
                                <Button
                                    className="mt-5"
                                    disabled={!pkg.buyable}
                                    onClick={() =>
                                        openPurchase('prebuilt', pkg.id)
                                    }
                                    fullWidth={isMobile}
                                >
                                    {t.buy_package}
                                </Button>
                            </article>
                        ))}
                    </div>
                )}
            </section>

            <Modal
                open={paymentOpen}
                onClose={() => {
                    if (!purchaseForm.processing) {
                        setPaymentOpen(false);
                    }
                }}
                title={translations.buyer?.leads?.payment_method ?? ''}
                footer={
                    <>
                        <Button
                            variant="outline"
                            disabled={purchaseForm.processing}
                            onClick={() => setPaymentOpen(false)}
                        >
                            {common.cancel}
                        </Button>
                        <Button
                            onClick={submitPurchase}
                            disabled={purchaseForm.processing}
                        >
                            {purchaseForm.processing
                                ? (translations.buyer?.payments
                                      ?.redirecting_to_checkout ??
                                  common.processing ??
                                  common.pay_now)
                                : common.pay_now}
                        </Button>
                    </>
                }
            >
                <Select
                    label={translations.buyer?.leads?.payment_method ?? ''}
                    name="payment_method"
                    value={purchaseForm.data.payment_method}
                    options={paymentMethodOptions}
                    error={purchaseForm.errors.payment_method}
                    disabled={purchaseForm.processing}
                    onChange={(e) =>
                        purchaseForm.setData('payment_method', e.target.value)
                    }
                />
                {purchaseForm.errors.payment_method ? (
                    <p className="mt-2 text-sm text-rml-red">
                        {purchaseForm.errors.payment_method}
                    </p>
                ) : null}
            </Modal>
        </AppLayout>
    );
}
