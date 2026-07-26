import { useMemo, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import {
    Button,
    DataTable,
    EmptyState,
    FormInput,
    MobileCardList,
    Modal,
    Select,
    StatusBadge,
    TableActionButton,
    TableActions,
    Textarea,
    tableActionIcons,
} from '@/Components/ui';
import { useIsMobile } from '@/hooks/use-media-query';
import {
    COMMISSION_APPLIES_TO,
    type CommissionAppliesTo,
    type CommissionRuleRow,
} from './types';

type CommissionFormData = {
    name: string;
    applies_to: string;
    rate_per_m2: string | number;
    active: boolean;
    notes: string;
};

function emptyCommissionForm(): CommissionFormData {
    return {
        name: '',
        applies_to: 'seller_company',
        rate_per_m2: '',
        active: true,
        notes: '',
    };
}

function ruleToForm(rule: CommissionRuleRow): CommissionFormData {
    return {
        name: rule.name,
        applies_to: rule.applies_to ?? 'seller_company',
        rate_per_m2: rule.rate_per_m2 ?? '',
        active: rule.active,
        notes: rule.notes ?? '',
    };
}

function emptyLabel(common: Record<string, string>): string {
    return common.not_available ?? '—';
}

function formatRate(
    rule: CommissionRuleRow,
    common: Record<string, string>,
): string {
    if (rule.rate_per_m2 != null) {
        return `€${Number(rule.rate_per_m2).toFixed(2)}/m²`;
    }

    return emptyLabel(common);
}

function CommissionFormFields({
    form,
    t,
    common,
    appliesToOptions,
}: {
    form: ReturnType<typeof useForm<CommissionFormData>>;
    t: Record<string, string>;
    common: Record<string, string>;
    appliesToOptions: { label: string; value: string }[];
}) {
    return (
        <div className="grid gap-3 sm:grid-cols-2">
            <FormInput
                label={common.name}
                value={form.data.name}
                error={form.errors.name}
                onChange={(e) => form.setData('name', e.target.value)}
                required
                className="sm:col-span-2"
            />
            <Select
                label={t.applies_to}
                value={form.data.applies_to}
                error={form.errors.applies_to}
                onChange={(e) => form.setData('applies_to', e.target.value)}
                options={appliesToOptions}
                required
            />
            <FormInput
                label={t.rate_per_m2}
                type="number"
                step="0.01"
                min="0"
                value={form.data.rate_per_m2}
                error={form.errors.rate_per_m2}
                onChange={(e) => form.setData('rate_per_m2', e.target.value)}
                hint={t.rate_per_m2_hint}
                required
            />
            <Textarea
                label={common.description}
                value={form.data.notes}
                error={form.errors.notes}
                onChange={(e) => form.setData('notes', e.target.value)}
                rows={2}
                className="sm:col-span-2"
            />
        </div>
    );
}

export default function CommissionsTab({
    rules,
    t,
    common,
}: {
    rules: CommissionRuleRow[];
    t: Record<string, string>;
    common: Record<string, string>;
}) {
    const isMobile = useIsMobile();
    const [viewRule, setViewRule] = useState<CommissionRuleRow | null>(null);
    const [editRule, setEditRule] = useState<CommissionRuleRow | null>(null);
    const [creating, setCreating] = useState(false);
    const [deactivateRule, setDeactivateRule] =
        useState<CommissionRuleRow | null>(null);

    const createForm = useForm<CommissionFormData>(emptyCommissionForm());
    const editForm = useForm<CommissionFormData>(emptyCommissionForm());

    const triggerLabel = t.trigger_short ?? t.trigger_hint;

    const appliesToLabel = (value: string | null) => {
        if (!value) {
            return emptyLabel(common);
        }
        const key = value as CommissionAppliesTo;
        return t[key] ?? value;
    };

    const appliesToOptions = COMMISSION_APPLIES_TO.map((value) => ({
        label: t[value],
        value,
    }));

    const groupedRules = useMemo(() => {
        const groups: Record<CommissionAppliesTo, CommissionRuleRow[]> = {
            seller_company: [],
            individual_agent: [],
        };

        for (const rule of rules) {
            const key = (rule.applies_to ??
                'seller_company') as CommissionAppliesTo;
            if (groups[key]) {
                groups[key].push(rule);
            }
        }

        return groups;
    }, [rules]);

    const openCreate = () => {
        createForm.reset();
        createForm.clearErrors();
        setCreating(true);
    };

    const openEdit = (rule: CommissionRuleRow) => {
        editForm.clearErrors();
        editForm.setData(ruleToForm(rule));
        setEditRule(rule);
        setViewRule(null);
    };

    const submitCreate = () => {
        createForm.post(route('admin.settings.commissions.store'), {
            onSuccess: () => {
                setCreating(false);
                createForm.reset();
            },
        });
    };

    const submitEdit = () => {
        if (!editRule) {
            return;
        }
        editForm.put(route('admin.settings.commissions.update', editRule.id), {
            onSuccess: () => setEditRule(null),
        });
    };

    const confirmDeactivate = () => {
        if (!deactivateRule) {
            return;
        }
        router.delete(
            route('admin.settings.commissions.destroy', deactivateRule.id),
            {
                data: { deactivate_only: true },
                onSuccess: () => setDeactivateRule(null),
            },
        );
    };

    const actionButtons = (rule: CommissionRuleRow) => (
        <TableActions>
            <TableActionButton
                label={common.view}
                icon={tableActionIcons.view}
                onClick={() => setViewRule(rule)}
            />
            <TableActionButton
                label={common.edit}
                icon={tableActionIcons.edit}
                onClick={() => openEdit(rule)}
            />
            {rule.active && (
                <TableActionButton
                    label={common.deactivate}
                    icon={tableActionIcons.cancel}
                    tone="danger"
                    onClick={() => setDeactivateRule(rule)}
                />
            )}
        </TableActions>
    );

    const renderRuleTable = (sectionRules: CommissionRuleRow[]) => {
        if (sectionRules.length === 0) {
            return null;
        }

        if (isMobile) {
            return (
                <MobileCardList
                    items={sectionRules.map((rule) => ({
                        id: String(rule.id),
                        title: rule.name,
                        subtitle: appliesToLabel(rule.applies_to),
                        meta: (
                            <StatusBadge
                                label={rule.active ? t.active : t.inactive}
                                tone={rule.active ? 'success' : 'neutral'}
                            />
                        ),
                        body: (
                            <>
                                <p className="text-sm">
                                    <span className="text-rml-muted">
                                        {t.rate}:{' '}
                                    </span>
                                    {formatRate(rule, common)}
                                </p>
                                <p className="text-xs text-rml-muted">
                                    {triggerLabel}
                                </p>
                            </>
                        ),
                        actions: actionButtons(rule),
                    }))}
                    emptyMessage={common.empty}
                />
            );
        }

        return (
            <DataTable
                data={sectionRules}
                getRowId={(row) => String(row.id)}
                emptyMessage={common.empty}
                columns={[
                    {
                        id: 'name',
                        header: common.name,
                        cell: (row) => row.name,
                    },
                    {
                        id: 'applies_to',
                        header: t.applies_to,
                        cell: (row) => appliesToLabel(row.applies_to),
                    },
                    {
                        id: 'rate',
                        header: t.rate,
                        cell: (row) => formatRate(row, common),
                    },
                    {
                        id: 'trigger',
                        header: t.trigger,
                        cell: () => (
                            <span
                                className="text-xs text-rml-muted"
                                title={t.trigger_hint}
                            >
                                {triggerLabel}
                            </span>
                        ),
                    },
                    {
                        id: 'status',
                        header: common.status,
                        cell: (row) => (
                            <StatusBadge
                                label={row.active ? t.active : t.inactive}
                                tone={row.active ? 'success' : 'neutral'}
                            />
                        ),
                    },
                    {
                        id: 'actions',
                        header: common.actions,
                        cell: (row) => actionButtons(row),
                    },
                ]}
            />
        );
    };

    return (
        <div className="space-y-3">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 className="text-base font-semibold text-rml-text">
                        {t.commission_title}
                    </h2>
                    <p className="text-xs text-rml-muted">
                        {t.commission_subtitle}
                    </p>
                </div>
                <Button size="sm" onClick={openCreate}>
                    {t.add_commission}
                </Button>
            </div>

            {rules.length === 0 ? (
                <EmptyState title={common.empty} />
            ) : (
                <div className="space-y-2">
                    {COMMISSION_APPLIES_TO.map((group) => {
                        const sectionRules = groupedRules[group];
                        if (sectionRules.length === 0) {
                            return null;
                        }

                        return (
                            <section key={group} className="space-y-1">
                                <h3 className="text-xs font-semibold uppercase tracking-wide text-rml-muted">
                                    {t[group]}
                                </h3>
                                {renderRuleTable(sectionRules)}
                            </section>
                        );
                    })}
                </div>
            )}

            <Modal
                open={viewRule != null}
                onClose={() => setViewRule(null)}
                title={t.commission_title}
                size="md"
                footer={
                    <>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setViewRule(null)}
                        >
                            {common.close}
                        </Button>
                        {viewRule && (
                            <Button size="sm" onClick={() => openEdit(viewRule)}>
                                {common.edit}
                            </Button>
                        )}
                    </>
                }
            >
                {viewRule && (
                    <dl className="grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt className="text-rml-muted">{common.name}</dt>
                            <dd>{viewRule.name}</dd>
                        </div>
                        <div>
                            <dt className="text-rml-muted">{t.applies_to}</dt>
                            <dd>{appliesToLabel(viewRule.applies_to)}</dd>
                        </div>
                        <div>
                            <dt className="text-rml-muted">{t.rate}</dt>
                            <dd>{formatRate(viewRule, common)}</dd>
                        </div>
                        <div>
                            <dt className="text-rml-muted">{common.status}</dt>
                            <dd>
                                <StatusBadge
                                    label={
                                        viewRule.active ? t.active : t.inactive
                                    }
                                    tone={
                                        viewRule.active ? 'success' : 'neutral'
                                    }
                                />
                            </dd>
                        </div>
                        <div className="sm:col-span-2">
                            <dt className="text-rml-muted">{t.trigger}</dt>
                            <dd>{triggerLabel}</dd>
                        </div>
                        <div className="sm:col-span-2">
                            <dt className="text-rml-muted">
                                {common.description}
                            </dt>
                            <dd className="whitespace-pre-wrap">
                                {viewRule.notes?.trim()
                                    ? viewRule.notes
                                    : emptyLabel(common)}
                            </dd>
                        </div>
                    </dl>
                )}
            </Modal>

            <Modal
                open={creating}
                onClose={() => setCreating(false)}
                title={t.add_commission}
                size="md"
                footer={
                    <>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setCreating(false)}
                        >
                            {common.cancel}
                        </Button>
                        <Button
                            size="sm"
                            onClick={submitCreate}
                            disabled={createForm.processing}
                        >
                            {common.create}
                        </Button>
                    </>
                }
            >
                <CommissionFormFields
                    form={createForm}
                    t={t}
                    common={common}
                    appliesToOptions={appliesToOptions}
                />
            </Modal>

            <Modal
                open={editRule != null}
                onClose={() => setEditRule(null)}
                title={t.commission_title}
                size="md"
                footer={
                    <>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setEditRule(null)}
                        >
                            {common.cancel}
                        </Button>
                        <Button
                            size="sm"
                            onClick={submitEdit}
                            disabled={editForm.processing}
                        >
                            {common.save}
                        </Button>
                    </>
                }
            >
                <CommissionFormFields
                    form={editForm}
                    t={t}
                    common={common}
                    appliesToOptions={appliesToOptions}
                />
            </Modal>

            <Modal
                open={deactivateRule != null}
                onClose={() => setDeactivateRule(null)}
                title={t.confirm_deactivate_commission}
                size="sm"
                footer={
                    <>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setDeactivateRule(null)}
                        >
                            {common.cancel}
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={confirmDeactivate}
                        >
                            {common.deactivate}
                        </Button>
                    </>
                }
            >
                <p className="text-sm text-rml-text">
                    {t.confirm_deactivate_commission}
                </p>
            </Modal>
        </div>
    );
}
