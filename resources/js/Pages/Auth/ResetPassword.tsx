import { FormEventHandler } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';
import { Button } from '@/Components/ui/Button';
import { FormInput } from '@/Components/ui/FormInput';
import type { PageProps } from '@/types';

export default function ResetPassword({
    token,
    email,
}: {
    token: string;
    email: string;
}) {
    const { translations } = usePage<PageProps>().props;
    const t = translations.auth;

    const { data, setData, post, processing, errors, reset } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('password.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <AuthLayout title={t.reset_title} subtitle={t.reset_subtitle}>
            <Head title={t.reset_title} />

            <form onSubmit={submit} className="space-y-4">
                <FormInput
                    label={t.email}
                    id="email"
                    type="email"
                    name="email"
                    value={data.email}
                    autoComplete="username"
                    error={errors.email}
                    onChange={(e) => setData('email', e.target.value)}
                />

                <FormInput
                    label={t.password}
                    id="password"
                    type="password"
                    name="password"
                    value={data.password}
                    autoComplete="new-password"
                    autoFocus
                    required
                    error={errors.password}
                    onChange={(e) => setData('password', e.target.value)}
                />

                <FormInput
                    label={t.password_confirm}
                    type="password"
                    name="password_confirmation"
                    value={data.password_confirmation}
                    autoComplete="new-password"
                    required
                    error={errors.password_confirmation}
                    onChange={(e) =>
                        setData('password_confirmation', e.target.value)
                    }
                />

                <Button type="submit" fullWidth disabled={processing}>
                    {t.reset_submit}
                </Button>
            </form>
        </AuthLayout>
    );
}
