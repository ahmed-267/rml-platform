import { cn } from '@/lib/cn';
import { Button } from '@/Components/ui/Button';
import { Checkbox } from '@/Components/ui/Checkbox';

export interface AuditChecklistItem {
    id: string;
    label: string;
    description?: string;
    checked: boolean;
    required?: boolean;
}

export interface AuditChecklistProps {
    items: AuditChecklistItem[];
    onChange: (id: string, checked: boolean) => void;
    onCheckAll?: () => void;
    onUncheckAll?: () => void;
    checkAllLabel?: string;
    uncheckAllLabel?: string;
    readOnly?: boolean;
    className?: string;
    title?: string;
    subtitle?: string;
}

export function AuditChecklist({
    items,
    onChange,
    onCheckAll,
    onUncheckAll,
    checkAllLabel = 'Check all',
    uncheckAllLabel = 'Uncheck all',
    readOnly = false,
    className,
    title,
    subtitle,
}: AuditChecklistProps) {
    const allChecked = items.length > 0 && items.every((item) => item.checked);

    return (
        <div className={cn('space-y-1', className)}>
            <div className="flex flex-wrap items-start justify-between gap-2">
                {(title || subtitle) && (
                    <div className="min-w-0">
                        {title ? (
                            <h3 className="text-sm font-semibold text-rml-text">
                                {title}
                            </h3>
                        ) : null}
                        {subtitle ? (
                            <p className="text-sm text-rml-muted">{subtitle}</p>
                        ) : null}
                    </div>
                )}
                {!readOnly && onCheckAll && (
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() =>
                            allChecked ? onUncheckAll?.() : onCheckAll()
                        }
                    >
                        {allChecked ? uncheckAllLabel : checkAllLabel}
                    </Button>
                )}
            </div>
            <ul className="divide-y divide-slate-200">
                {items.map((item) => (
                    <li key={item.id} className="py-3 first:pt-0 last:pb-0">
                        <Checkbox
                            checked={item.checked}
                            disabled={readOnly}
                            onChange={(event) =>
                                onChange(item.id, event.target.checked)
                            }
                            label={
                                item.required
                                    ? `${item.label} *`
                                    : item.label
                            }
                            description={item.description}
                        />
                    </li>
                ))}
            </ul>
        </div>
    );
}
