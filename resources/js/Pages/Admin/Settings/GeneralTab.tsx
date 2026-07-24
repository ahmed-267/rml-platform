import { ReactNode, useEffect, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import {
    Button,
    FormInput,
    Modal,
    Textarea,
} from '@/Components/ui';
import { cn } from '@/lib/cn';
import {
    AGREEMENT_TYPES,
    GDPR_TYPES,
    TERMS_TYPES,
    type TemplateRow,
} from './types';

function filterTemplates(templates: TemplateRow[], types: readonly string[]) {
    return templates.filter((tpl) => tpl.type && types.includes(tpl.type));
}

function truncate(value: string | null | undefined, max = 48): string {
    if (!value) {
        return '';
    }
    return value.length > max ? `${value.slice(0, max)}…` : value;
}

function AccordionSection({
    id,
    title,
    open,
    onToggle,
    children,
    defaultCompact = true,
}: {
    id: string;
    title: string;
    open: boolean;
    onToggle: () => void;
    children: ReactNode;
    defaultCompact?: boolean;
}) {
    const padding = defaultCompact ? 'px-3 py-2' : 'px-4 py-3';

    return (
        <div className="rounded-lg border border-rml-border bg-white">
            <button
                type="button"
                id={`${id}-header`}
                aria-expanded={open}
                aria-controls={`${id}-panel`}
                className={cn(
                    'flex w-full items-center justify-between gap-2 text-left',
                    padding,
                )}
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
                    className={cn('border-t border-rml-border', padding)}
                >
                    {children}
                </div>
            )}
        </div>
    );
}

function TemplateListSection({
    id,
    title,
    templates,
    templateTypes,
    open,
    onToggle,
    onEdit,
    t,
    common,
}: {
    id: string;
    title: string;
    templates: TemplateRow[];
    templateTypes: Record<string, string>;
    open: boolean;
    onToggle: () => void;
    onEdit: (template: TemplateRow) => void;
    t: Record<string, string>;
    common: Record<string, string>;
}) {
    if (templates.length === 0) {
        return null;
    }

    return (
        <AccordionSection id={id} title={title} open={open} onToggle={onToggle}>
            <div>
                {templates.map((template) => {
                    const typeLabel =
                        template.type && templateTypes[template.type]
                            ? templateTypes[template.type]
                            : template.type ?? '';
                    const subtitle =
                        template.active_version != null
                            ? `${t.version}: ${template.active_version}`
                            : truncate(template.description);

                    return (
                        <div
                            key={template.id}
                            className="flex items-center justify-between gap-3 border-b border-rml-border py-2 last:border-0"
                        >
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-medium text-rml-text">
                                    {template.name}
                                </p>
                                <p className="truncate text-xs text-rml-muted">
                                    {subtitle || typeLabel}
                                </p>
                            </div>
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={() => onEdit(template)}
                            >
                                {common.edit}
                            </Button>
                        </div>
                    );
                })}
            </div>
        </AccordionSection>
    );
}

function TemplateEditModal({
    template,
    typeLabel,
    open,
    onClose,
    t,
    common,
}: {
    template: TemplateRow | null;
    typeLabel: string;
    open: boolean;
    onClose: () => void;
    t: Record<string, string>;
    common: Record<string, string>;
}) {
    const form = useForm({
        name: '',
        description: '',
        content: '',
        version: '',
    });

    useEffect(() => {
        if (!template) {
            return;
        }
        form.setData({
            name: template.name,
            description: template.description ?? '',
            content: template.content ?? '',
            version: '',
        });
        form.clearErrors();
    }, [template?.id]);

    const save = () => {
        if (!template) {
            return;
        }
        form.put(route('admin.settings.templates.update', template.id), {
            onSuccess: () => onClose(),
        });
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={template?.name ?? t.templates}
            description={typeLabel || undefined}
            size="lg"
            footer={
                <>
                    <Button variant="ghost" size="sm" onClick={onClose}>
                        {common.cancel}
                    </Button>
                    <Button size="sm" onClick={save} disabled={form.processing}>
                        {common.save}
                    </Button>
                </>
            }
        >
            {template && (
                <div className="grid gap-3 sm:grid-cols-2">
                    <FormInput
                        label={common.name}
                        value={form.data.name}
                        error={form.errors.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                    />
                    <FormInput
                        label={t.version}
                        value={form.data.version}
                        error={form.errors.version}
                        onChange={(e) =>
                            form.setData('version', e.target.value)
                        }
                        hint={
                            template.active_version
                                ? `${t.version}: ${template.active_version}`
                                : undefined
                        }
                    />
                    <Textarea
                        label={common.description}
                        value={form.data.description}
                        error={form.errors.description}
                        onChange={(e) =>
                            form.setData('description', e.target.value)
                        }
                        rows={2}
                        className="sm:col-span-2"
                    />
                    <Textarea
                        label={t.content}
                        value={form.data.content}
                        error={form.errors.content}
                        onChange={(e) =>
                            form.setData('content', e.target.value)
                        }
                        rows={6}
                        className="sm:col-span-2"
                    />
                </div>
            )}
        </Modal>
    );
}

export default function GeneralTab({
    general,
    templates,
    t,
    common,
    templateTypes,
}: {
    general: Record<string, string | null>;
    templates: TemplateRow[];
    t: Record<string, string>;
    common: Record<string, string>;
    templateTypes: Record<string, string>;
}) {
    const [platformOpen, setPlatformOpen] = useState(true);
    const [agreementsOpen, setAgreementsOpen] = useState(false);
    const [termsOpen, setTermsOpen] = useState(false);
    const [gdprOpen, setGdprOpen] = useState(false);
    const [editingTemplate, setEditingTemplate] = useState<TemplateRow | null>(
        null,
    );

    const generalForm = useForm({
        support_email: general.support_email ?? '',
        support_phone: general.support_phone ?? '',
        default_locale: general.default_locale ?? 'en',
        default_currency: general.default_currency ?? 'EUR',
        bank_transfer_instructions: general.bank_transfer_instructions ?? '',
        company_name: general.company_name ?? '',
    });

    const saveGeneral = () => {
        generalForm.put(route('admin.settings.general.update'));
    };

    const editingTypeLabel =
        editingTemplate?.type && templateTypes[editingTemplate.type]
            ? templateTypes[editingTemplate.type]
            : editingTemplate?.type ?? '';

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
                    <FormInput
                        label={t.default_locale}
                        value={generalForm.data.default_locale}
                        error={generalForm.errors.default_locale}
                        onChange={(e) =>
                            generalForm.setData(
                                'default_locale',
                                e.target.value,
                            )
                        }
                    />
                    <FormInput
                        label={t.default_currency}
                        value={generalForm.data.default_currency}
                        error={generalForm.errors.default_currency}
                        onChange={(e) =>
                            generalForm.setData(
                                'default_currency',
                                e.target.value,
                            )
                        }
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

            <TemplateListSection
                id="agreements"
                title={t.section_agreements}
                templates={filterTemplates(templates, AGREEMENT_TYPES)}
                templateTypes={templateTypes}
                open={agreementsOpen}
                onToggle={() => setAgreementsOpen((prev) => !prev)}
                onEdit={setEditingTemplate}
                t={t}
                common={common}
            />

            <TemplateListSection
                id="terms"
                title={t.section_terms}
                templates={filterTemplates(templates, TERMS_TYPES)}
                templateTypes={templateTypes}
                open={termsOpen}
                onToggle={() => setTermsOpen((prev) => !prev)}
                onEdit={setEditingTemplate}
                t={t}
                common={common}
            />

            <TemplateListSection
                id="gdpr"
                title={t.section_gdpr}
                templates={filterTemplates(templates, GDPR_TYPES)}
                templateTypes={templateTypes}
                open={gdprOpen}
                onToggle={() => setGdprOpen((prev) => !prev)}
                onEdit={setEditingTemplate}
                t={t}
                common={common}
            />

            <TemplateEditModal
                template={editingTemplate}
                typeLabel={editingTypeLabel}
                open={editingTemplate != null}
                onClose={() => setEditingTemplate(null)}
                t={t}
                common={common}
            />
        </div>
    );
}
