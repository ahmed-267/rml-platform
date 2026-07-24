import { FormEventHandler } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';
import { Alert, Button, FormInput } from '@/Components/ui';
import type { PageProps } from '@/types';

export default function AcceptStaffInvitation({
    token,
    email,
    company_name,
    expires_at,
}: {
    token: string;
    email: string;
    company_name: string | null;
    expires_at: string | null;
}) {
    const { translations, app, flash } = usePage<PageProps>().props;
    const t = translations.seller.staff;
    const auth = translations.auth;

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        password: '',
        password_confirmation: '',
        phone: '',
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('seller.staff.invitations.accept.store', token));
    };

    return (
        <AuthLayout title={t.accept_title} subtitle={t.accept_subtitle}>
            <Head title={t.accept_title} />

            {flash?.success && (
                <Alert variant="success" className="mb-4">
                    {flash.success}
                </Alert>
            )}

            <div className="mb-6 space-y-1 text-sm text-rml-muted">
                <p>
                    {auth.email}:{' '}
                    <span className="font-medium text-rml-text">{email}</span>
                </p>
                {company_name && (
                    <p>
                        {translations.seller.profile.company_name}:{' '}
                        <span className="font-medium text-rml-text">
                            {company_name}
                        </span>
                    </p>
                )}
                {expires_at && (
                    <p>
                        {t.expires}:{' '}
                        {new Date(expires_at).toLocaleString(app.locale)}
                    </p>
                )}
            </div>

            <form onSubmit={submit} className="space-y-4">
                <FormInput
                    label={auth.full_name}
                    name="name"
                    required
                    autoFocus
                    value={data.name}
                    error={errors.name}
                    onChange={(e) => setData('name', e.target.value)}
                />
                <FormInput
                    label={auth.phone}
                    name="phone"
                    value={data.phone}
                    error={errors.phone}
                    onChange={(e) => setData('phone', e.target.value)}
                />
                <FormInput
                    label={auth.password}
                    name="password"
                    type="password"
                    required
                    autoComplete="new-password"
                    value={data.password}
                    error={errors.password}
                    onChange={(e) => setData('password', e.target.value)}
                />
                <FormInput
                    label={auth.password_confirm}
                    name="password_confirmation"
                    type="password"
                    required
                    autoComplete="new-password"
                    value={data.password_confirmation}
                    error={errors.password_confirmation}
                    onChange={(e) =>
                        setData('password_confirmation', e.target.value)
                    }
                />
                <Button type="submit" fullWidth disabled={processing}>
                    {t.accept_submit}
                </Button>
            </form>
        </AuthLayout>
    );
}
