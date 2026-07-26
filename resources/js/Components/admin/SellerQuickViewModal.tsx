import { Link } from '@inertiajs/react';
import { Button, Modal, StatusBadge } from '@/Components/ui';

export type SellerQuickView = {
    id: number;
    name: string;
    email: string;
    phone?: string | null;
    company_name?: string | null;
    role_label?: string | null;
    approval_status?: string | null;
};

export function SellerQuickViewModal({
    open,
    onClose,
    seller,
    companyName,
    labels,
    statusLabel,
    statusTone,
}: {
    open: boolean;
    onClose: () => void;
    seller: SellerQuickView | null;
    companyName?: string | null;
    labels: {
        title: string;
        name: string;
        email: string;
        phone: string;
        company: string;
        role: string;
        status: string;
        openFullProfile: string;
        close: string;
    };
    statusLabel?: string;
    statusTone?: 'success' | 'warning' | 'danger' | 'info' | 'neutral' | 'default';
}) {
    if (!seller) {
        return null;
    }

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={labels.title}
            size="md"
            footer={
                <>
                    <Button variant="ghost" size="sm" onClick={onClose}>
                        {labels.close}
                    </Button>
                    <Link
                        href={route('admin.sellers.show', seller.id)}
                        className="inline-flex"
                    >
                        <Button size="sm">{labels.openFullProfile}</Button>
                    </Link>
                </>
            }
        >
            <div className="space-y-4">
                <div className="rounded-2xl border border-rml-border bg-gradient-to-br from-slate-50 to-white p-4">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div className="min-w-0">
                            <p className="text-lg font-semibold tracking-tight text-rml-text">
                                {seller.name}
                            </p>
                            <p className="mt-0.5 text-sm text-rml-muted">
                                {companyName ?? seller.company_name ?? '—'}
                            </p>
                        </div>
                        {statusLabel && (
                            <StatusBadge
                                label={statusLabel}
                                tone={statusTone ?? 'neutral'}
                            />
                        )}
                    </div>
                </div>

                <dl className="grid gap-3 sm:grid-cols-2">
                    <div>
                        <dt className="text-xs font-medium uppercase tracking-wide text-rml-muted">
                            {labels.email}
                        </dt>
                        <dd className="mt-1 text-sm text-rml-text">
                            {seller.email}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-xs font-medium uppercase tracking-wide text-rml-muted">
                            {labels.phone}
                        </dt>
                        <dd className="mt-1 text-sm text-rml-text">
                            {seller.phone ?? '—'}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-xs font-medium uppercase tracking-wide text-rml-muted">
                            {labels.company}
                        </dt>
                        <dd className="mt-1 text-sm text-rml-text">
                            {companyName ?? seller.company_name ?? '—'}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-xs font-medium uppercase tracking-wide text-rml-muted">
                            {labels.role}
                        </dt>
                        <dd className="mt-1 text-sm text-rml-text">
                            {seller.role_label ?? '—'}
                        </dd>
                    </div>
                </dl>
            </div>
        </Modal>
    );
}
