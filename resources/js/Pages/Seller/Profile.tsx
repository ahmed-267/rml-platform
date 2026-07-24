import { FormEvent } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, FormInput, StatusBadge } from '@/Components/ui';
import { leadStatusLabel, leadStatusTone } from '@/lib/lead-status';
import type { PageProps } from '@/types';

interface SellerProfileProps {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    locale: string | null;
    approval_status: string | null;
    role: string | null;
    seller_type: string | null;
    company: { id: number; name: string } | null;
    commission_rate: number | null;
    bank_account_iban: string | null;
    bank_account_name: string | null;
    payout_method: string | null;
}

export default function SellerProfile({
    profile,
}: {
    profile: SellerProfileProps;
}) {
    const { translations } = usePage<PageProps>().props;
    const t = translations.seller.profile;
    const payments = translations.seller.payments;
    const staff = translations.seller.staff;
    const statuses = translations.statuses;
    const roles = translations.roles;

    const {
        data,
        setData,
        put,
        processing,
        errors,
        recentlySuccessful,
    } = useForm({
        name: profile.name ?? '',
        email: profile.email ?? '',
        phone: profile.phone ?? '',
        locale: profile.locale ?? 'en',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        put(route('seller.profile.update'));
    };

    return (
        <AppLayout title={t.title} subtitle={t.subtitle}>
            <Head title={t.title} />

            <section className="rml-card space-y-4 p-5 sm:p-6">
                <div className="flex flex-wrap items-center gap-3">
                    <h2 className="text-base font-semibold text-rml-text">
                        {t.personal}
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
                        <dt className="text-rml-muted">{t.account_status}</dt>
                        <dd className="font-medium text-rml-text">
                            {profile.role
                                ? (roles[profile.role] ?? profile.role)
                                : '—'}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-rml-muted">{t.approval_status}</dt>
                        <dd className="font-medium text-rml-text">
                            {profile.approval_status
                                ? leadStatusLabel(
                                      profile.approval_status,
                                      statuses,
                                  )
                                : '—'}
                        </dd>
                    </div>
                    {profile.commission_rate != null && (
                        <div>
                            <dt className="text-rml-muted">
                                {staff.commission_rate}
                            </dt>
                            <dd className="font-medium text-rml-text">
                                {profile.commission_rate}%
                            </dd>
                        </div>
                    )}
                </dl>

                <form onSubmit={submit} className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <FormInput
                        label={t.name}
                        name="name"
                        required
                        value={data.name}
                        error={errors.name}
                        onChange={(e) => setData('name', e.target.value)}
                    />
                    <FormInput
                        label={t.email}
                        name="email"
                        type="email"
                        required
                        value={data.email}
                        error={errors.email}
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    <FormInput
                        label={t.phone}
                        name="phone"
                        value={data.phone}
                        error={errors.phone}
                        onChange={(e) => setData('phone', e.target.value)}
                    />
                    <FormInput
                        label={translations.common.language}
                        name="locale"
                        value={data.locale}
                        error={errors.locale}
                        onChange={(e) => setData('locale', e.target.value)}
                    />
                    <div className="flex items-center gap-3 sm:col-span-2">
                        <Button type="submit" disabled={processing}>
                            {t.save}
                        </Button>
                        {recentlySuccessful && (
                            <p className="text-sm text-rml-primary">{t.saved}</p>
                        )}
                    </div>
                </form>
            </section>

            <section className="rml-card space-y-4 p-5 sm:p-6">
                <h2 className="text-base font-semibold text-rml-text">
                    {t.company}
                </h2>
                <FormInput
                    label={t.company_name}
                    name="company_name"
                    value={profile.company?.name ?? ''}
                    disabled
                    hint={t.readonly_company}
                />
            </section>

            <section className="rml-card space-y-4 p-5 sm:p-6">
                <h2 className="text-base font-semibold text-rml-text">
                    {t.bank}
                </h2>
                <p className="text-sm text-rml-muted">
                    <Link
                        href={route('seller.payments')}
                        className="font-semibold text-rml-primary hover:underline"
                    >
                        {payments.bank_details}
                    </Link>
                </p>
                <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt className="text-xs uppercase text-rml-muted">
                            {payments.iban}
                        </dt>
                        <dd className="font-mono text-sm text-rml-text">
                            {profile.bank_account_iban ?? '—'}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-xs uppercase text-rml-muted">
                            {payments.account_name}
                        </dt>
                        <dd className="text-sm text-rml-text">
                            {profile.bank_account_name ?? '—'}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-xs uppercase text-rml-muted">
                            {payments.payout_method}
                        </dt>
                        <dd className="text-sm text-rml-text">
                            {profile.payout_method ?? '—'}
                        </dd>
                    </div>
                </dl>
            </section>
        </AppLayout>
    );
}
