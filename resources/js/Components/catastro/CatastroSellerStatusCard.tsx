import { StatusBadge } from '@/Components/ui';
import { usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';

type SellerStatus = {
    key: string;
    label_key: string;
};

type Props = {
    cadastralReference?: string | null;
    sellerStatus?: SellerStatus | null;
    enabled?: boolean;
};

function toneFor(key: string): 'success' | 'warning' | 'danger' | 'neutral' | 'info' {
    switch (key) {
        case 'verified':
            return 'success';
        case 'needs_review':
            return 'warning';
        case 'failed':
            return 'danger';
        default:
            return 'neutral';
    }
}

/**
 * Seller-facing Catastro status only — no technical/internal details.
 */
export function CatastroSellerStatusCard({
    cadastralReference,
    sellerStatus,
    enabled = true,
}: Props) {
    const { translations } = usePage<PageProps>().props;
    const t = (translations as PageProps['translations'] & {
        catastro?: Record<string, any>;
    }).catastro ?? {};
    const sellerLabels = t.seller_statuses ?? {};

    const key = sellerStatus?.key ?? 'not_checked';
    const label =
        sellerLabels[key] ??
        (key === 'verified'
            ? 'Verified'
            : key === 'needs_review'
              ? 'Needs review'
              : key === 'failed'
                ? 'Failed'
                : 'Not checked');

    return (
        <section className="rml-card space-y-3 p-4 sm:p-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-sm font-semibold text-rml-text">
                        {t.seller_card_title ?? 'Cadastral verification'}
                    </h2>
                    <p className="mt-1 text-sm text-rml-muted">
                        {t.seller_card_subtitle ??
                            'Simple status for the cadastral reference you submitted.'}
                    </p>
                </div>
                <StatusBadge label={label} tone={toneFor(key)} />
            </div>

            {!enabled ? (
                <p className="text-sm text-rml-muted">
                    {t.lookup_unavailable ??
                        'Catastro lookup is currently unavailable.'}
                </p>
            ) : !cadastralReference ? (
                <p className="text-sm text-rml-muted">
                    {t.no_reference ?? 'No cadastral reference provided.'}
                </p>
            ) : (
                <dl className="grid gap-2 text-sm sm:grid-cols-2">
                    <div>
                        <dt className="text-rml-muted">
                            {t.cadastral_reference ?? 'Cadastral reference'}
                        </dt>
                        <dd className="font-mono font-medium text-rml-text">
                            {cadastralReference}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-rml-muted">
                            {t.status ?? 'Status'}
                        </dt>
                        <dd className="text-rml-text">{label}</dd>
                    </div>
                </dl>
            )}
        </section>
    );
}
