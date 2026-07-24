import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { Button, Modal } from '@/Components/ui';
import type { ApprovalStatus } from '@/types';
import { cn } from '@/lib/cn';

export type AccountActionLabels = {
    view: string;
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
};

type Props = {
    status: ApprovalStatus;
    targetName: string;
    companyName?: string | null;
    viewHref?: string;
    approveRoute?: string;
    rejectRoute?: string;
    suspendRoute?: string;
    reinstateRoute?: string;
    labels: AccountActionLabels;
    canManage?: boolean;
    showView?: boolean;
    variant?: 'links' | 'buttons';
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
    approveRoute,
    rejectRoute,
    suspendRoute,
    reinstateRoute,
    labels,
    canManage = true,
    showView = true,
    variant = 'links',
    className,
}: Props) {
    const [modal, setModal] = useState<
        'reject' | 'suspend' | 'reinstate' | 'approve' | null
    >(null);
    const [processing, setProcessing] = useState(false);

    const vars = {
        name: targetName,
        company: companyName?.trim() || targetName,
    };

    const linkClass =
        'text-xs font-semibold whitespace-nowrap hover:underline disabled:opacity-50';

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

    const renderAction = (
        key: string,
        label: string,
        onClick: () => void,
        tone: 'primary' | 'danger' | 'warning',
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
            <button
                key={key}
                type="button"
                className={cn(
                    linkClass,
                    tone === 'danger' && 'text-rml-red',
                    tone === 'warning' && 'text-rml-amber',
                    tone === 'primary' && 'text-rml-primary',
                )}
                disabled={processing}
                onClick={onClick}
            >
                {label}
            </button>
        );
    };

    return (
        <>
            <div className={cn('flex flex-wrap items-center gap-2', className)}>
                {showView &&
                    viewHref &&
                    (variant === 'buttons' ? (
                        <Link href={viewHref}>
                            <Button size="sm" variant="outline">
                                {labels.view}
                            </Button>
                        </Link>
                    ) : (
                        <Link
                            href={viewHref}
                            className={cn(linkClass, 'text-rml-primary')}
                        >
                            {labels.view}
                        </Link>
                    ))}

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
                        'primary',
                    )}

                {canManage &&
                    status === 'pending' &&
                    rejectRoute &&
                    renderAction(
                        'reject',
                        labels.reject,
                        () => setModal('reject'),
                        'danger',
                    )}

                {canManage &&
                    status === 'approved' &&
                    suspendRoute &&
                    renderAction(
                        'suspend',
                        labels.suspend,
                        () => setModal('suspend'),
                        'warning',
                    )}

                {canManage &&
                    status === 'suspended' &&
                    reinstateRoute &&
                    renderAction(
                        'reinstate',
                        labels.reinstate,
                        () => setModal('reinstate'),
                        'primary',
                    )}
            </div>

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
        </>
    );
}
