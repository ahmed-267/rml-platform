import { FormEvent, useEffect, useMemo, useState } from 'react';
import { Button, Modal, Select, Textarea } from '@/Components/ui';

export type RejectionReasonOption = {
    value: string;
    label: string;
};

type Props = {
    open: boolean;
    onClose: () => void;
    title: string;
    warning: string;
    reasonLabel: string;
    commentLabel: string;
    commentHint?: string;
    selectReasonLabel: string;
    cancelLabel: string;
    confirmLabel: string;
    reasons: RejectionReasonOption[];
    processing?: boolean;
    errors?: {
        reason_code?: string;
        comment?: string;
    };
    onConfirm: (payload: { reason_code: string; comment: string }) => void;
};

export function RejectionFormModal({
    open,
    onClose,
    title,
    warning,
    reasonLabel,
    commentLabel,
    commentHint,
    selectReasonLabel,
    cancelLabel,
    confirmLabel,
    reasons,
    processing = false,
    errors = {},
    onConfirm,
}: Props) {
    const [reasonCode, setReasonCode] = useState('');
    const [comment, setComment] = useState('');

    useEffect(() => {
        if (!open) {
            setReasonCode('');
            setComment('');
        }
    }, [open]);

    const commentRequired = reasonCode === 'other';
    const canSubmit = useMemo(() => {
        if (!reasonCode) {
            return false;
        }
        if (commentRequired && comment.trim() === '') {
            return false;
        }
        return true;
    }, [reasonCode, comment, commentRequired]);

    const submit = (event?: FormEvent) => {
        event?.preventDefault();
        if (!canSubmit || processing) {
            return;
        }
        onConfirm({
            reason_code: reasonCode,
            comment: comment.trim(),
        });
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={title}
            size="md"
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
                        variant="danger"
                        size="sm"
                        onClick={submit}
                        disabled={!canSubmit || processing}
                    >
                        {confirmLabel}
                    </Button>
                </>
            }
        >
            <form onSubmit={submit} className="space-y-4">
                <p className="text-sm text-rml-muted">{warning}</p>
                <Select
                    label={reasonLabel}
                    value={reasonCode}
                    error={errors.reason_code}
                    required
                    onChange={(e) => setReasonCode(e.target.value)}
                    options={[
                        { label: selectReasonLabel, value: '' },
                        ...reasons,
                    ]}
                />
                <Textarea
                    label={commentLabel}
                    name="comment"
                    value={comment}
                    error={errors.comment}
                    required={commentRequired}
                    onChange={(e) => setComment(e.target.value)}
                />
                {commentHint ? (
                    <p className="text-xs text-rml-muted">{commentHint}</p>
                ) : null}
            </form>
        </Modal>
    );
}
