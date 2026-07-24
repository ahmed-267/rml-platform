import { cn } from '@/lib/cn';
import { Button } from '@/Components/ui/Button';

export interface PaymentSummaryBarLabels {
    selected?: string;
    leads?: string;
    totalSize?: string;
    total?: string;
    clear?: string;
    payNow?: string;
}

export interface PaymentSummaryBarProps {
    selectedCount: number;
    totalSize: number;
    totalPrice: number;
    currency?: string;
    labels?: PaymentSummaryBarLabels;
    onClear?: () => void;
    onPay?: () => void;
    stickyMobile?: boolean;
    className?: string;
    disabled?: boolean;
}

export function PaymentSummaryBar({
    selectedCount,
    totalSize,
    totalPrice,
    currency = '€',
    labels,
    onClear,
    onPay,
    stickyMobile = true,
    className,
    disabled = false,
}: PaymentSummaryBarProps) {
    const selectedLabel = labels?.selected ?? 'Selected';
    const leadsLabel = labels?.leads ?? 'leads';
    const totalSizeLabel = labels?.totalSize ?? 'Total size';
    const totalLabel = labels?.total ?? 'Total';
    const clearLabel = labels?.clear ?? 'Clear';
    const payNowLabel = labels?.payNow ?? 'Pay now';

    const formattedPrice = `${currency}${totalPrice.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;

    return (
        <div
            className={cn(
                'rounded-xl border border-rml-border bg-white p-4 shadow-card',
                stickyMobile &&
                    'fixed inset-x-0 bottom-0 z-40 rounded-none border-x-0 border-b-0 pb-[max(1rem,env(safe-area-inset-bottom))] shadow-dropdown lg:static lg:rounded-xl lg:border lg:pb-4 lg:shadow-card',
                className,
            )}
        >
            <div className="mx-auto flex max-w-content flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="grid grid-cols-3 gap-3 text-sm sm:flex sm:gap-6">
                    <div>
                        <p className="text-rml-muted">{selectedLabel}</p>
                        <p className="font-semibold text-rml-text">
                            {selectedCount} {leadsLabel}
                        </p>
                    </div>
                    <div>
                        <p className="text-rml-muted">{totalSizeLabel}</p>
                        <p className="font-semibold text-rml-text">
                            {totalSize.toLocaleString()} m²
                        </p>
                    </div>
                    <div>
                        <p className="text-rml-muted">{totalLabel}</p>
                        <p className="font-semibold text-rml-primary">
                            {formattedPrice}
                        </p>
                    </div>
                </div>
                <div className="flex gap-2">
                    {onClear && (
                        <Button
                            variant="outline"
                            onClick={onClear}
                            disabled={selectedCount === 0}
                        >
                            {clearLabel}
                        </Button>
                    )}
                    {onPay && (
                        <Button
                            onClick={onPay}
                            disabled={disabled || selectedCount === 0}
                            className="flex-1 sm:flex-none"
                        >
                            {payNowLabel}
                        </Button>
                    )}
                </div>
            </div>
        </div>
    );
}
