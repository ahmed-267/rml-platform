import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Alert, Button, StatusBadge } from '@/Components/ui';
import { formatMoney } from '@/lib/admin-helpers';
import { leadStatusTone } from '@/lib/lead-status';
import type { PageProps } from '@/types';

type PaymentPayload = {
    id: number;
    payment_reference: string;
    amount: number | null;
    currency: string | null;
    status: string | null;
    method: string | null;
    provider: string | null;
    purchase_id: number | null;
    can_pay?: boolean;
};

export default function PaymentCancelled({
    payment,
    purchase_id,
}: {
    payment: PaymentPayload;
    purchase_id: number | null;
}) {
    const { translations } = usePage<PageProps>().props;
    const t = translations.buyer.payments;
    const statuses = translations.payment_statuses;
    const purchaseId = purchase_id ?? payment.purchase_id;

    const retry = () => {
        router.post(route('buyer.payments.pay', payment.id));
    };

    return (
        <AppLayout
            title={t.cancelled_title}
            subtitle={payment.payment_reference}
        >
            <Head title={t.cancelled_title} />

            <div className="mx-auto max-w-lg space-y-4">
                <Alert variant="warning" title={t.payment_cancelled}>
                    <p className="text-sm">{t.payment_cancelled_hint}</p>
                </Alert>

                <div className="space-y-3 rounded-xl border border-rml-border bg-white p-4">
                    <div className="flex items-center justify-between gap-3">
                        <span className="font-mono text-sm text-rml-text">
                            {payment.payment_reference}
                        </span>
                        <StatusBadge
                            label={
                                (payment.status && statuses?.[payment.status]) ||
                                payment.status ||
                                '—'
                            }
                            tone={leadStatusTone(payment.status)}
                        />
                    </div>
                    <p className="text-sm text-rml-muted">
                        {t.amount}:{' '}
                        {formatMoney(payment.amount, payment.currency ?? 'EUR')}
                    </p>
                </div>

                <div className="flex flex-wrap gap-2">
                    {payment.can_pay ? (
                        <Button type="button" onClick={retry}>
                            {t.try_again}
                        </Button>
                    ) : null}
                    {purchaseId ? (
                        <Link href={route('buyer.purchases.show', purchaseId)}>
                            <Button type="button" variant="secondary">
                                {t.view_purchase ?? t.continue_payment}
                            </Button>
                        </Link>
                    ) : null}
                    <Link href={route('buyer.payments')}>
                        <Button type="button" variant="secondary">
                            {t.title}
                        </Button>
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}
