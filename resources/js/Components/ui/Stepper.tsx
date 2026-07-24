import { Check } from 'lucide-react';
import { cn } from '@/lib/cn';

export interface StepperItem {
    id: string;
    label: string;
    complete?: boolean;
    optional?: boolean;
}

export interface StepperProps {
    steps: StepperItem[];
    current: string;
    onChange?: (id: string) => void;
    className?: string;
}

export function Stepper({ steps, current, onChange, className }: StepperProps) {
    const currentIndex = Math.max(
        0,
        steps.findIndex((step) => step.id === current),
    );

    return (
        <nav
            aria-label="Progress"
            className={cn(
                'overflow-x-auto rounded-xl border border-rml-border bg-white px-2 py-2 sm:px-3',
                className,
            )}
        >
            <ol className="flex min-w-max items-center gap-1 sm:gap-2">
                {steps.map((step, index) => {
                    const active = step.id === current;
                    const done = Boolean(step.complete) && !active;
                    const clickable = typeof onChange === 'function';

                    return (
                        <li key={step.id} className="flex items-center gap-1 sm:gap-2">
                            {index > 0 && (
                                <span
                                    className={cn(
                                        'mx-0.5 hidden h-px w-4 sm:block sm:w-6',
                                        index <= currentIndex
                                            ? 'bg-rml-primary'
                                            : 'bg-rml-border',
                                    )}
                                    aria-hidden
                                />
                            )}
                            <button
                                type="button"
                                disabled={!clickable}
                                onClick={() => onChange?.(step.id)}
                                className={cn(
                                    'inline-flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-left text-sm transition-colors',
                                    active &&
                                        'bg-rml-primary-lighter text-rml-primary',
                                    !active &&
                                        done &&
                                        'text-rml-text hover:bg-rml-background',
                                    !active &&
                                        !done &&
                                        'text-rml-muted hover:bg-rml-background hover:text-rml-text',
                                    !clickable && 'cursor-default',
                                )}
                                aria-current={active ? 'step' : undefined}
                            >
                                <span
                                    className={cn(
                                        'inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                                        active &&
                                            'bg-rml-primary text-white',
                                        done &&
                                            'bg-rml-primary-light text-rml-primary',
                                        !active &&
                                            !done &&
                                            'bg-rml-background text-rml-muted',
                                    )}
                                >
                                    {done ? (
                                        <Check className="h-3.5 w-3.5" aria-hidden />
                                    ) : (
                                        index + 1
                                    )}
                                </span>
                                <span className="hidden font-medium sm:inline">
                                    {step.label}
                                </span>
                            </button>
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
