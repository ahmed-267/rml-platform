import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, Building2, Home, ShoppingBag } from 'lucide-react';
import AuthLayout from '@/Layouts/AuthLayout';
import { Button } from '@/Components/ui/Button';
import type { PageProps } from '@/types';

export default function Register() {
    const { translations } = usePage<PageProps>().props;
    const t = translations.register;

    return (
        <AuthLayout title={t.title} subtitle={t.subtitle} wide>
            <Head title={t.title} />

            <div className="grid gap-4 sm:grid-cols-2">
                <Link
                    href={route('register.seller')}
                    className="group flex h-full flex-col rounded-xl border border-rml-border bg-white p-5 shadow-card transition hover:border-rml-primary hover:shadow-md"
                >
                    <div className="mb-4 flex h-11 w-11 items-center justify-center rounded-lg bg-rml-primary-light text-rml-primary">
                        <Building2 className="h-5 w-5" />
                    </div>
                    <h2 className="text-base font-semibold text-rml-text">
                        {t.sell_title}
                    </h2>
                    <p className="mt-2 flex-1 text-sm text-rml-muted">
                        {t.sell_body}
                    </p>
                    <span className="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-rml-primary">
                        {translations.common.continue ?? 'Continue'}
                        <ArrowRight className="h-4 w-4 transition group-hover:translate-x-0.5" />
                    </span>
                </Link>

                <Link
                    href={route('register.buyer')}
                    className="group flex h-full flex-col rounded-xl border border-rml-border bg-white p-5 shadow-card transition hover:border-rml-primary hover:shadow-md"
                >
                    <div className="mb-4 flex h-11 w-11 items-center justify-center rounded-lg bg-rml-blue-light text-rml-blue">
                        <ShoppingBag className="h-5 w-5" />
                    </div>
                    <h2 className="text-base font-semibold text-rml-text">
                        {t.buy_title}
                    </h2>
                    <p className="mt-2 flex-1 text-sm text-rml-muted">
                        {t.buy_body}
                    </p>
                    <span className="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-rml-primary">
                        {translations.common.continue ?? 'Continue'}
                        <ArrowRight className="h-4 w-4 transition group-hover:translate-x-0.5" />
                    </span>
                </Link>
            </div>

            <div className="mt-5 rounded-xl border border-dashed border-rml-border bg-rml-background px-4 py-4">
                <div className="flex items-start gap-3">
                    <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-rml-muted ring-1 ring-rml-border">
                        <Home className="h-4 w-4" />
                    </div>
                    <div className="min-w-0">
                        <p className="text-sm text-rml-text">{t.homeowner}</p>
                        <Link
                            href="/#free-installation"
                            className="mt-1 inline-flex text-sm font-semibold text-rml-primary hover:underline"
                        >
                            {t.homeowner_cta}
                        </Link>
                    </div>
                </div>
            </div>

            <div className="mt-6 flex flex-col items-center gap-3 border-t border-rml-border pt-6 sm:flex-row sm:justify-between">
                <p className="text-sm text-rml-muted">
                    {t.have_account}{' '}
                    <Link
                        href={route('login')}
                        className="font-semibold text-rml-primary hover:underline"
                    >
                        {translations.nav.sign_in}
                    </Link>
                </p>
                <Link href={route('login')}>
                    <Button variant="outline" size="sm">
                        {translations.nav.sign_in}
                    </Button>
                </Link>
            </div>
        </AuthLayout>
    );
}
