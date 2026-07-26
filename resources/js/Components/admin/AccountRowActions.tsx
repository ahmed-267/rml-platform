import { useState } from 'react';
import { router } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    Button,
    Modal,
    TableActionButton,
    TableActionLink,
    TableActions,
    tableActionIcons,
} from '@/Components/ui';
import type { TableActionTone } from '@/Components/ui/TableActions';
import type { ApprovalStatus } from '@/types';
import { cn } from '@/lib/cn';

export type AccountActionLabels = {
    view: string;
    edit?: string;
    delete?: string;
    assign?: string;
    approve: string;
    reject: string;
    suspend: string;
    reinstate: string;
    cancel: string;
    rejectTitle: string;
    rejectBody: string;
    rejectConfirm: string;
    suspendTitle: string;
    suspendBody: string;
    suspendConfirm: string;
    reinstateTitle: string;
    reinstateBody: string;
    reinstateConfirm: string;
    approveTitle?: string;
    approveBody?: string;
    approveConfirm?: string;
    deleteTitle?: string;
    deleteBody?: string;
    deleteConfirm?: string;
};

type Props = {
    status: ApprovalStatus;
    targetName: string;
    companyName?: string | null;
    viewHref?: string;
    editHref?: string;
    onEdit?: () => void;
    onAssign?: () => void;
    deleteRoute?: string;
    approveRoute?: string;
    rejectRoute?: string;
    suspendRoute?: string;
    reinstateRoute?: string;
    labels: AccountActionLabels;
    canManage?: boolean;
    canDelete?: boolean;
    canAssign?: boolean;
    showView?: boolean;
    variant?: 'links' | 'buttons' | 'icons';
    className?: string;
};

function fillTemplate(
    template: string,
    vars: { name: string; company: string },
): string {
    return template
        .replaceAll(':name', vars.name)
        .replaceAll(':company', vars.company);
}

export function AccountRowActions({
    status,
    targetName,
    companyName,
    viewHref,
    editHref,
    onEdit,
    onAssign,
    deleteRoute,
    approveRoute,
    rejectRoute,
    suspendRoute,
    reinstateRoute,
    labels,
    canManage = true,
    canDelete = false,
    canAssign = false,
    showView = true,
    variant = 'icons',
    className,
}: Props) {
    const [modal, setModal] = useState<
        'reject' | 'suspend' | 'reinstate' | 'approve' | 'delete' | null
    >(null);
    const [processing, setProcessing] = useState(false);

    const vars = {
        name: targetName,
        company: companyName?.trim() || targetName,
    };

    const run = (callback: () => void) => {
        setProcessing(true);
        callback();
    };

    const closeModal = () => setModal(null);

    const submitApprove = () => {
        if (!approveRoute) return;
        run(() =>
            router.post(
                approveRoute,
                {},
                {
                    onSuccess: closeModal,
                    onFinish: () => setProcessing(false),
                },
            ),
        );
    };

    const submitReject = () => {
        if (!rejectRoute) return;
        run(() =>
            router.post(
                rejectRoute,
                {},
                {
                    onSuccess: closeModal,
                    onFinish: () => setProcessing(false),
                },
            ),
        );
    };

    const submitSuspend = () => {
        if (!suspendRoute) return;
        run(() =>
            router.post(
                suspendRoute,
                {},
                {
                    onSuccess: closeModal,
                    onFinish: () => setProcessing(false),
                },
            ),
        );
    };

    const submitReinstate = () => {
        if (!reinstateRoute) return;
        run(() =>
            router.post(
                reinstateRoute,
                {},
                {
                    onSuccess: closeModal,
                    onFinish: () => setProcessing(false),
                },
            ),
        );
    };

    const submitDelete = () => {
        if (!deleteRoute) return;
        run(() =>
            router.delete(deleteRoute, {
                onSuccess: closeModal,
                onFinish: () => setProcessing(false),
            }),
        );
    };

    const renderAction = (
        key: string,
        label: string,
        onClick: () => void,
        tone: TableActionTone,
        icon: LucideIcon,
    ) => {
        if (variant === 'buttons') {
            return (
                <Button
                    key={key}
                    size="sm"
                    variant={
                        tone === 'danger'
                            ? 'danger'
                            : tone === 'warning'
                              ? 'outline'
                              : 'primary'
                    }
                    disabled={processing}
                    onClick={onClick}
                >
                    {label}
                </Button>
            );
        }

        return (
            <TableActionButton
                key={key}
                label={label}
                icon={icon}
                tone={tone}
                disabled={processing}
                onClick={onClick}
            />
        );
    };

    return (
        <>
            <TableActions className={cn(className)}>
                {showView && viewHref && (
                    <TableActionLink
                        href={viewHref}
                        label={labels.view}
                        icon={tableActionIcons.view}
                    />
                )}

                {(onEdit || editHref) &&
                    (editHref && !onEdit ? (
                        <TableActionLink
                            href={editHref}
                            label={labels.edit ?? labels.view}
                            icon={tableActionIcons.edit}
                        />
                    ) : (
                        renderAction(
                            'edit',
                            labels.edit ?? 'Edit',
                            () => onEdit?.(),
                            'default',
                            tableActionIcons.edit,
                        )
                    ))}

                {canAssign &&
                    onAssign &&
                    renderAction(
                        'assign',
                        labels.assign ?? 'Assign audit',
                        onAssign,
                        'default',
                        tableActionIcons.assign,
                    )}

                {canManage &&
                    status === 'pending' &&
                    approveRoute &&
                    renderAction(
                        'approve',
                        labels.approve,
                        () => {
                            if (labels.approveTitle) {
                                setModal('approve');
                            } else {
                                submitApprove();
                            }
                        },
                        'success',
                        tableActionIcons.approve,
                    )}

                {canManage &&
                    status === 'pending' &&
                    rejectRoute &&
                    renderAction(
                        'reject',
                        labels.reject,
                        () => setModal('reject'),
                        'danger',
                        tableActionIcons.reject,
                    )}

                {canManage &&
                    status === 'approved' &&
                    suspendRoute &&
                    renderAction(
                        'suspend',
                        labels.suspend,
                        () => setModal('suspend'),
                        'warning',
                        tableActionIcons.suspend,
                    )}

                {canManage &&
                    status === 'suspended' &&
                    reinstateRoute &&
                    renderAction(
                        'reinstate',
                        labels.reinstate,
                        () => setModal('reinstate'),
                        'success',
                        tableActionIcons.reinstate,
                    )}

                {canDelete &&
                    deleteRoute &&
                    renderAction(
                        'delete',
                        labels.delete ?? 'Delete',
                        () => setModal('delete'),
                        'danger',
                        tableActionIcons.delete,
                    )}
            </TableActions>

            <Modal
                open={modal === 'approve'}
                onClose={closeModal}
                title={labels.approveTitle ?? labels.approve}
                size="sm"
                footer={
                    <>
                        <Button variant="ghost" size="sm" onClick={closeModal}>
                            {labels.cancel}
                        </Button>
                        <Button
                            size="sm"
                            disabled={processing}
                            onClick={submitApprove}
                        >
                            {labels.approveConfirm ?? labels.approve}
                        </Button>
                    </>
                }
            >
                <p className="text-sm text-rml-muted">
                    {fillTemplate(labels.approveBody ?? labels.approve, vars)}
                </p>
            </Modal>

            <Modal
                open={modal === 'reject'}
                onClose={closeModal}
                title={labels.rejectTitle}
                size="sm"
                footer={
                    <>
                        <Button variant="ghost" size="sm" onClick={closeModal}>
                            {labels.cancel}
                        </Button>
                        <Button
                            variant="danger"
                            size="sm"
                            disabled={processing}
                            onClick={submitReject}
                        >
                            {labels.rejectConfirm}
                        </Button>
                    </>
                }
            >
                <p className="text-sm text-rml-muted">
                    {fillTemplate(labels.rejectBody, vars)}
                </p>
            </Modal>

            <Modal
                open={modal === 'suspend'}
                onClose={closeModal}
                title={labels.suspendTitle}
                size="sm"
                footer={
                    <>
                        <Button variant="ghost" size="sm" onClick={closeModal}>
                            {labels.cancel}
                        </Button>
                        <Button
                            variant="danger"
                            size="sm"
                            disabled={processing}
                            onClick={submitSuspend}
                        >
                            {labels.suspendConfirm}
                        </Button>
                    </>
                }
            >
                <p className="text-sm text-rml-muted">
                    {fillTemplate(labels.suspendBody, vars)}
                </p>
            </Modal>

            <Modal
                open={modal === 'reinstate'}
                onClose={closeModal}
                title={labels.reinstateTitle}
                size="sm"
                footer={
                    <>
                        <Button variant="ghost" size="sm" onClick={closeModal}>
                            {labels.cancel}
                        </Button>
                        <Button
                            size="sm"
                            disabled={processing}
                            onClick={submitReinstate}
                        >
                            {labels.reinstateConfirm}
                        </Button>
                    </>
                }
            >
                <p className="text-sm text-rml-muted">
                    {fillTemplate(labels.reinstateBody, vars)}
                </p>
            </Modal>

            <Modal
                open={modal === 'delete'}
                onClose={closeModal}
                title={labels.deleteTitle ?? labels.delete ?? 'Delete'}
                size="sm"
                footer={
                    <>
                        <Button variant="ghost" size="sm" onClick={closeModal}>
                            {labels.cancel}
                        </Button>
                        <Button
                            variant="danger"
                            size="sm"
                            disabled={processing}
                            onClick={submitDelete}
                        >
                            {labels.deleteConfirm ?? labels.delete ?? 'Delete'}
                        </Button>
                    </>
                }
            >
                <p className="text-sm text-rml-muted">
                    {fillTemplate(
                        labels.deleteBody ??
                            'Permanently delete :name? This cannot be undone.',
                        vars,
                    )}
                </p>
            </Modal>
        </>
    );
}
