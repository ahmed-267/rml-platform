import { Fragment, ReactNode } from 'react';
import { Dialog, DialogPanel, DialogTitle, Transition, TransitionChild } from '@headlessui/react';
import { X } from 'lucide-react';
import { usePage } from '@inertiajs/react';
import { cn } from '@/lib/cn';
import { Button } from '@/Components/ui/Button';
import type { PageProps } from '@/types';

export interface DrawerProps {
    open: boolean;
    onClose: () => void;
    title?: string;
    description?: string;
    children: ReactNode;
    footer?: ReactNode;
    side?: 'right' | 'left' | 'bottom';
    className?: string;
}

export function Drawer({
    open,
    onClose,
    title,
    description,
    children,
    footer,
    side = 'right',
    className,
}: DrawerProps) {
    const isBottom = side === 'bottom';
    const { translations } = usePage<PageProps>().props;
    const closeLabel = translations.common?.close ?? 'Close';

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

                <div className="fixed inset-0 overflow-hidden">
                    <div
                        className={cn(
                            'absolute inset-0 flex',
                            isBottom
                                ? 'items-end'
                                : side === 'left'
                                  ? 'justify-start'
                                  : 'justify-end',
                        )}
                    >
                        <TransitionChild
                            as={Fragment}
                            enter="transform transition ease-out duration-200"
                            enterFrom={
                                isBottom
                                    ? 'translate-y-full'
                                    : side === 'left'
                                      ? '-translate-x-full'
                                      : 'translate-x-full'
                            }
                            enterTo="translate-x-0 translate-y-0"
                            leave="transform transition ease-in duration-150"
                            leaveFrom="translate-x-0 translate-y-0"
                            leaveTo={
                                isBottom
                                    ? 'translate-y-full'
                                    : side === 'left'
                                      ? '-translate-x-full'
                                      : 'translate-x-full'
                            }
                        >
                            <DialogPanel
                                className={cn(
                                    'flex w-full flex-col bg-white shadow-xl',
                                    isBottom
                                        ? 'max-h-[90vh] rounded-t-2xl'
                                        : 'h-full max-w-md',
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
                                <div className="flex-1 overflow-y-auto px-5 py-4">
                                    {children}
                                </div>
                                {footer && (
                                    <div className="border-t border-rml-border bg-rml-background px-5 py-4">
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
