import { ButtonHTMLAttributes, forwardRef } from 'react';
import { cn } from '@/lib/cn';

type ButtonVariant = 'primary' | 'secondary' | 'outline' | 'ghost' | 'danger' | 'soft';
type ButtonSize = 'sm' | 'md' | 'lg';

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: ButtonVariant;
    size?: ButtonSize;
    fullWidth?: boolean;
}

const variantClasses: Record<ButtonVariant, string> = {
    primary:
        'bg-gradient-to-b from-emerald-500 to-rml-primary text-white hover:from-emerald-400 hover:to-green-700 shadow-sm shadow-emerald-900/10 border border-transparent',
    secondary:
        'bg-rml-sidebar text-white hover:bg-slate-800 shadow-sm border border-transparent',
    outline:
        'bg-white text-rml-text border-rml-border hover:bg-rml-background hover:border-slate-300 shadow-sm border',
    ghost: 'bg-transparent text-rml-muted hover:bg-rml-background hover:text-rml-text border border-transparent',
    danger: 'bg-gradient-to-b from-red-500 to-rml-red text-white hover:from-red-400 hover:to-red-700 shadow-sm border border-transparent',
    soft: 'bg-rml-primary-light text-rml-primary hover:bg-rml-primary-lighter border border-transparent',
};

const sizeClasses: Record<ButtonSize, string> = {
    sm: 'h-9 px-3.5 text-sm gap-1.5',
    md: 'h-10 px-4 text-sm gap-2',
    lg: 'h-11 px-5 text-base gap-2',
};

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
    (
        {
            className,
            variant = 'primary',
            size = 'md',
            fullWidth = false,
            type = 'button',
            disabled,
            children,
            ...props
        },
        ref,
    ) => {
        return (
            <button
                ref={ref}
                type={type}
                disabled={disabled}
                className={cn(
                    'inline-flex items-center justify-center rounded-xl font-semibold transition-all duration-150 rml-focus-ring disabled:cursor-not-allowed disabled:opacity-50 active:scale-[0.98]',
                    variantClasses[variant],
                    sizeClasses[size],
                    fullWidth && 'w-full',
                    className,
                )}
                {...props}
            >
                {children}
            </button>
        );
    },
);

Button.displayName = 'Button';
