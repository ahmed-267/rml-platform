import { Package } from 'lucide-react';
import { cn } from '@/lib/cn';
import { Button } from '@/Components/ui/Button';
import { StatusBadge } from '@/Components/ui/StatusBadge';

export interface LeadPackageCardProps {
    title: string;
    description?: string;
    leadCount: number;
    totalSize: number;
    unitPriceApprox?: number;
    totalPrice: number;
    zones?: string[];
    badge?: string;
    onSelect?: () => void;
    className?: string;
}

export function LeadPackageCard({
    title,
    description,
    leadCount,
    totalSize,
    unitPriceApprox,
    totalPrice,
    zones = [],
    badge,
    onSelect,
    className,
}: LeadPackageCardProps) {
    return (
        <article className={cn('rml-card flex flex-col p-5', className)}>
            <div className="mb-4 flex items-start justify-between gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-rml-primary-light text-rml-primary">
                    <Package className="h-5 w-5" />
                </div>
                {badge && <StatusBadge label={badge} tone="primary" />}
            </div>
            <h3 className="text-base font-semibold text-rml-text">{title}</h3>
            {description && (
                <p className="mt-1 text-sm text-rml-muted">{description}</p>
            )}
            <dl className="mt-4 grid grid-cols-2 gap-3 text-sm">
                <div>
                    <dt className="text-rml-muted">Leads</dt>
                    <dd className="font-semibold text-rml-text">{leadCount}</dd>
                </div>
                <div>
                    <dt className="text-rml-muted">Total size</dt>
                    <dd className="font-semibold text-rml-text">
                        {totalSize.toLocaleString()} m²
                    </dd>
                </div>
                {typeof unitPriceApprox === 'number' && (
                    <div>
                        <dt className="text-rml-muted">Avg €/m²</dt>
                        <dd className="font-semibold text-rml-text">
                            €{unitPriceApprox.toFixed(2)}
                        </dd>
                    </div>
                )}
                <div>
                    <dt className="text-rml-muted">Package total</dt>
                    <dd className="font-semibold text-rml-primary">
                        €
                        {totalPrice.toLocaleString(undefined, {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2,
                        })}
                    </dd>
                </div>
            </dl>
            {zones.length > 0 && (
                <div className="mt-4 flex flex-wrap gap-1.5">
                    {zones.map((zone) => (
                        <StatusBadge key={zone} label={zone} tone="neutral" />
                    ))}
                </div>
            )}
            {onSelect && (
                <Button className="mt-5" onClick={onSelect} fullWidth>
                    Proceed to payment
                </Button>
            )}
        </article>
    );
}
