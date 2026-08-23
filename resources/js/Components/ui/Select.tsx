import { SelectHTMLAttributes, forwardRef } from 'react';
import { cn } from '@/lib/cn';

export interface SelectOption {
    label: string;
    value: string;
    disabled?: boolean;
}

export interface SelectProps extends SelectHTMLAttributes<HTMLSelectElement> {
    label?: string;
    hint?: string;
    error?: string;
    options: SelectOption[];
    placeholder?: string;
}

export const Select = forwardRef<HTMLSelectElement, SelectProps>(
    (
        {
            className,
            label,
            hint,
            error,
            id,
            name,
            options,
            placeholder,
            required,
            ...props
        },
        ref,
    ) => {
        const selectId = id ?? name;
        const errorField = name ?? selectId;

        return (
            <div
                className="w-full space-y-1.5"
                data-error-field={errorField || undefined}
            >
                {label && (
                    <label
                        htmlFor={selectId}
                        className="block text-sm font-medium text-rml-text"
                    >
                        {label}
                        {required ? (
                            <span className="ml-0.5 text-rml-red" aria-hidden>
                                *
                            </span>
                        ) : null}
                    </label>
                )}
                <select
                    ref={ref}
                    id={selectId}
                    name={name}
                    required={required}
                    aria-invalid={error ? true : undefined}
                    data-error-field={errorField || undefined}
                    className={cn(
                        'block w-full rounded-lg border border-rml-border bg-white px-3.5 py-2.5 text-sm text-rml-text shadow-sm rml-focus-ring focus:border-rml-primary disabled:bg-rml-background disabled:text-rml-muted',
                        error &&
                            'border-rml-red focus:border-rml-red focus-visible:ring-rml-red',
                        className,
                    )}
                    {...props}
                >
                    {placeholder && (
                        <option value="" disabled>
                            {placeholder}
                        </option>
                    )}
                    {options.map((option) => (
                        <option
                            key={option.value}
                            value={option.value}
                            disabled={option.disabled}
                        >
                            {option.label}
                        </option>
                    ))}
                </select>
                {error ? (
                    <p className="text-sm text-rml-red">{error}</p>
                ) : hint ? (
                    <p className="text-sm text-rml-muted">{hint}</p>
                ) : null}
            </div>
        );
    },
);

Select.displayName = 'Select';
