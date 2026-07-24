import { FormEventHandler } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';
import { Alert } from '@/Components/ui/Alert';
import { Button } from '@/Components/ui/Button';
import type { PageProps } from '@/types';

export default function VerifyEmail({ status }: { status?: string }) {
    const { translations } = usePage<PageProps>().props;
    const t = translations.profile;
    const pending = translations.pending;
    const { post, processing } = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('verification.send'));
    };

    return (
        <AuthLayout title={t.verify_title} subtitle={t.verify_subtitle}>
            <Head title={t.verify_title} />

            <p className="mb-4 text-sm text-rml-muted">{t.verify_body}</p>

            {status === 'verification-link-sent' && (
                <Alert variant="success" className="mb-4">
                    {t.verification_sent}
                </Alert>
            )}

            <form onSubmit={submit} className="space-y-3">
                <Button type="submit" fullWidth disabled={processing}>
                    {t.verify_resend}
                </Button>
            </form>

            <div className="mt-3">
                <Button
                    type="button"
                    variant="outline"
                    fullWidth
                    disabled={processing}
                    onClick={() => router.post(route('logout'))}
                >
                    {pending.sign_out}
                </Button>
            </div>
        </AuthLayout>
    );
}
