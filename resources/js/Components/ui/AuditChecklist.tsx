import { cn } from '@/lib/cn';
import { Checkbox } from '@/Components/ui/Checkbox';

export interface AuditChecklistItem {
    id: string;
    label: string;
    description?: string;
    checked: boolean;
}

export interface AuditChecklistProps {
    items: AuditChecklistItem[];
    onChange: (id: string, checked: boolean) => void;
    readOnly?: boolean;
    className?: string;
    title?: string;
    subtitle?: string;
}

export function AuditChecklist({
    items,
    onChange,
    readOnly = false,
    className,
    title = 'Audit checklist',
    subtitle = 'Confirm each validation item before accepting a lead.',
}: AuditChecklistProps) {
    return (
        <div
            className={cn(
                'space-y-3 rounded-xl border border-rml-border bg-white p-4',
                className,
            )}
        >
            <div>
                <h3 className="text-sm font-semibold text-rml-text">
                    {title}
                </h3>
                <p className="text-sm text-rml-muted">
                    {subtitle}
                </p>
            </div>
            <ul className="divide-y divide-rml-border">
                {items.map((item) => (
                    <li key={item.id} className="py-3 first:pt-0 last:pb-0">
                        <Checkbox
                            checked={item.checked}
                            disabled={readOnly}
                            onChange={(event) =>
                                onChange(item.id, event.target.checked)
                            }
                            label={item.label}
                            description={item.description}
                        />
                    </li>
                ))}
            </ul>
        </div>
    );
}
