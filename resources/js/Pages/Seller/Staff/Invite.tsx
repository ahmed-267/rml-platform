import { FormEvent } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import {
    Button,
    DataTable,
    EmptyState,
    FormInput,
    MobileCardList,
    StatusBadge,
    TableActionButton,
    tableActionIcons,
} from '@/Components/ui';
import { useIsMobile } from '@/hooks/use-media-query';
import { leadStatusLabel, leadStatusTone } from '@/lib/lead-status';
import type { PageProps } from '@/types';

interface InvitationRow {
    id: number;
    email: string;
    status: string | null;
    expires_at: string | null;
    accepted_at: string | null;
    created_at: string | null;
}

export default function StaffInvite({
    invitations,
}: {
    invitations: InvitationRow[];
}) {
    const { translations, app } = usePage<PageProps>().props;
    const t = translations.seller.staff;
    const common = translations.seller.common;
    const statusLabels = {
        ...translations.statuses,
        ...translations.lead_statuses,
    };
    const isMobile = useIsMobile();

    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(route('seller.staff.invite.store'), {
            onSuccess: () => reset('email'),
        });
    };

    const cancelInvitation = (id: number) => {
        router.delete(route('seller.staff.invitations.destroy', id));
    };

    const pending = invitations.filter((row) => row.status === 'pending');

    return (
        <AppLayout title={t.invite_title} subtitle={t.invite_subtitle}>
            <Head title={t.invite_title} />

            <section className="rml-card space-y-4 p-5 sm:p-6">
                <form
                    onSubmit={submit}
                    className="flex flex-col gap-3 sm:flex-row sm:items-end"
                >
                    <div className="min-w-0 flex-1">
                        <FormInput
                            label={t.invite_email}
                            name="email"
                            type="email"
                            required
                            value={data.email}
                            error={errors.email}
                            onChange={(e) => setData('email', e.target.value)}
                        />
                    </div>
                    <Button type="submit" disabled={processing}>
                        {t.invite_send}
                    </Button>
                </form>
            </section>

            <section className="space-y-3">
                <h2 className="text-base font-semibold text-rml-text">
                    {t.invite_list}
                </h2>
                {pending.length === 0 ? (
                    <EmptyState title={t.empty_invites} />
                ) : isMobile ? (
                    <MobileCardList
                        emptyMessage={t.empty_invites}
                        items={pending.map((row) => ({
                            id: String(row.id),
                            title: row.email,
                            meta: row.status ? (
                                <StatusBadge
                                    label={leadStatusLabel(
                                        row.status,
                                        statusLabels,
                                    )}
                                    tone={leadStatusTone(row.status)}
                                />
                            ) : null,
                            body: (
                                <p className="text-rml-muted">
                                    {t.expires}:{' '}
                                    {row.expires_at
                                        ? new Date(
                                              row.expires_at,
                                          ).toLocaleString(app.locale)
                                        : '—'}
                                </p>
                            ),
                            actions: (
                                <TableActionButton
                                    label={t.invite_cancel}
                                    icon={tableActionIcons.cancel}
                                    tone="danger"
                                    onClick={() => cancelInvitation(row.id)}
                                />
                            ),
                        }))}
                    />
                ) : (
                    <DataTable
                        data={pending}
                        getRowId={(row) => String(row.id)}
                        emptyMessage={t.empty_invites}
                        columns={[
                            {
                                id: 'email',
                                header: t.invite_email,
                                cell: (row) => row.email,
                            },
                            {
                                id: 'status',
                                header: common.status,
                                cell: (row) =>
                                    row.status ? (
                                        <StatusBadge
                                            label={leadStatusLabel(
                                                row.status,
                                                statusLabels,
                                            )}
                                            tone={leadStatusTone(row.status)}
                                        />
                                    ) : (
                                        '—'
                                    ),
                            },
                            {
                                id: 'expires',
                                header: t.expires,
                                cell: (row) =>
                                    row.expires_at
                                        ? new Date(
                                              row.expires_at,
                                          ).toLocaleString(app.locale)
                                        : '—',
                            },
                            {
                                id: 'actions',
                                header: common.actions,
                                cell: (row) => (
                                    <TableActionButton
                                        label={t.invite_cancel}
                                        icon={tableActionIcons.cancel}
                                        tone="danger"
                                        onClick={() =>
                                            cancelInvitation(row.id)
                                        }
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
