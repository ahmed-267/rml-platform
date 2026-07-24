import { FormEventHandler } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Clock3, LogOut, Mail } from 'lucide-react';
import AuthLayout from '@/Layouts/AuthLayout';
import { Alert } from '@/Components/ui/Alert';
import { Button } from '@/Components/ui/Button';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import type { ApprovalStatus, PageProps } from '@/types';

interface AccountPayload {
    name: string;
    email: string;
    approval_status: ApprovalStatus;
    role: string | null;
    role_label: string | null;
    company_name: string | null;
    company_type: string | null;
    submitted_at: string | null;
}

export default function PendingApproval({
    account,
}: {
    account: AccountPayload;
}) {
    const { translations } = usePage<PageProps>().props;
    const t = translations.pending;
    const statuses = translations.statuses;
    const roles = translations.roles;
    const { post, processing } = useForm({});

    const logout: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('logout'));
    };

    const tone =
        account.approval_status === 'rejected'
            ? 'danger'
            : account.approval_status === 'suspended'
              ? 'warning'
              : 'warning';

    const statusLabel =
        statuses[account.approval_status] ?? account.approval_status;
    const roleLabel =
        (account.role && roles[account.role]) || account.role_label;

    return (
        <AuthLayout title={t.title} subtitle={t.subtitle}>
            <Head title={t.title} />

            <div className="mb-5 flex items-center justify-center">
                <div className="flex h-14 w-14 items-center justify-center rounded-full bg-rml-amber/10 text-rml-amber">
                    <Clock3 className="h-7 w-7" />
                </div>
            </div>

            <Alert
                variant={tone === 'danger' ? 'error' : 'warning'}
                className="mb-5"
            >
                {account.approval_status === 'rejected'
                    ? t.rejected_message
                    : account.approval_status === 'suspended'
                      ? t.suspended_message
                      : t.message}
            </Alert>

            <div className="space-y-3 rounded-xl border border-rml-border bg-rml-background p-4">
                <div className="flex items-center justify-between gap-3">
                    <p className="text-sm text-rml-muted">{t.status}</p>
                    <StatusBadge
                        label={statusLabel}
                        tone={
                            account.approval_status === 'approved'
                                ? 'success'
                                : account.approval_status === 'rejected'
                                  ? 'danger'
                                  : 'warning'
                        }
                    />
                </div>
                <div>
                    <p className="text-xs text-rml-muted">{t.account}</p>
                    <p className="text-sm font-medium text-rml-text">
                        {account.name}
                    </p>
                    <p className="text-sm text-rml-muted">{account.email}</p>
                </div>
                {account.company_name && (
                    <div>
                        <p className="text-xs text-rml-muted">{t.company}</p>
                        <p className="text-sm font-medium text-rml-text">
                            {account.company_name}
                        </p>
                    </div>
                )}
                {roleLabel && (
                    <div>
                        <p className="text-xs text-rml-muted">{t.account_type}</p>
                        <p className="text-sm font-medium text-rml-text">
                            {roleLabel}
                        </p>
                    </div>
                )}
            </div>

            <div className="mt-6 flex flex-col gap-3">
                <form onSubmit={logout}>
                    <Button
                        type="submit"
                        variant="outline"
                        fullWidth
                        disabled={processing}
                    >
                        <LogOut className="mr-2 h-4 w-4" />
                        {t.sign_out}
                    </Button>
                </form>
                <Link
                    href="/#contact"
                    className="inline-flex items-center justify-center gap-2 text-sm font-semibold text-rml-primary hover:underline"
                >
                    <Mail className="h-4 w-4" />
                    {t.contact_support}
                </Link>
            </div>
        </AuthLayout>
    );
}
