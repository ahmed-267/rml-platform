import { FormEventHandler } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';
import { Alert } from '@/Components/ui/Alert';
import { Button } from '@/Components/ui/Button';
import { Checkbox } from '@/Components/ui/Checkbox';
import { FormInput } from '@/Components/ui/FormInput';
import type { PageProps } from '@/types';

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    const { translations } = usePage<PageProps>().props;
    const t = translations.auth;

    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthLayout title={t.login_title} subtitle={t.login_subtitle}>
            <Head title={t.sign_in} />

            {status && (
                <Alert variant="success" className="mb-4">
                    {status}
                </Alert>
            )}

            <form onSubmit={submit} className="space-y-4">
                <FormInput
                    label={t.email}
                    id="email"
                    type="email"
                    name="email"
                    value={data.email}
                    autoComplete="username"
                    autoFocus
                    required
                    error={errors.email}
                    onChange={(e) => setData('email', e.target.value)}
                />

                <FormInput
                    label={t.password}
                    id="password"
                    type="password"
                    name="password"
                    value={data.password}
                    autoComplete="current-password"
                    required
                    error={errors.password}
                    onChange={(e) => setData('password', e.target.value)}
                />

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Checkbox
                        name="remember"
                        checked={data.remember}
                        onChange={(e) => setData('remember', e.target.checked)}
                        label={t.remember}
                    />

                    {canResetPassword && (
                        <Link
                            href={route('password.request')}
                            className="text-sm font-medium text-rml-primary hover:underline"
                        >
                            {t.forgot}
                        </Link>
                    )}
                </div>

                <Button type="submit" fullWidth disabled={processing}>
                    {t.sign_in}
                </Button>

                <p className="text-center text-sm text-rml-muted">
                    {t.no_account}{' '}
                    <Link
                        href={route('register')}
                        className="font-semibold text-rml-primary hover:underline"
                    >
                        {t.create_account}
                    </Link>
                </p>
            </form>
        </AuthLayout>
    );
}
