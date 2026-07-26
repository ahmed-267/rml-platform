import { FormEventHandler, useMemo, useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';
import { Alert } from '@/Components/ui/Alert';
import { Button } from '@/Components/ui/Button';
import { Checkbox } from '@/Components/ui/Checkbox';
import { FileUpload } from '@/Components/ui/FileUpload';
import { FormInput } from '@/Components/ui/FormInput';
import { CountrySelect } from '@/Components/ui/CountrySelect';
import { DEFAULT_COUNTRY } from '@/lib/countries';
import {
    phoneErrorMessage,
    phoneMessagesFromTranslations,
    sanitizePhoneInput,
} from '@/lib/phone';
import { useScrollToFirstError } from '@/hooks/use-scroll-to-first-error';
import type { PageProps } from '@/types';

type AccountType = 'company' | 'individual';

export default function RegisterSeller() {
    const { translations } = usePage<PageProps>().props;
    const t = translations.auth;
    const [accountType, setAccountType] = useState<AccountType>('company');
    const [clientErrors, setClientErrors] = useState<Record<string, string>>(
        {},
    );

    const phoneMessages = phoneMessagesFromTranslations(
        translations.validation,
        (field) =>
            (
                translations.validation?.field_required ??
                ':field is required.'
            ).replace(':field', field),
        t.phone,
    );

    const { data, setData, post, processing, errors, reset } = useForm({
        account_type: 'company' as AccountType,
        company_name: '',
        name: '',
        email: '',
        phone: '',
        whatsapp: '',
        postcode: '',
        address: '',
        city: '',
        country: DEFAULT_COUNTRY,
        password: '',
        password_confirmation: '',
        agreement: false as boolean,
        gdpr: false as boolean,
    });

    const fieldError = (key: string) =>
        clientErrors[key] ?? (errors as Record<string, string | undefined>)[key];

    useScrollToFirstError({ ...clientErrors, ...errors });

    const switchType = (type: AccountType) => {
        setAccountType(type);
        setData('account_type', type);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const next: Record<string, string> = {};
        const phoneErr = phoneErrorMessage(data.phone, phoneMessages, {
            required: true,
        });
        if (phoneErr) {
            next.phone = phoneErr;
        }
        const whatsappErr = phoneErrorMessage(data.whatsapp, phoneMessages, {
            required: false,
        });
        if (whatsappErr) {
            next.whatsapp = whatsappErr;
        }
        if (Object.keys(next).length > 0) {
            setClientErrors(next);
            return;
        }
        setClientErrors({});
        post(route('register.seller.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    const title = useMemo(
        () =>
            accountType === 'company'
                ? t.seller_company_title
                : t.seller_individual_title,
        [accountType, t],
    );

    return (
        <AuthLayout
            title={title}
            subtitle={t.seller_subtitle}
            wide
            backHref={route('register')}
        >
            <Head title={title} />

            <div className="mb-6 grid gap-3 sm:grid-cols-2">
                <button
                    type="button"
                    onClick={() => switchType('company')}
                    className={`rounded-xl border px-4 py-3 text-left transition ${
                        accountType === 'company'
                            ? 'border-rml-primary bg-rml-primary-lighter'
                            : 'border-rml-border bg-white hover:border-rml-primary/40'
                    }`}
                >
                    <p className="text-sm font-semibold text-rml-text">
                        {t.seller_type_company}
                    </p>
                    <p className="mt-1 text-xs text-rml-muted">
                        {t.seller_type_company_hint}
                    </p>
                </button>
                <button
                    type="button"
                    onClick={() => switchType('individual')}
                    className={`rounded-xl border px-4 py-3 text-left transition ${
                        accountType === 'individual'
                            ? 'border-rml-primary bg-rml-primary-lighter'
                            : 'border-rml-border bg-white hover:border-rml-primary/40'
                    }`}
                >
                    <p className="text-sm font-semibold text-rml-text">
                        {t.seller_type_individual}
                    </p>
                    <p className="mt-1 text-xs text-rml-muted">
                        {t.seller_type_individual_hint}
                    </p>
                </button>
            </div>

            <Alert variant="info" className="mb-5">
                {t.seller_staff_note}
            </Alert>

            <form onSubmit={submit} className="space-y-4">
                {accountType === 'company' && (
                    <FormInput
                        label={t.company_name}
                        name="company_name"
                        value={data.company_name}
                        required
                        error={errors.company_name}
                        onChange={(e) => setData('company_name', e.target.value)}
                    />
                )}

                <div className="grid gap-4 sm:grid-cols-2">
                    <FormInput
                        label={
                            accountType === 'company'
                                ? t.contact_name
                                : t.full_name
                        }
                        name="name"
                        value={data.name}
                        required
                        error={errors.name}
                        onChange={(e) => setData('name', e.target.value)}
                    />
                    <FormInput
                        label={t.email}
                        type="email"
                        name="email"
                        value={data.email}
                        required
                        autoComplete="username"
                        error={errors.email}
                        onChange={(e) => setData('email', e.target.value)}
                    />
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <FormInput
                        label={t.phone}
                        name="phone"
                        type="tel"
                        inputMode="tel"
                        autoComplete="tel"
                        value={data.phone}
                        required
                        error={fieldError('phone')}
                        hint={
                            fieldError('phone')
                                ? undefined
                                : translations.validation?.phone_length
                        }
                        onChange={(e) => {
                            const value = sanitizePhoneInput(e.target.value);
                            setData('phone', value);
                            const msg = phoneErrorMessage(
                                value,
                                phoneMessages,
                                { required: true },
                            );
                            setClientErrors((prev) => {
                                const next = { ...prev };
                                if (msg) {
                                    next.phone = msg;
                                } else {
                                    delete next.phone;
                                }
                                return next;
                            });
                        }}
                    />
                    <FormInput
                        label={t.whatsapp}
                        name="whatsapp"
                        type="tel"
                        inputMode="tel"
                        value={data.whatsapp}
                        error={fieldError('whatsapp')}
                        onChange={(e) => {
                            const value = sanitizePhoneInput(e.target.value);
                            setData('whatsapp', value);
                            const msg = phoneErrorMessage(
                                value,
                                phoneMessages,
                                { required: false },
                            );
                            setClientErrors((prev) => {
                                const next = { ...prev };
                                if (msg) {
                                    next.whatsapp = msg;
                                } else {
                                    delete next.whatsapp;
                                }
                                return next;
                            });
                        }}
                    />
                </div>

                <FormInput
                    label={t.address}
                    name="address"
                    value={data.address}
                    required
                    error={errors.address}
                    onChange={(e) => setData('address', e.target.value)}
                />

                <div className="grid gap-4 sm:grid-cols-3">
                    <FormInput
                        label={t.city}
                        name="city"
                        value={data.city}
                        required
                        error={errors.city}
                        onChange={(e) => setData('city', e.target.value)}
                    />
                    <FormInput
                        label={t.postcode}
                        name="postcode"
                        value={data.postcode}
                        required
                        error={errors.postcode}
                        onChange={(e) => setData('postcode', e.target.value)}
                    />
                    <CountrySelect
                        label={t.country}
                        name="country"
                        required
                        value={data.country}
                        error={errors.country}
                        onChange={(e) => setData('country', e.target.value)}
                    />
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <FormInput
                        label={t.password}
                        type="password"
                        name="password"
                        value={data.password}
                        required
                        autoComplete="new-password"
                        error={errors.password}
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    <FormInput
                        label={t.password_confirm}
                        type="password"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        required
                        autoComplete="new-password"
                        onChange={(e) =>
                            setData('password_confirmation', e.target.value)
                        }
                    />
                </div>

                <FileUpload
                    label={t.documents_optional}
                    hint={t.documents_hint}
                    accept=".pdf,.jpg,.jpeg,.png"
                />

                <Checkbox
                    name="agreement"
                    checked={data.agreement}
                    onChange={(e) => setData('agreement', e.target.checked)}
                    label={t.agreement}
                />
                {errors.agreement && (
                    <p className="text-sm text-rml-red">{errors.agreement}</p>
                )}

                <Checkbox
                    name="gdpr"
                    checked={data.gdpr}
                    onChange={(e) => setData('gdpr', e.target.checked)}
                    label={t.gdpr}
                />
                {errors.gdpr && (
                    <p className="text-sm text-rml-red">{errors.gdpr}</p>
                )}

                <Button type="submit" fullWidth disabled={processing}>
                    {t.submit_seller}
                </Button>

                <p className="text-center text-sm text-rml-muted">
                    {translations.register.have_account}{' '}
                    <Link
                        href={route('login')}
                        className="font-semibold text-rml-primary hover:underline"
                    >
                        {translations.nav.sign_in}
                    </Link>
                </p>
            </form>
        </AuthLayout>
    );
}
