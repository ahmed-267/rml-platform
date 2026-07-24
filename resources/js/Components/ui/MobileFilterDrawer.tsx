import { ReactNode } from 'react';
import { Drawer } from '@/Components/ui/Drawer';
import { Button } from '@/Components/ui/Button';

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
    title = 'Filters',
}: MobileFilterDrawerProps) {
    return (
        <Drawer
            open={open}
            onClose={onClose}
            title={title}
            side="bottom"
            footer={
                <div className="flex gap-2">
                    {onReset && (
                        <Button variant="outline" fullWidth onClick={onReset}>
                            Reset
                        </Button>
                    )}
                    <Button
                        fullWidth
                        onClick={() => {
                            onApply?.();
                            onClose();
                        }}
                    >
                        Apply filters
                    </Button>
                </div>
            }
        >
            <div className="space-y-4">{children}</div>
        </Drawer>
    );
}
