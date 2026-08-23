import { TextareaHTMLAttributes, forwardRef } from 'react';
import { cn } from '@/lib/cn';

export interface TextareaProps
    extends TextareaHTMLAttributes<HTMLTextAreaElement> {
    label?: string;
    hint?: string;
    error?: string;
}

export const Textarea = forwardRef<HTMLTextAreaElement, TextareaProps>(
    (
        {
            className,
            label,
            hint,
            error,
            id,
            name,
            rows = 4,
            required,
            ...props
        },
        ref,
    ) => {
        const textareaId = id ?? name;
        const errorField = name ?? textareaId;

        return (
            <div
                className="w-full space-y-1.5"
                data-error-field={errorField || undefined}
            >
                {label && (
                    <label
                        htmlFor={textareaId}
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
                <textarea
                    ref={ref}
                    id={textareaId}
                    name={name}
                    rows={rows}
                    required={required}
                    aria-invalid={error ? true : undefined}
                    data-error-field={errorField || undefined}
                    className={cn(
                        'block w-full rounded-lg border border-rml-border bg-white px-3.5 py-2.5 text-sm text-rml-text shadow-sm placeholder:text-rml-muted/70 rml-focus-ring focus:border-rml-primary disabled:bg-rml-background disabled:text-rml-muted',
                        error &&
                            'border-rml-red focus:border-rml-red focus-visible:ring-rml-red',
                        className,
                    )}
                    {...props}
                />
                {error ? (
                    <p className="text-sm text-rml-red">{error}</p>
                ) : hint ? (
                    <p className="text-sm text-rml-muted">{hint}</p>
                ) : null}
            </div>
        );
    },
);

Textarea.displayName = 'Textarea';
