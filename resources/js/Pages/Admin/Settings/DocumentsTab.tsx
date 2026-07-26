import { useMemo, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import {
    Button,
    ConfirmDialog,
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
import type { TemplateRow, TemplateVersionRow } from './types';

type DocumentTabKind = 'agreements' | 'terms' | 'gdpr';

type TemplateFormData = {
    type: string;
    name: string;
    description: string;
    content: string;
};

function emptyTemplateForm(defaultType = ''): TemplateFormData {
    return {
        type: defaultType,
        name: '',
        description: '',
        content: '',
    };
}

function templateToForm(template: TemplateRow): TemplateFormData {
    return {
        type: template.type ?? '',
        name: template.name,
        description: template.description ?? '',
        content: '',
    };
}

export default function DocumentsTab({
    tabKind,
    templates,
    allowedTypes,
    t,
    common,
    templateTypes,
}: {
    tabKind: DocumentTabKind;
    templates: TemplateRow[];
    allowedTypes: readonly string[];
    t: Record<string, string>;
    common: Record<string, string>;
    templateTypes: Record<string, string>;
}) {
    const isMobile = useIsMobile();
    const [creating, setCreating] = useState(false);
    const [viewTemplate, setViewTemplate] = useState<TemplateRow | null>(null);
    const [editTemplate, setEditTemplate] = useState<TemplateRow | null>(null);
    const [deleteTemplate, setDeleteTemplate] = useState<TemplateRow | null>(
        null,
    );
    const [versionAction, setVersionAction] = useState<{
        template: TemplateRow;
        version: TemplateVersionRow;
        mode: 'activate' | 'deactivate';
    } | null>(null);

    const createForm = useForm<TemplateFormData>(emptyTemplateForm());
    const editForm = useForm<TemplateFormData>(emptyTemplateForm());

    const usedTypes = useMemo(
        () => new Set(templates.map((tpl) => tpl.type).filter(Boolean)),
        [templates],
    );

    const availableTypeOptions = useMemo(
        () =>
            allowedTypes
                .filter((type) => !usedTypes.has(type))
                .map((type) => ({
                    label: templateTypes[type] ?? type,
                    value: type,
                })),
        [allowedTypes, templateTypes, usedTypes],
    );

    const typeLabel = (type: string | null) => {
        if (!type) {
            return common.not_available ?? '—';
        }
        return templateTypes[type] ?? type;
    };

    const sectionTitle =
        tabKind === 'agreements'
            ? t.section_agreements
            : tabKind === 'terms'
              ? t.section_terms
              : t.section_gdpr;

    const sectionSubtitle =
        tabKind === 'agreements'
            ? t.agreements_subtitle
            : tabKind === 'terms'
              ? t.terms_subtitle
              : t.gdpr_subtitle;

    const openCreate = () => {
        createForm.reset();
        createForm.setData(
            emptyTemplateForm(availableTypeOptions[0]?.value ?? ''),
        );
        createForm.clearErrors();
        setCreating(true);
    };

    const openEdit = (template: TemplateRow) => {
        editForm.clearErrors();
        editForm.setData(templateToForm(template));
        setEditTemplate(template);
        setViewTemplate(null);
    };

    const submitCreate = () => {
        createForm.post(route('admin.settings.templates.store'), {
            onSuccess: () => {
                setCreating(false);
                createForm.reset();
            },
        });
    };

    const submitEdit = () => {
        if (!editTemplate) {
            return;
        }
        editForm.put(route('admin.settings.templates.update', editTemplate.id), {
            onSuccess: () => setEditTemplate(null),
        });
    };

    const confirmDelete = () => {
        if (!deleteTemplate) {
            return;
        }
        router.delete(
            route('admin.settings.templates.destroy', deleteTemplate.id),
            {
                onSuccess: () => setDeleteTemplate(null),
            },
        );
    };

    const confirmVersionAction = () => {
        if (!versionAction) {
            return;
        }
        const { template, version, mode } = versionAction;
        const routeName =
            mode === 'activate'
                ? 'admin.settings.templates.versions.activate'
                : 'admin.settings.templates.versions.deactivate';

        router.put(
            route(routeName, {
                template: template.id,
                version: version.id,
            }),
            {},
            {
                onSuccess: () => setVersionAction(null),
            },
        );
    };

    const actionButtons = (template: TemplateRow) => (
        <TableActions>
            <TableActionButton
                label={common.view}
                icon={tableActionIcons.view}
                onClick={() => setViewTemplate(template)}
            />
            <TableActionButton
                label={common.edit}
                icon={tableActionIcons.edit}
                onClick={() => openEdit(template)}
            />
            <TableActionButton
                label={common.delete}
                icon={tableActionIcons.delete}
                tone="danger"
                onClick={() => setDeleteTemplate(template)}
            />
        </TableActions>
    );

    const versionActions = (template: TemplateRow, version: TemplateVersionRow) => (
        <TableActions>
            <TableActionButton
                label={common.view}
                icon={tableActionIcons.view}
                onClick={() =>
                    setViewTemplate({
                        ...template,
                        content: version.content,
                        active_version: version.version,
                    })
                }
            />
            {!version.is_active ? (
                <TableActionButton
                    label={t.activate_version}
                    icon={tableActionIcons.approve}
                    tone="success"
                    onClick={() =>
                        setVersionAction({
                            template,
                            version,
                            mode: 'activate',
                        })
                    }
                />
            ) : (
                <TableActionButton
                    label={t.deactivate_version}
                    icon={tableActionIcons.cancel}
                    tone="danger"
                    onClick={() =>
                        setVersionAction({
                            template,
                            version,
                            mode: 'deactivate',
                        })
                    }
                />
            )}
        </TableActions>
    );

    return (
        <div className="space-y-3">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 className="text-base font-semibold text-rml-text">
                        {sectionTitle}
                    </h2>
                    <p className="text-xs text-rml-muted">{sectionSubtitle}</p>
                </div>
                {availableTypeOptions.length > 0 && (
                    <Button size="sm" onClick={openCreate}>
                        {t.add_template}
                    </Button>
                )}
            </div>

            {templates.length === 0 ? (
                <EmptyState title={common.empty} />
            ) : isMobile ? (
                <MobileCardList
                    items={templates.map((template) => ({
                        id: String(template.id),
                        title: template.name,
                        subtitle: typeLabel(template.type),
                        meta: (
                            <StatusBadge
                                label={
                                    template.active_version_id
                                        ? t.active
                                        : t.inactive
                                }
                                tone={
                                    template.active_version_id
                                        ? 'success'
                                        : 'neutral'
                                }
                            />
                        ),
                        body: (
                            <p className="text-sm text-rml-muted">
                                {t.version}: {template.active_version ?? '—'}
                            </p>
                        ),
                        actions: actionButtons(template),
                    }))}
                />
            ) : (
                <DataTable
                    data={templates}
                    getRowId={(row) => String(row.id)}
                    emptyMessage={common.empty}
                    columns={[
                        {
                            id: 'name',
                            header: common.name,
                            cell: (row) => row.name,
                        },
                        {
                            id: 'type',
                            header: t.template_type,
                            cell: (row) => typeLabel(row.type),
                        },
                        {
                            id: 'version',
                            header: t.version,
                            cell: (row) => row.active_version ?? '—',
                        },
                        {
                            id: 'status',
                            header: common.status,
                            cell: (row) => (
                                <StatusBadge
                                    label={
                                        row.active_version_id
                                            ? t.active
                                            : t.inactive
                                    }
                                    tone={
                                        row.active_version_id
                                            ? 'success'
                                            : 'neutral'
                                    }
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
            )}

            <Modal
                open={viewTemplate != null}
                onClose={() => setViewTemplate(null)}
                title={viewTemplate?.name ?? t.templates}
                description={
                    viewTemplate?.type
                        ? typeLabel(viewTemplate.type)
                        : undefined
                }
                size="lg"
                footer={
                    <>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setViewTemplate(null)}
                        >
                            {common.close}
                        </Button>
                        {viewTemplate && (
                            <Button
                                size="sm"
                                onClick={() => openEdit(viewTemplate)}
                            >
                                {common.edit}
                            </Button>
                        )}
                    </>
                }
            >
                {viewTemplate && (
                    <div className="space-y-4">
                        <dl className="grid gap-3 text-sm sm:grid-cols-2">
                            <div>
                                <dt className="text-rml-muted">{t.version}</dt>
                                <dd>{viewTemplate.active_version ?? '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-rml-muted">
                                    {common.status}
                                </dt>
                                <dd>
                                    <StatusBadge
                                        label={
                                            viewTemplate.active_version_id
                                                ? t.active
                                                : t.inactive
                                        }
                                        tone={
                                            viewTemplate.active_version_id
                                                ? 'success'
                                                : 'neutral'
                                        }
                                    />
                                </dd>
                            </div>
                            <div className="sm:col-span-2">
                                <dt className="text-rml-muted">
                                    {common.description}
                                </dt>
                                <dd>
                                    {viewTemplate.description?.trim()
                                        ? viewTemplate.description
                                        : common.not_available ?? '—'}
                                </dd>
                            </div>
                        </dl>

                        <div>
                            <h3 className="mb-1.5 text-sm font-semibold">
                                {t.content}
                            </h3>
                            <pre className="max-h-72 overflow-auto whitespace-pre-wrap rounded-lg bg-rml-background p-3 text-xs">
                                {viewTemplate.content?.trim()
                                    ? viewTemplate.content
                                    : common.not_available ?? '—'}
                            </pre>
                        </div>

                        {(viewTemplate.versions?.length ?? 0) > 0 && (
                            <div>
                                <h3 className="mb-2 text-sm font-semibold">
                                    {t.versions}
                                </h3>
                                <DataTable
                                    data={viewTemplate.versions ?? []}
                                    getRowId={(row) => String(row.id)}
                                    emptyMessage={common.empty}
                                    columns={[
                                        {
                                            id: 'version',
                                            header: t.version,
                                            cell: (row) => row.version,
                                        },
                                        {
                                            id: 'status',
                                            header: common.status,
                                            cell: (row) => (
                                                <StatusBadge
                                                    label={
                                                        row.is_active
                                                            ? t.active
                                                            : t.inactive
                                                    }
                                                    tone={
                                                        row.is_active
                                                            ? 'success'
                                                            : 'neutral'
                                                    }
                                                />
                                            ),
                                        },
                                        {
                                            id: 'actions',
                                            header: common.actions,
                                            cell: (row) =>
                                                versionActions(
                                                    viewTemplate,
                                                    row,
                                                ),
                                        },
                                    ]}
                                />
                            </div>
                        )}
                    </div>
                )}
            </Modal>

            <Modal
                open={creating}
                onClose={() => setCreating(false)}
                title={t.add_template}
                size="lg"
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
                <div className="grid gap-3 sm:grid-cols-2">
                    <Select
                        label={t.template_type}
                        value={createForm.data.type}
                        error={createForm.errors.type}
                        onChange={(e) =>
                            createForm.setData('type', e.target.value)
                        }
                        options={availableTypeOptions}
                        required
                        className="sm:col-span-2"
                    />
                    <FormInput
                        label={common.name}
                        value={createForm.data.name}
                        error={createForm.errors.name}
                        onChange={(e) =>
                            createForm.setData('name', e.target.value)
                        }
                        required
                        className="sm:col-span-2"
                    />
                    <Textarea
                        label={common.description}
                        value={createForm.data.description}
                        error={createForm.errors.description}
                        onChange={(e) =>
                            createForm.setData('description', e.target.value)
                        }
                        rows={2}
                        className="sm:col-span-2"
                    />
                    <Textarea
                        label={t.content}
                        value={createForm.data.content}
                        error={createForm.errors.content}
                        onChange={(e) =>
                            createForm.setData('content', e.target.value)
                        }
                        rows={8}
                        className="sm:col-span-2"
                    />
                </div>
            </Modal>

            <Modal
                open={editTemplate != null}
                onClose={() => setEditTemplate(null)}
                title={editTemplate?.name ?? t.templates}
                description={
                    editTemplate?.type
                        ? typeLabel(editTemplate.type)
                        : undefined
                }
                size="lg"
                footer={
                    <>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setEditTemplate(null)}
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
                {editTemplate && (
                    <div className="grid gap-3 sm:grid-cols-2">
                        <FormInput
                            label={common.name}
                            value={editForm.data.name}
                            error={editForm.errors.name}
                            onChange={(e) =>
                                editForm.setData('name', e.target.value)
                            }
                            className="sm:col-span-2"
                        />
                        <Textarea
                            label={common.description}
                            value={editForm.data.description}
                            error={editForm.errors.description}
                            onChange={(e) =>
                                editForm.setData('description', e.target.value)
                            }
                            rows={2}
                            className="sm:col-span-2"
                        />
                        <Textarea
                            label={t.new_version_content}
                            value={editForm.data.content}
                            error={editForm.errors.content}
                            onChange={(e) =>
                                editForm.setData('content', e.target.value)
                            }
                            hint={
                                editTemplate.active_version
                                    ? `${t.version}: ${editTemplate.active_version}`
                                    : undefined
                            }
                            rows={8}
                            className="sm:col-span-2"
                        />
                    </div>
                )}
            </Modal>

            <ConfirmDialog
                open={deleteTemplate != null}
                onClose={() => setDeleteTemplate(null)}
                onConfirm={confirmDelete}
                title={t.confirm_delete_template}
                body={t.confirm_delete_template_body}
                confirmLabel={common.confirm_yes_delete ?? common.delete}
                cancelLabel={common.confirm_no_cancel ?? common.cancel}
            />

            <ConfirmDialog
                open={versionAction != null}
                onClose={() => setVersionAction(null)}
                onConfirm={confirmVersionAction}
                title={
                    versionAction?.mode === 'activate'
                        ? t.confirm_activate_version
                        : t.confirm_deactivate_version
                }
                body={
                    versionAction?.mode === 'activate'
                        ? t.confirm_activate_version_body
                        : t.confirm_deactivate_version_body
                }
                confirmLabel={
                    versionAction?.mode === 'activate'
                        ? t.activate_version
                        : t.deactivate_version
                }
                cancelLabel={common.confirm_no_cancel ?? common.cancel}
                confirmVariant="outline"
                confirmTone={
                    versionAction?.mode === 'activate' ? 'default' : 'danger'
                }
            />
        </div>
    );
}
