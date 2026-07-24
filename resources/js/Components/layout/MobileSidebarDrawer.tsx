import { Fragment } from 'react';
import { Dialog, DialogPanel, Transition, TransitionChild } from '@headlessui/react';
import { PortalSidebar } from '@/Components/layout/PortalSidebar';
import type { NavItem } from '@/config/navigation';

export interface MobileSidebarDrawerProps {
    open: boolean;
    onClose: () => void;
    items: NavItem[];
    currentPath: string;
}

export function MobileSidebarDrawer({
    open,
    onClose,
    items,
    currentPath,
}: MobileSidebarDrawerProps) {
    return (
        <Transition show={open} as={Fragment}>
            <Dialog as="div" className="relative z-50 lg:hidden" onClose={onClose}>
                <TransitionChild
                    as={Fragment}
                    enter="ease-out duration-200"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in duration-150"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="fixed inset-0 bg-slate-900/50" />
                </TransitionChild>

                <div className="fixed inset-0 flex">
                    <TransitionChild
                        as={Fragment}
                        enter="transform transition ease-out duration-200"
                        enterFrom="-translate-x-full"
                        enterTo="translate-x-0"
                        leave="transform transition ease-in duration-150"
                        leaveFrom="translate-x-0"
                        leaveTo="-translate-x-full"
                    >
                        <DialogPanel className="relative flex h-full w-72 max-w-[85vw]">
                            <PortalSidebar
                                items={items}
                                currentPath={currentPath}
                                onNavigate={onClose}
                                className="w-full"
                            />
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </Dialog>
        </Transition>
    );
}
