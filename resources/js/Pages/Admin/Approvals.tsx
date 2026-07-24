import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/Button';
import { EmptyState } from '@/Components/ui/EmptyState';
import { FormInput } from '@/Components/ui/FormInput';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import type { ApprovalStatus, PageProps } from '@/types';

interface ApprovalRow {
    id: number;
    name: string;
    email: string;
    type: string;
    role: string | null;
    role_label: string | null;
    company_name: string | null;
    approval_status: ApprovalStatus;
    submitted_at: string | null;
    approved_at: string | null;
}

export default function Approvals({
    pending,
    recentApproved,
}: {
    pending: ApprovalRow[];
    recentApproved: ApprovalRow[];
}) {
    const { translations } = usePage<PageProps>().props;
    const t = translations.approvals;
    const roles = translations.roles;
    const statuses = translations.statuses;
    const [rejectingId, setRejectingId] = useState<number | null>(null);
    const [reason, setReason] = useState('');

    const typeLabel = (type: string) => {
        if (type === 'buyer') {
            return t.type_buyer;
        }
        if (type === 'individual_seller') {
            return t.type_individual_seller;
        }
        return t.type_seller_company;
    };

    const roleLabel = (row: ApprovalRow) => {
        if (row.role && roles[row.role]) {
            return roles[row.role];
        }
        return row.role_label ?? '';
    };

    const statusLabel = (status: ApprovalStatus) =>
        statuses[status] ?? status;

    const approve = (id: number) => {
        router.post(route('admin.approvals.approve', id));
    };

    const reject = (id: number) => {
        router.post(
            route('admin.approvals.reject', id),
            { reason },
            {
                onSuccess: () => {
                    setRejectingId(null);
                    setReason('');
                },
            },
        );
    };

    const suspend = (id: number) => {
        router.post(route('admin.approvals.suspend', id));
    };

    return (
        <AppLayout title={t.title} subtitle={t.subtitle}>
            <Head title={t.head_title} />

            <section className="rml-card overflow-hidden">
                <div className="border-b border-rml-border px-5 py-4">
                    <h2 className="text-base font-semibold text-rml-text">
                        {t.pending_title}
                    </h2>
                    <p className="mt-1 text-sm text-rml-muted">
                        {t.pending_subtitle}
                    </p>
                </div>

                {pending.length === 0 ? (
                    <div className="p-6">
                        <EmptyState
                            title={t.empty_title}
                            description={t.empty_description}
                        />
                    </div>
                ) : (
                    <ul className="divide-y divide-rml-border">
                        {pending.map((row) => (
                            <li key={row.id} className="px-5 py-4">
                                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div className="min-w-0 space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="font-semibold text-rml-text">
                                                {row.company_name ?? row.name}
                                            </p>
                                            <StatusBadge
                                                label={statusLabel(
                                                    row.approval_status,
                                                )}
                                                tone={
                                                    row.approval_status ===
                                                    'approved'
                                                        ? 'success'
                                                        : row.approval_status ===
                                                            'rejected'
                                                          ? 'danger'
                                                          : 'warning'
                                                }
                                            />
                                        </div>
                                        <p className="text-sm text-rml-muted">
                                            {row.name} · {row.email}
                                        </p>
                                        <p className="text-xs text-rml-muted">
                                            {roleLabel(row)} ·{' '}
                                            {typeLabel(row.type)}
                                            {row.submitted_at
                                                ? ` · ${new Date(row.submitted_at).toLocaleString()}`
                                                : ''}
                                        </p>
                                    </div>

                                    <div className="flex flex-wrap gap-2">
                                        {row.approval_status !== 'approved' && (
                                            <Button
                                                size="sm"
                                                onClick={() => approve(row.id)}
                                            >
                                                {t.approve}
                                            </Button>
                                        )}
                                        {row.approval_status === 'pending' && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    setRejectingId(
                                                        rejectingId === row.id
                                                            ? null
                                                            : row.id,
                                                    )
                                                }
                                            >
                                                {t.reject}
                                            </Button>
                                        )}
                                        {row.approval_status === 'approved' && (
                                            <Button
                                                size="sm"
                                                variant="danger"
                                                onClick={() => suspend(row.id)}
                                            >
                                                {t.suspend}
                                            </Button>
                                        )}
                                    </div>
                                </div>

                                {rejectingId === row.id && (
                                    <div className="mt-4 space-y-3 rounded-lg border border-rml-border bg-rml-background p-4">
                                        <FormInput
                                            label={t.rejection_reason}
                                            name="reason"
                                            value={reason}
                                            required
                                            onChange={(e) =>
                                                setReason(e.target.value)
                                            }
                                        />
                                        <div className="flex gap-2">
                                            <Button
                                                size="sm"
                                                variant="danger"
                                                onClick={() => reject(row.id)}
                                                disabled={
                                                    reason.trim().length < 5
                                                }
                                            >
                                                {t.confirm_reject}
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() => {
                                                    setRejectingId(null);
                                                    setReason('');
                                                }}
                                            >
                                                {t.cancel}
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            {recentApproved.length > 0 && (
                <section className="rml-card mt-6 overflow-hidden">
                    <div className="border-b border-rml-border px-5 py-4">
                        <h2 className="text-base font-semibold text-rml-text">
                            {t.recent_title}
                        </h2>
                    </div>
                    <ul className="divide-y divide-rml-border">
                        {recentApproved.map((row) => (
                            <li
                                key={row.id}
                                className="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div>
                                    <p className="font-medium text-rml-text">
                                        {row.company_name ?? row.name}
                                    </p>
                                    <p className="text-sm text-rml-muted">
                                        {row.email} · {roleLabel(row)}
                                    </p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <StatusBadge
                                        label={statusLabel('approved')}
                                        tone="success"
                                    />
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => suspend(row.id)}
                                    >
                                        {t.suspend}
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                </section>
            )}
        </AppLayout>
    );
}
