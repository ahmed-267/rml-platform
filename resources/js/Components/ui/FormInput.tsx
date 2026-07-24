import { InputHTMLAttributes, forwardRef } from 'react';
import { cn } from '@/lib/cn';

export interface FormInputProps extends InputHTMLAttributes<HTMLInputElement> {
    label?: string;
    hint?: string;
    error?: string;
    mono?: boolean;
}

export const FormInput = forwardRef<HTMLInputElement, FormInputProps>(
    ({ className, label, hint, error, id, mono = false, ...props }, ref) => {
        const inputId = id ?? props.name;

        return (
            <div className="w-full space-y-1.5">
                {label && (
                    <label
                        htmlFor={inputId}
                        className="block text-sm font-medium text-rml-text"
                    >
                        {label}
                        {props.required && (
                            <span className="ml-0.5 text-rml-red">*</span>
                        )}
                    </label>
                )}
                <input
                    ref={ref}
                    id={inputId}
                    className={cn(
                        'block w-full rounded-lg border border-rml-border bg-white px-3.5 py-2.5 text-sm text-rml-text shadow-sm placeholder:text-rml-muted/70 rml-focus-ring focus:border-rml-primary disabled:bg-rml-background disabled:text-rml-muted',
                        mono && 'font-mono',
                        error && 'border-rml-red focus:border-rml-red focus-visible:ring-rml-red',
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

FormInput.displayName = 'FormInput';
