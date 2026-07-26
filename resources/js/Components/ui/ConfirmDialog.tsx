import type { ReactNode } from 'react';
import { Button } from './Button';
import { Modal } from './Modal';

export default function ConfirmDialog({
    open,
    onClose,
    onConfirm,
    title,
    body,
    confirmLabel,
    cancelLabel,
    confirmVariant = 'outline',
    confirmTone = 'danger',
    processing = false,
}: {
    open: boolean;
    onClose: () => void;
    onConfirm: () => void;
    title: string;
    body: ReactNode;
    confirmLabel: string;
    cancelLabel: string;
    confirmVariant?: 'primary' | 'outline' | 'ghost';
    confirmTone?: 'default' | 'danger';
    processing?: boolean;
}) {
    return (
        <Modal
            open={open}
            onClose={() => {
                if (!processing) {
                    onClose();
                }
            }}
            title={title}
            size="sm"
            footer={
                <>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={onClose}
                        disabled={processing}
                    >
                        {cancelLabel}
                    </Button>
                    <Button
                        variant={confirmVariant}
                        size="sm"
                        onClick={onConfirm}
                        disabled={processing}
                        className={
                            confirmTone === 'danger'
                                ? 'border-red-200 bg-red-50 text-rml-red hover:bg-red-100'
                                : undefined
                        }
                    >
                        {confirmLabel}
                    </Button>
                </>
            }
        >
            <div className="text-sm text-rml-text">{body}</div>
        </Modal>
    );
}
