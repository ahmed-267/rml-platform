import { FormEventHandler, useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import AuthLayout from '@/Layouts/AuthLayout';
import { Alert, Button, FormInput } from '@/Components/ui';
import { Modal } from '@/Components/ui/Modal';
import type { PageProps } from '@/types';

function AccountForms({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const { translations, auth } = usePage<PageProps>().props;
    const t = translations.profile ?? {};
    const user = auth.user!;

    const profileForm = useForm({
        name: user.name,
        email: user.email,
    });

    const passwordForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const deleteForm = useForm({
        password: '',
    });

    const [confirmDelete, setConfirmDelete] = useState(false);

    const submitProfile: FormEventHandler = (e) => {
        e.preventDefault();
        profileForm.patch(route('profile.update'));
    };

    const submitPassword: FormEventHandler = (e) => {
        e.preventDefault();
        passwordForm.put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => passwordForm.reset(),
        });
    };

    const submitDelete: FormEventHandler = (e) => {
        e.preventDefault();
        deleteForm.delete(route('profile.destroy'), {
            preserveScroll: true,
            onSuccess: () => setConfirmDelete(false),
            onFinish: () => deleteForm.reset(),
        });
    };

    return (
        <div className="space-y-6">
            <section className="rml-card space-y-4 p-5 sm:p-6">
                <div>
                    <h2 className="text-base font-semibold text-rml-text">
                        {t.account_details}
                    </h2>
                    <p className="mt-1 text-sm text-rml-muted">
                        {t.account_details_help}
                    </p>
                </div>
                <form onSubmit={submitProfile} className="space-y-4">
                    <FormInput
                        label={t.name}
                        id="name"
                        value={profileForm.data.name}
                        onChange={(e) =>
                            profileForm.setData('name', e.target.value)
                        }
                        required
                        autoComplete="name"
                        error={profileForm.errors.name}
                    />
                    <FormInput
                        label={t.email}
                        id="email"
                        type="email"
                        value={profileForm.data.email}
                        onChange={(e) =>
                            profileForm.setData('email', e.target.value)
                        }
                        required
                        autoComplete="username"
                        error={profileForm.errors.email}
                    />
                    {mustVerifyEmail && user.email_verified_at === null && (
                        <Alert variant="warning">
                            <p>{t.unverified}</p>
                            <button
                                type="button"
                                className="mt-2 text-sm font-semibold text-rml-primary hover:underline"
                                onClick={() =>
                                    router.post(route('verification.send'))
                                }
                            >
                                {t.resend_verification}
                            </button>
                            {status === 'verification-link-sent' && (
                                <p className="mt-2 text-sm text-rml-primary">
                                    {t.verification_sent}
                                </p>
                            )}
                        </Alert>
                    )}
                    <div className="flex flex-wrap items-center gap-3">
                        <Button type="submit" disabled={profileForm.processing}>
                            {t.save}
                        </Button>
                        {profileForm.recentlySuccessful && (
                            <span className="text-sm text-rml-muted">
                                {t.saved}
                            </span>
                        )}
                    </div>
                </form>
            </section>

            <section className="rml-card space-y-4 p-5 sm:p-6">
                <div>
                    <h2 className="text-base font-semibold text-rml-text">
                        {t.change_password}
                    </h2>
                    <p className="mt-1 text-sm text-rml-muted">
                        {t.change_password_help}
                    </p>
                </div>
                <form onSubmit={submitPassword} className="space-y-4">
                    <FormInput
                        label={t.current_password}
                        id="current_password"
                        type="password"
                        value={passwordForm.data.current_password}
                        onChange={(e) =>
                            passwordForm.setData(
                                'current_password',
                                e.target.value,
                            )
                        }
                        autoComplete="current-password"
                        error={passwordForm.errors.current_password}
                    />
                    <FormInput
                        label={t.new_password}
                        id="password"
                        type="password"
                        value={passwordForm.data.password}
                        onChange={(e) =>
                            passwordForm.setData('password', e.target.value)
                        }
                        autoComplete="new-password"
                        error={passwordForm.errors.password}
                    />
                    <FormInput
                        label={t.confirm_password}
                        id="password_confirmation"
                        type="password"
                        value={passwordForm.data.password_confirmation}
                        onChange={(e) =>
                            passwordForm.setData(
                                'password_confirmation',
                                e.target.value,
                            )
                        }
                        autoComplete="new-password"
                        error={passwordForm.errors.password_confirmation}
                    />
                    <div className="flex flex-wrap items-center gap-3">
                        <Button
                            type="submit"
                            disabled={passwordForm.processing}
                        >
                            {t.save}
                        </Button>
                        {passwordForm.recentlySuccessful && (
                            <span className="text-sm text-rml-muted">
                                {t.saved}
                            </span>
                        )}
                    </div>
                </form>
            </section>

            <section className="rml-card space-y-4 p-5 sm:p-6">
                <div>
                    <h2 className="text-base font-semibold text-rml-text">
                        {t.delete_account}
                    </h2>
                    <p className="mt-1 text-sm text-rml-muted">
                        {t.delete_account_help}
                    </p>
                </div>
                <Button
                    variant="danger"
                    onClick={() => setConfirmDelete(true)}
                >
                    {t.delete_account}
                </Button>
            </section>

            <Modal
                open={confirmDelete}
                onClose={() => {
                    setConfirmDelete(false);
                    deleteForm.clearErrors();
                    deleteForm.reset();
                }}
                title={t.delete_confirm_title}
            >
                <form onSubmit={submitDelete} className="space-y-4">
                    <p className="text-sm text-rml-muted">
                        {t.delete_confirm_body}
                    </p>
                    <FormInput
                        label={translations.auth.password}
                        id="delete_password"
                        type="password"
                        value={deleteForm.data.password}
                        onChange={(e) =>
                            deleteForm.setData('password', e.target.value)
                        }
                        required
                        autoComplete="current-password"
                        error={deleteForm.errors.password}
                    />
                    <div className="flex flex-wrap justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setConfirmDelete(false)}
                        >
                            {t.cancel}
                        </Button>
                        <Button
                            type="submit"
                            variant="danger"
                            disabled={deleteForm.processing}
                        >
                            {t.delete_confirm_button}
                        </Button>
                    </div>
                </form>
            </Modal>
        </div>
    );
}

export default function Edit({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const { translations, auth } = usePage<PageProps>().props;
    const t = translations.profile ?? {};
    const portal = auth.user?.portal;
    const title = t.title ?? 'Account';
    const subtitle = t.subtitle;

    if (portal) {
        return (
            <AppLayout title={title} subtitle={subtitle}>
                <Head title={title} />
                <AccountForms
                    mustVerifyEmail={mustVerifyEmail}
                    status={status}
                />
            </AppLayout>
        );
    }

    return (
        <AuthLayout title={title} subtitle={subtitle} wide>
            <Head title={title} />
            <AccountForms mustVerifyEmail={mustVerifyEmail} status={status} />
        </AuthLayout>
    );
}
