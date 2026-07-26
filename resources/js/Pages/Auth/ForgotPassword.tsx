import { FormEventHandler } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';
import { Alert } from '@/Components/ui/Alert';
import { Button } from '@/Components/ui/Button';
import { FormInput } from '@/Components/ui/FormInput';
import type { PageProps } from '@/types';

export default function ForgotPassword({ status }: { status?: string }) {
    const { translations } = usePage<PageProps>().props;
    const t = translations.auth;

    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('password.email'));
    };

    return (
        <AuthLayout
            title={t.forgot_title}
            subtitle={t.forgot_subtitle}
            backHref={route('login')}
        >
            <Head title={t.forgot_title} />

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
                    autoFocus
                    required
                    error={errors.email}
                    onChange={(e) => setData('email', e.target.value)}
                />

                <Button type="submit" fullWidth disabled={processing}>
                    {t.forgot_submit}
                </Button>

                <p className="text-center text-sm text-rml-muted">
                    <Link
                        href={route('login')}
                        className="font-semibold text-rml-primary hover:underline"
                    >
                        {t.sign_in}
                    </Link>
                </p>
            </form>
        </AuthLayout>
    );
}
