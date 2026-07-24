import { FormEvent } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, FormInput, Select, StatusBadge } from '@/Components/ui';
import { leadStatusLabel, leadStatusTone } from '@/lib/lead-status';
import type { PageProps } from '@/types';

export default function AuditorProfile({
    profile,
    stats,
}: {
    profile: {
        id: number;
        name: string;
        email: string;
        phone: string | null;
        locale: string | null;
        approval_status: string | null;
        role: string | null;
        permissions: string[];
    };
    stats: {
        assigned: number;
        in_review: number;
        completed: number;
        recommended_accept: number;
        recommended_reject: number;
    };
}) {
    const { translations } = usePage<PageProps>().props;
    const t = translations.auditor.profile;
    const common = translations.auditor.common;
    const roles = translations.roles;
    const statuses = translations.statuses;

    const form = useForm({
        name: profile.name ?? '',
        phone: profile.phone ?? '',
        locale: profile.locale ?? 'en',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(route('auditor.profile.update'));
    };

    return (
        <AppLayout title={t.title} subtitle={t.subtitle}>
            <Head title={t.title} />

            <section className="rml-card space-y-4 p-5 sm:p-6">
                <div className="flex flex-wrap items-center gap-3">
                    <h2 className="text-base font-semibold text-rml-text">
                        {t.account}
                    </h2>
                    {profile.approval_status && (
                        <StatusBadge
                            label={leadStatusLabel(
                                profile.approval_status,
                                statuses,
                            )}
                            tone={leadStatusTone(profile.approval_status)}
                        />
                    )}
                </div>

                <dl className="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt className="text-rml-muted">{common.role}</dt>
                        <dd className="font-medium text-rml-text">
                            {profile.role
                                ? (roles[profile.role] ?? profile.role)
                                : '—'}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-rml-muted">{t.email}</dt>
                        <dd className="font-medium text-rml-text">
                            {profile.email}
                        </dd>
                    </div>
                </dl>

                <form onSubmit={submit} className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <FormInput
                        label={t.name}
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        error={form.errors.name}
                    />
                    <FormInput
                        label={t.phone}
                        value={form.data.phone}
                        onChange={(e) => form.setData('phone', e.target.value)}
                        error={form.errors.phone}
                    />
                    <Select
                        label={t.language}
                        value={form.data.locale}
                        onChange={(e) => form.setData('locale', e.target.value)}
                        options={[
                            { value: 'en', label: t.locale_en },
                            { value: 'es', label: t.locale_es },
                            { value: 'fr', label: t.locale_fr },
                        ]}
                    />
                    <div className="flex items-end">
                        <Button type="submit" disabled={form.processing}>
                            {common.save}
                        </Button>
                    </div>
                </form>
            </section>

            <section className="rml-card space-y-3 p-5 sm:p-6">
                <h2 className="text-base font-semibold text-rml-text">
                    {t.stats}
                </h2>
                <dl className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                    <Stat label={t.stat_assigned} value={stats.assigned} />
                    <Stat label={t.stat_in_review} value={stats.in_review} />
                    <Stat label={t.stat_completed} value={stats.completed} />
                    <Stat
                        label={t.stat_recommend_accept}
                        value={stats.recommended_accept}
                    />
                    <Stat
                        label={t.stat_recommend_reject}
                        value={stats.recommended_reject}
                    />
                </dl>
            </section>

            <section className="rml-card space-y-3 p-5 sm:p-6">
                <h2 className="text-base font-semibold text-rml-text">
                    {t.permissions}
                </h2>
                <p className="text-sm text-rml-muted">{t.permissions_help}</p>
                <ul className="flex flex-wrap gap-2">
                    {profile.permissions.map((permission) => (
                        <li
                            key={permission}
                            className="rounded-md bg-rml-background px-2 py-1 font-mono text-xs text-rml-muted"
                        >
                            {permission}
                        </li>
                    ))}
                </ul>
            </section>
        </AppLayout>
    );
}

function Stat({ label, value }: { label: string; value: number }) {
    return (
        <div>
            <dt className="text-rml-muted">{label}</dt>
            <dd className="text-lg font-semibold text-rml-text">{value}</dd>
        </div>
    );
}
