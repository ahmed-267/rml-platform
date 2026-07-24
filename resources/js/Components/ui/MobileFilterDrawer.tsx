import { ReactNode } from 'react';
import { usePage } from '@inertiajs/react';
import { Drawer } from '@/Components/ui/Drawer';
import { Button } from '@/Components/ui/Button';
import type { PageProps } from '@/types';

export interface MobileFilterDrawerProps {
    open: boolean;
    onClose: () => void;
    onApply?: () => void;
    onReset?: () => void;
    children: ReactNode;
    title?: string;
}

export function MobileFilterDrawer({
    open,
    onClose,
    onApply,
    onReset,
    children,
    title,
}: MobileFilterDrawerProps) {
    const { translations } = usePage<PageProps>().props;
    const resolvedTitle =
        title ??
        translations.common?.filters ??
        translations.admin?.common?.filters ??
        translations.seller?.common?.filters ??
        translations.buyer?.common?.filters ??
        '';
    const applyLabel =
        translations.common?.apply_filters ??
        translations.admin?.common?.apply ??
        translations.seller?.common?.apply ??
        translations.buyer?.common?.apply ??
        '';
    const resetLabel =
        translations.common?.reset ??
        translations.admin?.common?.reset ??
        translations.seller?.common?.reset ??
        translations.buyer?.common?.reset ??
        '';

    return (
        <Drawer
            open={open}
            onClose={onClose}
            title={resolvedTitle}
            side="bottom"
            footer={
                <div className="flex gap-2">
                    {onReset && (
                        <Button variant="outline" fullWidth onClick={onReset}>
                            {resetLabel}
                        </Button>
                    )}
                    <Button
                        fullWidth
                        onClick={() => {
                            onApply?.();
                            onClose();
                        }}
                    >
                        {applyLabel}
                    </Button>
                </div>
            }
        >
            <div className="space-y-4">{children}</div>
        </Drawer>
    );
}
