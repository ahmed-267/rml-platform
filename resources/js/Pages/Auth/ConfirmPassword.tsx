import { FormEventHandler } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';
import { Button } from '@/Components/ui/Button';
import { FormInput } from '@/Components/ui/FormInput';
import type { PageProps } from '@/types';

export default function ConfirmPassword() {
    const { translations } = usePage<PageProps>().props;
    const t = translations.profile;
    const auth = translations.auth;

    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('password.confirm'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthLayout
            title={t.confirm_password_title}
            subtitle={t.confirm_password_subtitle}
        >
            <Head title={t.confirm_password_title} />

            <form onSubmit={submit} className="space-y-4">
                <FormInput
                    label={auth.password}
                    id="password"
                    type="password"
                    name="password"
                    value={data.password}
                    autoComplete="current-password"
                    autoFocus
                    required
                    error={errors.password}
                    onChange={(e) => setData('password', e.target.value)}
                />

                <Button type="submit" fullWidth disabled={processing}>
                    {t.confirm_password_submit}
                </Button>

                <p className="text-center text-sm text-rml-muted">
                    <Link
                        href={route('login')}
                        className="font-semibold text-rml-primary hover:underline"
                    >
                        {auth.sign_in}
                    </Link>
                </p>
            </form>
        </AuthLayout>
    );
}
