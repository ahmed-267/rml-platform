import { useState, type ReactNode } from 'react';
import { useForm } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { Button, FormInput, Select, Textarea } from '@/Components/ui';
import { cn } from '@/lib/cn';

const LOCALE_OPTIONS = [
    { label: '🇬🇧 English', value: 'en' },
    { label: '🇪🇸 Español', value: 'es' },
    { label: '🇫🇷 Français', value: 'fr' },
];

const CURRENCY_OPTIONS = [
    { label: 'EUR €', value: 'EUR' },
    { label: 'GBP £', value: 'GBP' },
    { label: 'USD $', value: 'USD' },
];

function AccordionSection({
    id,
    title,
    open,
    onToggle,
    children,
}: {
    id: string;
    title: string;
    open: boolean;
    onToggle: () => void;
    children: ReactNode;
}) {
    return (
        <div className="rounded-lg border border-rml-border bg-white">
            <button
                type="button"
                id={`${id}-header`}
                aria-expanded={open}
                aria-controls={`${id}-panel`}
                className="flex w-full items-center justify-between gap-2 px-3 py-2 text-left"
                onClick={onToggle}
            >
                <span className="text-sm font-semibold text-rml-text">{title}</span>
                <ChevronDown
                    className={cn(
                        'h-4 w-4 shrink-0 text-rml-muted transition-transform',
                        open && 'rotate-180',
                    )}
                />
            </button>
            {open && (
                <div
                    id={`${id}-panel`}
                    className="border-t border-rml-border px-3 py-2"
                >
                    {children}
                </div>
            )}
        </div>
    );
}

export default function GeneralTab({
    general,
    t,
    common,
}: {
    general: Record<string, string | null>;
    t: Record<string, string>;
    common: Record<string, string>;
}) {
    const [platformOpen, setPlatformOpen] = useState(true);

    const generalForm = useForm({
        support_email: general.support_email ?? '',
        support_phone: general.support_phone ?? '',
        default_locale: general.default_locale ?? 'en',
        default_currency: (general.default_currency ?? 'EUR').toUpperCase(),
        bank_transfer_instructions: general.bank_transfer_instructions ?? '',
        company_name: general.company_name ?? '',
    });

    const saveGeneral = () => {
        generalForm.put(route('admin.settings.general.update'));
    };

    return (
        <div className="space-y-3">
            <AccordionSection
                id="platform"
                title={t.platform_settings}
                open={platformOpen}
                onToggle={() => setPlatformOpen((prev) => !prev)}
            >
                <div className="grid gap-3 sm:grid-cols-2">
                    <FormInput
                        label={t.support_email}
                        value={generalForm.data.support_email}
                        error={generalForm.errors.support_email}
                        onChange={(e) =>
                            generalForm.setData('support_email', e.target.value)
                        }
                    />
                    <FormInput
                        label={t.support_phone}
                        value={generalForm.data.support_phone}
                        error={generalForm.errors.support_phone}
                        onChange={(e) =>
                            generalForm.setData('support_phone', e.target.value)
                        }
                    />
                    <Select
                        label={t.default_language}
                        value={generalForm.data.default_locale}
                        error={generalForm.errors.default_locale}
                        onChange={(e) =>
                            generalForm.setData(
                                'default_locale',
                                e.target.value,
                            )
                        }
                        options={LOCALE_OPTIONS}
                    />
                    <Select
                        label={t.default_currency}
                        value={generalForm.data.default_currency}
                        error={generalForm.errors.default_currency}
                        onChange={(e) =>
                            generalForm.setData(
                                'default_currency',
                                e.target.value,
                            )
                        }
                        options={CURRENCY_OPTIONS}
                    />
                    <FormInput
                        label={t.company_name}
                        value={generalForm.data.company_name}
                        error={generalForm.errors.company_name}
                        onChange={(e) =>
                            generalForm.setData('company_name', e.target.value)
                        }
                        className="sm:col-span-2"
                    />
                    <Textarea
                        label={t.bank_transfer_instructions}
                        value={generalForm.data.bank_transfer_instructions}
                        error={generalForm.errors.bank_transfer_instructions}
                        onChange={(e) =>
                            generalForm.setData(
                                'bank_transfer_instructions',
                                e.target.value,
                            )
                        }
                        rows={3}
                        className="sm:col-span-2"
                    />
                </div>
                <div className="pt-2">
                    <Button
                        size="sm"
                        onClick={saveGeneral}
                        disabled={generalForm.processing}
                    >
                        {common.save}
                    </Button>
                </div>
            </AccordionSection>
        </div>
    );
}
