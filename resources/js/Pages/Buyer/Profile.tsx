import { FormEvent } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, Checkbox, FormInput, StatusBadge } from '@/Components/ui';
import { leadStatusLabel, leadStatusTone } from '@/lib/lead-status';
import type { PageProps } from '@/types';

interface CompanyProfile {
    id: number;
    name: string;
    contact_name: string | null;
    email: string | null;
    phone: string | null;
    whatsapp: string | null;
    address: string | null;
    city: string | null;
    postcode: string | null;
    country: string | null;
    approval_status: string | null;
}

interface BuyerProfileProps {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    locale: string | null;
    approval_status: string | null;
    role: string | null;
    company: CompanyProfile | null;
    services_offered: string[];
    preferred_zones: string[];
    max_distance_km: number | null;
    billing_status: string | null;
}

const SERVICE_OPTIONS = [
    { value: 'insulation', labelKey: 'service_insulation' },
    { value: 'double_glazing', labelKey: 'service_double_glazing' },
    { value: 'heat_pumps', labelKey: 'service_heat_pumps' },
] as const;

const ZONE_OPTIONS = ['D1', 'D2', 'E1', 'E2'];

export default function BuyerProfile({
    profile,
}: {
    profile: BuyerProfileProps;
}) {
    const { translations } = usePage<PageProps>().props;
    const t = translations.buyer?.profile ?? {};
    const statuses = translations.statuses ?? {};
    const roles = translations.roles ?? {};

    const {
        data,
        setData,
        put,
        processing,
        errors,
        recentlySuccessful,
    } = useForm({
        name: profile.name ?? '',
        phone: profile.phone ?? '',
        company_name: profile.company?.name ?? '',
        contact_name: profile.company?.contact_name ?? '',
        company_email: profile.company?.email ?? '',
        company_phone: profile.company?.phone ?? '',
        whatsapp: profile.company?.whatsapp ?? '',
        address: profile.company?.address ?? '',
        city: profile.company?.city ?? '',
        postcode: profile.company?.postcode ?? '',
        country: profile.company?.country ?? 'ES',
        services_offered: profile.services_offered ?? [],
        preferred_zones: profile.preferred_zones ?? [],
        max_distance_km: profile.max_distance_km ?? '',
    });

    const toggleArrayValue = (
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

    const submit = (event: FormEvent) => {
        event.preventDefault();
        put(route('buyer.profile.update'));
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
                    {profile.billing_status && (
                        <div>
                            <dt className="text-rml-muted">
                                {t.billing_status}
                            </dt>
                            <dd className="font-medium text-rml-text">
                                {profile.billing_status}
                            </dd>
                        </div>
                    )}
                </dl>

                <form
                    onSubmit={submit}
                    className="grid grid-cols-1 gap-4 sm:grid-cols-2"
                >
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
                        value={profile.email}
                        disabled
                    />
                    <FormInput
                        label={t.phone}
                        name="phone"
                        value={data.phone}
                        error={errors.phone}
                        onChange={(e) => setData('phone', e.target.value)}
                    />
                    <FormInput
                        label={t.max_distance}
                        name="max_distance_km"
                        type="number"
                        value={String(data.max_distance_km)}
                        error={errors.max_distance_km}
                        onChange={(e) =>
                            setData('max_distance_km', e.target.value)
                        }
                    />

                    <div className="space-y-2 sm:col-span-2">
                        <p className="text-sm font-medium text-rml-text">
                            {t.services}
                        </p>
                        <div className="flex flex-wrap gap-4">
                            {SERVICE_OPTIONS.map((service) => (
                                <Checkbox
                                    key={service.value}
                                    label={
                                        t[service.labelKey] ?? service.value
                                    }
                                    checked={data.services_offered.includes(
                                        service.value,
                                    )}
                                    onChange={() =>
                                        toggleArrayValue(
                                            'services_offered',
                                            service.value,
                                        )
                                    }
                                />
                            ))}
                        </div>
                        {errors.services_offered && (
                            <p className="text-sm text-rml-red">
                                {errors.services_offered}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2 sm:col-span-2">
                        <p className="text-sm font-medium text-rml-text">
                            {t.preferred_zones}
                        </p>
                        <div className="flex flex-wrap gap-4">
                            {ZONE_OPTIONS.map((zone) => (
                                <Checkbox
                                    key={zone}
                                    label={zone}
                                    checked={data.preferred_zones.includes(
                                        zone,
                                    )}
                                    onChange={() =>
                                        toggleArrayValue(
                                            'preferred_zones',
                                            zone,
                                        )
                                    }
                                />
                            ))}
                        </div>
                        {errors.preferred_zones && (
                            <p className="text-sm text-rml-red">
                                {errors.preferred_zones}
                            </p>
                        )}
                    </div>

                    <div className="sm:col-span-2">
                        <h3 className="mb-4 text-base font-semibold text-rml-text">
                            {t.company}
                        </h3>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <FormInput
                                label={t.company_name}
                                name="company_name"
                                value={data.company_name}
                                error={errors.company_name}
                                onChange={(e) =>
                                    setData('company_name', e.target.value)
                                }
                            />
                            <FormInput
                                label={t.contact_name}
                                name="contact_name"
                                value={data.contact_name}
                                error={errors.contact_name}
                                onChange={(e) =>
                                    setData('contact_name', e.target.value)
                                }
                            />
                            <FormInput
                                label={t.email}
                                name="company_email"
                                type="email"
                                value={data.company_email}
                                error={errors.company_email}
                                onChange={(e) =>
                                    setData('company_email', e.target.value)
                                }
                            />
                            <FormInput
                                label={t.phone}
                                name="company_phone"
                                value={data.company_phone}
                                error={errors.company_phone}
                                onChange={(e) =>
                                    setData('company_phone', e.target.value)
                                }
                            />
                            <FormInput
                                label={t.whatsapp}
                                name="whatsapp"
                                value={data.whatsapp}
                                error={errors.whatsapp}
                                onChange={(e) =>
                                    setData('whatsapp', e.target.value)
                                }
                            />
                            <FormInput
                                label={t.address}
                                name="address"
                                value={data.address}
                                error={errors.address}
                                onChange={(e) =>
                                    setData('address', e.target.value)
                                }
                            />
                            <FormInput
                                label={t.city}
                                name="city"
                                value={data.city}
                                error={errors.city}
                                onChange={(e) =>
                                    setData('city', e.target.value)
                                }
                            />
                            <FormInput
                                label={t.postcode}
                                name="postcode"
                                value={data.postcode}
                                error={errors.postcode}
                                onChange={(e) =>
                                    setData('postcode', e.target.value)
                                }
                            />
                            <FormInput
                                label={t.country}
                                name="country"
                                value={data.country}
                                error={errors.country}
                                onChange={(e) =>
                                    setData('country', e.target.value)
                                }
                            />
                        </div>
                    </div>

                    <div className="flex items-center gap-3 sm:col-span-2">
                        <Button type="submit" disabled={processing}>
                            {t.save}
                        </Button>
                        {recentlySuccessful && (
                            <p className="text-sm text-rml-primary">
                                {t.saved}
                            </p>
                        )}
                    </div>
                </form>
            </section>
        </AppLayout>
    );
}
