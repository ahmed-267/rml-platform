import { InputHTMLAttributes, forwardRef } from 'react';
import { cn } from '@/lib/cn';

export interface CheckboxProps
    extends Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> {
    label?: string;
    description?: string;
    error?: string;
}

export const Checkbox = forwardRef<HTMLInputElement, CheckboxProps>(
    ({ className, label, description, error, id, ...props }, ref) => {
        const checkboxId = id ?? props.name;

        return (
            <div className="space-y-1">
                <label
                    htmlFor={checkboxId}
                    className="flex cursor-pointer items-start gap-3"
                >
                    <input
                        ref={ref}
                        id={checkboxId}
                        type="checkbox"
                        className={cn(
                            'mt-0.5 h-4 w-4 rounded border-rml-border text-rml-primary focus:ring-rml-primary',
                            className,
                        )}
                        {...props}
                    />
                    {(label || description) && (
                        <span className="space-y-0.5">
                            {label && (
                                <span className="block text-sm font-medium text-rml-text">
                                    {label}
                                </span>
                            )}
                            {description && (
                                <span className="block text-sm text-rml-muted">
                                    {description}
                                </span>
                            )}
                        </span>
                    )}
                </label>
                {error && <p className="text-sm text-rml-red">{error}</p>}
            </div>
        );
    },
);

Checkbox.displayName = 'Checkbox';
