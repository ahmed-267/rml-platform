import { StatusBadge, type BadgeTone } from '@/Components/ui/StatusBadge';
import { cn } from '@/lib/cn';

export type FloatingLeadStatus =
    | 'audited'
    | 'pending'
    | 'paid'
    | 'locked'
    | 'released';

interface FloatingLeadCardProps {
    reference: string;
    zone: string;
    size: string;
    scheme: string;
    statusLabel: string;
    detailLabel: string;
    zoneLabel: string;
    sizeLabel: string;
    schemeLabel: string;
    statusTone?: FloatingLeadStatus;
    className?: string;
    float?: boolean;
}

const toneToBadge: Record<FloatingLeadStatus, BadgeTone> = {
    audited: 'success',
    pending: 'warning',
    paid: 'success',
    locked: 'neutral',
    released: 'info',
};

export default function FloatingLeadCard({
    reference,
    zone,
    size,
    scheme,
    statusLabel,
    detailLabel,
    zoneLabel,
    sizeLabel,
    schemeLabel,
    statusTone = 'audited',
    className,
    float = false,
}: FloatingLeadCardProps) {
    return (
        <article
            className={cn(
                'rml-card p-4 shadow-md',
                float && 'animate-rml-float',
                className,
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <p className="font-mono text-sm font-semibold text-rml-text">
                    {reference}
                </p>
                <StatusBadge
                    label={statusLabel}
                    tone={toneToBadge[statusTone]}
                />
            </div>
            <dl className="mt-3 grid grid-cols-2 gap-2 text-xs text-rml-muted">
                <div>
                    <dt className="uppercase tracking-wide">{zoneLabel}</dt>
                    <dd className="mt-0.5 font-semibold text-rml-text">{zone}</dd>
                </div>
                <div>
                    <dt className="uppercase tracking-wide">{sizeLabel}</dt>
                    <dd className="mt-0.5 font-semibold text-rml-text">{size}</dd>
                </div>
                <div className="col-span-2">
                    <dt className="uppercase tracking-wide">{schemeLabel}</dt>
                    <dd className="mt-0.5 font-semibold text-rml-text">
                        {scheme}
                    </dd>
                </div>
            </dl>
            <p className="mt-3 border-t border-rml-border pt-2 text-xs font-medium text-rml-muted">
                {detailLabel}
            </p>
        </article>
    );
}
