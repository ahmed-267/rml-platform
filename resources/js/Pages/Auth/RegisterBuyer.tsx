import { FormEventHandler } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';
import { Button } from '@/Components/ui/Button';
import { Checkbox } from '@/Components/ui/Checkbox';
import { FormInput } from '@/Components/ui/FormInput';
import { CountrySelect } from '@/Components/ui/CountrySelect';
import { DEFAULT_COUNTRY } from '@/lib/countries';
import { sanitizePhoneInput } from '@/lib/phone';
import { useScrollToFirstError } from '@/hooks/use-scroll-to-first-error';
import type { PageProps } from '@/types';

const serviceOptions = [
    { value: 'insulation', labelKey: 'service_insulation' as const },
    { value: 'double_glazing', labelKey: 'service_glazing' as const },
    { value: 'heat_pumps', labelKey: 'service_heat_pumps' as const },
];

const zoneOptions = ['D1', 'D2', 'E1', 'E2'];

export default function RegisterBuyer() {
    const { translations } = usePage<PageProps>().props;
    const t = translations.auth;

    const { data, setData, post, processing, errors, reset } = useForm({
        company_name: '',
        name: '',
        email: '',
        phone: '',
        whatsapp: '',
        address: '',
        city: '',
        postcode: '',
        country: DEFAULT_COUNTRY,
        password: '',
        password_confirmation: '',
        services_offered: [] as string[],
        preferred_zones: [] as string[],
        max_distance_km: '' as string | number,
        agreement: false as boolean,
        gdpr: false as boolean,
    });

    useScrollToFirstError(errors);

    const toggleValue = (
        field: 'services_offered' | 'preferred_zones',
        value: string,
    ) => {
        const current = data[field];
        setData(
            field,
            current.includes(value)
                ? current.filter((item) => item !== value)
                : [...current, value],
        );
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('register.buyer.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <AuthLayout
            title={t.buyer_title}
            subtitle={t.buyer_subtitle}
            wide
            backHref={route('register')}
        >
            <Head title={t.buyer_title} />

            <form onSubmit={submit} className="space-y-4">
                <FormInput
                    label={t.company_name}
                    name="company_name"
                    value={data.company_name}
                    required
                    error={errors.company_name}
                    onChange={(e) => setData('company_name', e.target.value)}
                />

                <div className="grid gap-4 sm:grid-cols-2">
                    <FormInput
                        label={t.contact_name}
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
                        error={errors.phone}
                        hint={translations.validation?.phone_length}
                        onChange={(e) =>
                            setData('phone', sanitizePhoneInput(e.target.value))
                        }
                    />
                    <FormInput
                        label={t.whatsapp_optional}
                        name="whatsapp"
                        type="tel"
                        inputMode="tel"
                        value={data.whatsapp}
                        error={errors.whatsapp}
                        onChange={(e) =>
                            setData(
                                'whatsapp',
                                sanitizePhoneInput(e.target.value),
                            )
                        }
                    />
                </div>

                <FormInput
                    label={t.company_address}
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

                <div>
                    <p className="mb-2 text-sm font-medium text-rml-text">
                        {t.services_offered}
                        <span className="ml-0.5 text-rml-red">*</span>
                    </p>
                    <div className="grid gap-2 sm:grid-cols-3">
                        {serviceOptions.map((option) => (
                            <Checkbox
                                key={option.value}
                                name={`service_${option.value}`}
                                checked={data.services_offered.includes(
                                    option.value,
                                )}
                                onChange={() =>
                                    toggleValue(
                                        'services_offered',
                                        option.value,
                                    )
                                }
                                label={t[option.labelKey]}
                            />
                        ))}
                    </div>
                    {errors.services_offered && (
                        <p className="mt-1 text-sm text-rml-red">
                            {errors.services_offered}
                        </p>
                    )}
                </div>

                <div>
                    <p className="mb-2 text-sm font-medium text-rml-text">
                        {t.preferred_zones}
                        <span className="ml-0.5 text-rml-red">*</span>
                    </p>
                    <div className="flex flex-wrap gap-3">
                        {zoneOptions.map((zone) => (
                            <Checkbox
                                key={zone}
                                name={`zone_${zone}`}
                                checked={data.preferred_zones.includes(zone)}
                                onChange={() =>
                                    toggleValue('preferred_zones', zone)
                                }
                                label={zone}
                            />
                        ))}
                    </div>
                    {errors.preferred_zones && (
                        <p className="mt-1 text-sm text-rml-red">
                            {errors.preferred_zones}
                        </p>
                    )}
                </div>

                <FormInput
                    label={t.max_distance}
                    type="number"
                    name="max_distance_km"
                    value={String(data.max_distance_km)}
                    error={errors.max_distance_km}
                    onChange={(e) =>
                        setData(
                            'max_distance_km',
                            e.target.value === ''
                                ? ''
                                : Number(e.target.value),
                        )
                    }
                />

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
                    {t.submit_buyer}
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
