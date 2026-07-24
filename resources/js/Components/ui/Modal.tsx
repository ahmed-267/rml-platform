import { Fragment, ReactNode, useEffect } from 'react';
import { Dialog, DialogPanel, DialogTitle, Transition, TransitionChild } from '@headlessui/react';
import { X } from 'lucide-react';
import { usePage } from '@inertiajs/react';
import { cn } from '@/lib/cn';
import { Button } from '@/Components/ui/Button';
import type { PageProps } from '@/types';

export interface ModalProps {
    open: boolean;
    onClose: () => void;
    title?: string;
    description?: string;
    children: ReactNode;
    footer?: ReactNode;
    size?: 'sm' | 'md' | 'lg' | 'xl' | '2xl';
    className?: string;
}

const sizeClasses = {
    sm: 'max-w-md',
    md: 'max-w-lg',
    lg: 'max-w-2xl',
    xl: 'max-w-4xl',
    '2xl': 'max-w-[68rem]',
};

export function Modal({
    open,
    onClose,
    title,
    description,
    children,
    footer,
    size = 'md',
    className,
}: ModalProps) {
    const { translations } = usePage<PageProps>().props;
    const closeLabel = translations.common?.close ?? 'Close';

    useEffect(() => {
        if (!open) {
            return;
        }

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                onClose();
            }
        };

        window.addEventListener('keydown', onKeyDown);
        return () => window.removeEventListener('keydown', onKeyDown);
    }, [open, onClose]);

    return (
        <Transition show={open} as={Fragment}>
            <Dialog as="div" className="relative z-50" onClose={onClose}>
                <TransitionChild
                    as={Fragment}
                    enter="ease-out duration-200"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in duration-150"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="fixed inset-0 bg-slate-900/40" />
                </TransitionChild>

                <div className="fixed inset-0 overflow-y-auto p-4 sm:p-6">
                    <div className="flex min-h-full items-end justify-center sm:items-center">
                        <TransitionChild
                            as={Fragment}
                            enter="ease-out duration-200"
                            enterFrom="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                            enterTo="opacity-100 translate-y-0 sm:scale-100"
                            leave="ease-in duration-150"
                            leaveFrom="opacity-100 translate-y-0 sm:scale-100"
                            leaveTo="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        >
                            <DialogPanel
                                className={cn(
                                    'w-full overflow-hidden rounded-t-2xl bg-white shadow-xl sm:rounded-2xl',
                                    sizeClasses[size],
                                    className,
                                )}
                            >
                                <div className="flex items-start justify-between gap-4 border-b border-rml-border px-5 py-4">
                                    <div>
                                        {title && (
                                            <DialogTitle className="text-lg font-semibold text-rml-text">
                                                {title}
                                            </DialogTitle>
                                        )}
                                        {description && (
                                            <p className="mt-1 text-sm text-rml-muted">
                                                {description}
                                            </p>
                                        )}
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={onClose}
                                        aria-label={closeLabel}
                                        className="!px-2"
                                    >
                                        <X className="h-5 w-5" />
                                    </Button>
                                </div>
                                <div className="max-h-[min(75vh,42rem)] overflow-y-auto px-5 py-4">
                                    {children}
                                </div>
                                {footer && (
                                    <div className="flex flex-col-reverse gap-2 border-t border-rml-border bg-rml-background px-5 py-4 sm:flex-row sm:justify-end">
                                        {footer}
                                    </div>
                                )}
                            </DialogPanel>
                        </TransitionChild>
                    </div>
                </div>
            </Dialog>
        </Transition>
    );
}
