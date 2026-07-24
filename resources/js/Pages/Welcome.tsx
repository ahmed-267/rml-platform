import { FormEvent, useMemo } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import LandingBuyerSellerSplit from '@/Components/landing/LandingBuyerSellerSplit';
import LandingFaq from '@/Components/landing/LandingFaq';
import LandingFinalCta from '@/Components/landing/LandingFinalCta';
import LandingHero from '@/Components/landing/LandingHero';
import LandingLifecycle from '@/Components/landing/LandingLifecycle';
import LandingMarketplaceReveal from '@/Components/landing/LandingMarketplaceReveal';
import LandingProductPreview from '@/Components/landing/LandingProductPreview';
import LandingSpainSchemes from '@/Components/landing/LandingSpainSchemes';
import LandingTrustSection from '@/Components/landing/LandingTrustSection';
import RmlLogo from '@/Components/branding/RmlLogo';
import { Button } from '@/Components/ui/Button';
import { Checkbox } from '@/Components/ui/Checkbox';
import { FormInput } from '@/Components/ui/FormInput';
import { Select } from '@/Components/ui/Select';
import { Textarea } from '@/Components/ui/Textarea';
import ScrollReveal from '@/Components/landing/ScrollReveal';
import PublicLayout from '@/Layouts/PublicLayout';
import type { PageProps } from '@/types';

export default function Welcome({
    canLogin: _canLogin,
    canRegister: _canRegister,
}: {
    canLogin: boolean;
    canRegister: boolean;
}) {
    const { translations } = usePage<PageProps>().props;
    const t = translations.landing;
    const nav = translations.nav;

    const homeowner = useForm({
        full_name: '',
        phone: '',
        email: '',
        property_address: '',
        postcode: '',
        service: '',
        message: '',
        consent: false as boolean,
    });

    const contact = useForm({
        name: '',
        email: '',
        enquiry_type: '',
        subject: '',
        message: '',
    });

    const footerNav = useMemo(
        () => [
            { label: nav.home, href: '#home' },
            { label: nav.buy_leads, href: '#buy-leads' },
            { label: nav.sell_leads, href: '#sell-leads' },
            { label: nav.free_installation, href: '#free-installation' },
            { label: nav.contact, href: '#contact' },
        ],
        [nav],
    );

    const submitHomeowner = (event: FormEvent) => {
        event.preventDefault();
        homeowner.post(route('enquiries.homeowner'), {
            preserveScroll: true,
            onSuccess: () => homeowner.reset(),
        });
    };

    const submitContact = (event: FormEvent) => {
        event.preventDefault();
        contact.post(route('enquiries.contact'), {
            preserveScroll: true,
            onSuccess: () => contact.reset(),
        });
    };

    return (
        <PublicLayout>
            <Head title={t.meta_title} />

            <LandingHero t={t} />
            <LandingMarketplaceReveal t={t} />
            <LandingLifecycle t={t} />
            <LandingBuyerSellerSplit t={t} />
            <LandingTrustSection t={t} />
            <LandingProductPreview t={t} />
            <LandingSpainSchemes t={t} />

            <section
                id="free-installation"
                className="scroll-mt-20 bg-rml-primary-lighter py-16 sm:py-20"
                aria-labelledby="install-title"
            >
                <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                    <ScrollReveal className="mb-8 text-center">
                        <h2
                            id="install-title"
                            className="text-3xl font-bold tracking-tight text-rml-text sm:text-4xl"
                        >
                            {t.install_title}
                        </h2>
                        <p className="mt-3 text-rml-muted">
                            {t.install_subtitle}
                        </p>
                    </ScrollReveal>

                    <ScrollReveal>
                        <form
                            onSubmit={submitHomeowner}
                            className="rml-card space-y-4 p-6 sm:p-8"
                        >
                            <h3 className="text-base font-bold text-rml-text">
                                {t.install_form_title}
                            </h3>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <FormInput
                                    label={t.install_full_name}
                                    required
                                    value={homeowner.data.full_name}
                                    onChange={(e) =>
                                        homeowner.setData(
                                            'full_name',
                                            e.target.value,
                                        )
                                    }
                                    error={homeowner.errors.full_name}
                                />
                                <FormInput
                                    label={t.install_phone}
                                    required
                                    value={homeowner.data.phone}
                                    onChange={(e) =>
                                        homeowner.setData(
                                            'phone',
                                            e.target.value,
                                        )
                                    }
                                    error={homeowner.errors.phone}
                                />
                            </div>
                            <FormInput
                                label={t.install_email}
                                type="email"
                                required
                                value={homeowner.data.email}
                                onChange={(e) =>
                                    homeowner.setData('email', e.target.value)
                                }
                                error={homeowner.errors.email}
                            />
                            <FormInput
                                label={t.install_address}
                                required
                                value={homeowner.data.property_address}
                                onChange={(e) =>
                                    homeowner.setData(
                                        'property_address',
                                        e.target.value,
                                    )
                                }
                                error={homeowner.errors.property_address}
                            />
                            <div className="grid gap-4 sm:grid-cols-2">
                                <FormInput
                                    label={t.install_postcode}
                                    required
                                    value={homeowner.data.postcode}
                                    onChange={(e) =>
                                        homeowner.setData(
                                            'postcode',
                                            e.target.value,
                                        )
                                    }
                                    error={homeowner.errors.postcode}
                                />
                                <Select
                                    label={t.install_service}
                                    required
                                    value={homeowner.data.service}
                                    onChange={(e) =>
                                        homeowner.setData(
                                            'service',
                                            e.target.value,
                                        )
                                    }
                                    placeholder={t.install_service_placeholder}
                                    options={[
                                        {
                                            label: t.scheme_insulation_title,
                                            value: 'insulation',
                                        },
                                        {
                                            label: t.scheme_glazing_title,
                                            value: 'double_glazing',
                                        },
                                        {
                                            label: t.scheme_heat_title,
                                            value: 'heat_pumps',
                                        },
                                    ]}
                                    error={homeowner.errors.service}
                                />
                            </div>
                            <Textarea
                                label={t.install_message}
                                value={homeowner.data.message}
                                onChange={(e) =>
                                    homeowner.setData('message', e.target.value)
                                }
                                error={homeowner.errors.message}
                                placeholder={t.install_message_placeholder}
                            />
                            <Checkbox
                                checked={homeowner.data.consent}
                                onChange={(e) =>
                                    homeowner.setData(
                                        'consent',
                                        e.target.checked,
                                    )
                                }
                                label={t.install_consent}
                                error={homeowner.errors.consent}
                            />
                            <Button
                                type="submit"
                                fullWidth
                                disabled={homeowner.processing}
                            >
                                {t.install_submit}
                            </Button>
                        </form>
                    </ScrollReveal>
                </div>
            </section>

            <LandingFaq t={t} />
            <LandingFinalCta t={t} />

            <section
                id="contact"
                className="scroll-mt-20 bg-white py-16 sm:py-20"
                aria-labelledby="contact-title"
            >
                <div className="mx-auto max-w-content px-4 sm:px-6 lg:px-8">
                    <ScrollReveal className="mb-8 max-w-2xl">
                        <h2
                            id="contact-title"
                            className="text-3xl font-bold tracking-tight text-rml-text sm:text-4xl"
                        >
                            {t.contact_title}
                        </h2>
                        <p className="mt-3 text-rml-muted">
                            {t.contact_subtitle}
                        </p>
                    </ScrollReveal>

                    <div className="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
                        <ScrollReveal>
                            <form
                                onSubmit={submitContact}
                                className="rml-card space-y-4 p-6 sm:p-8"
                            >
                                <h3 className="text-base font-bold text-rml-text">
                                    {t.contact_form_title}
                                </h3>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <FormInput
                                        label={t.contact_name}
                                        required
                                        value={contact.data.name}
                                        onChange={(e) =>
                                            contact.setData(
                                                'name',
                                                e.target.value,
                                            )
                                        }
                                        error={contact.errors.name}
                                    />
                                    <FormInput
                                        label={t.contact_email}
                                        type="email"
                                        required
                                        value={contact.data.email}
                                        onChange={(e) =>
                                            contact.setData(
                                                'email',
                                                e.target.value,
                                            )
                                        }
                                        error={contact.errors.email}
                                    />
                                </div>
                                <Select
                                    label={t.contact_type}
                                    required
                                    value={contact.data.enquiry_type}
                                    onChange={(e) =>
                                        contact.setData(
                                            'enquiry_type',
                                            e.target.value,
                                        )
                                    }
                                    placeholder={t.contact_type_placeholder}
                                    options={[
                                        {
                                            label: t.contact_type_general,
                                            value: 'general',
                                        },
                                        {
                                            label: t.contact_type_buyer,
                                            value: 'buyer',
                                        },
                                        {
                                            label: t.contact_type_seller,
                                            value: 'seller',
                                        },
                                        {
                                            label: t.contact_type_homeowner,
                                            value: 'homeowner',
                                        },
                                        {
                                            label: t.contact_type_support,
                                            value: 'support',
                                        },
                                    ]}
                                    error={contact.errors.enquiry_type}
                                />
                                <FormInput
                                    label={t.contact_subject}
                                    required
                                    value={contact.data.subject}
                                    onChange={(e) =>
                                        contact.setData(
                                            'subject',
                                            e.target.value,
                                        )
                                    }
                                    error={contact.errors.subject}
                                />
                                <Textarea
                                    label={t.contact_message}
                                    required
                                    value={contact.data.message}
                                    onChange={(e) =>
                                        contact.setData(
                                            'message',
                                            e.target.value,
                                        )
                                    }
                                    error={contact.errors.message}
                                />
                                <Button
                                    type="submit"
                                    fullWidth
                                    disabled={contact.processing}
                                >
                                    {t.contact_submit}
                                </Button>
                            </form>
                        </ScrollReveal>

                        <ScrollReveal delayMs={80}>
                            <div className="rml-card p-6">
                                <h3 className="mb-3 text-sm font-bold text-rml-text">
                                    {t.contact_details_title}
                                </h3>
                                <ul className="space-y-2 text-sm text-rml-muted">
                                    <li>info@rmlplatform.com</li>
                                    <li>{t.contact_whatsapp}</li>
                                    <li>{t.contact_spain}</li>
                                </ul>
                            </div>
                        </ScrollReveal>
                    </div>
                </div>
            </section>

            <footer
                id="terms"
                className="border-t border-slate-800 bg-rml-sidebar text-slate-300"
            >
                <div className="mx-auto grid max-w-content gap-8 px-4 py-10 sm:px-6 lg:grid-cols-[1.2fr_1fr_1fr] lg:px-8">
                    <div>
                        <div className="inline-flex items-center gap-2">
                            <RmlLogo variant="light" />
                            <div>
                                <div className="text-sm font-semibold text-white">
                                    {t.footer_brand}
                                </div>
                                <div className="text-xs text-slate-400">
                                    {t.footer_platform}
                                </div>
                            </div>
                        </div>
                        <p className="mt-4 max-w-sm text-sm leading-relaxed text-slate-400">
                            {t.footer_description}
                        </p>
                    </div>

                    <div>
                        <h4 className="text-sm font-semibold text-white">
                            {t.footer_navigate}
                        </h4>
                        <ul className="mt-3 space-y-2 text-sm">
                            {footerNav.map((link) => (
                                <li key={link.href}>
                                    <a
                                        href={link.href}
                                        className="hover:text-white"
                                    >
                                        {link.label}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </div>

                    <div>
                        <h4 className="text-sm font-semibold text-white">
                            {t.footer_legal}
                        </h4>
                        <ul className="mt-3 space-y-2 text-sm">
                            <li>
                                <a href="#terms" className="hover:text-white">
                                    {t.footer_terms}
                                </a>
                            </li>
                            <li id="privacy">
                                <a href="#privacy" className="hover:text-white">
                                    {t.footer_privacy}
                                </a>
                            </li>
                            <li id="gdpr">
                                <a href="#gdpr" className="hover:text-white">
                                    {t.footer_gdpr}
                                </a>
                            </li>
                        </ul>
                        <p className="mt-4 text-xs text-slate-500">
                            {t.footer_languages}
                        </p>
                    </div>
                </div>
                <div className="border-t border-white/10">
                    <div className="mx-auto flex max-w-content flex-col gap-2 px-4 py-4 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                        <span>
                            © {new Date().getFullYear()} {t.footer_copyright}
                        </span>
                        <span>{t.footer_tagline}</span>
                    </div>
                </div>
            </footer>
        </PublicLayout>
    );
}
