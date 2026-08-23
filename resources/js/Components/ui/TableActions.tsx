import { Link } from '@inertiajs/react';
import {
    Ban,
    Check,
    ClipboardCheck,
    ClipboardPlus,
    CreditCard,
    Download,
    Eye,
    type LucideIcon,
    MapPin,
    Package,
    PauseCircle,
    Pencil,
    RotateCcw,
    ShoppingCart,
    Trash2,
    Wallet,
    X,
} from 'lucide-react';
import type {
    AnchorHTMLAttributes,
    ButtonHTMLAttributes,
    MouseEvent as ReactMouseEvent,
    ReactNode,
} from 'react';
import { Tooltip } from '@/Components/ui/Tooltip';
import { cn } from '@/lib/cn';

export type TableActionTone = 'default' | 'danger' | 'success' | 'warning';

const toneClass: Record<TableActionTone, string> = {
    default:
        'border-rml-border bg-white text-rml-text hover:bg-rml-background hover:border-rml-primary/40 hover:shadow-sm',
    danger: 'border-red-200 bg-red-50 text-rml-red hover:bg-red-100',
    success:
        'border-emerald-200 bg-emerald-50 text-rml-primary hover:bg-emerald-100',
    warning: 'border-amber-200 bg-amber-50 text-rml-amber hover:bg-amber-100',
};

type SharedProps = {
    label: string;
    icon?: LucideIcon;
    tone?: TableActionTone;
    className?: string;
};

const controlClass =
    'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border shadow-sm transition rml-focus-ring disabled:opacity-50';

export function TableActionButton({
    label,
    icon: Icon,
    tone = 'default',
    className,
    children,
    ...props
}: SharedProps &
    ButtonHTMLAttributes<HTMLButtonElement> & { children?: ReactNode }) {
    return (
        <Tooltip label={label}>
            <button
                type="button"
                aria-label={label}
                className={cn(controlClass, toneClass[tone], className)}
                {...props}
            >
                {children ??
                    (Icon ? <Icon className="h-4 w-4" aria-hidden /> : null)}
            </button>
        </Tooltip>
    );
}

export function TableActionLink({
    href,
    label,
    icon: Icon = Eye,
    tone = 'default',
    className,
    prefetch = true,
    external = false,
    onClick,
    ...props
}: SharedProps &
    AnchorHTMLAttributes<HTMLAnchorElement> & {
        href: string;
        prefetch?: boolean;
        external?: boolean;
    }) {
    const classes = cn(controlClass, toneClass[tone], className);

    if (external) {
        return (
            <Tooltip label={label}>
                <a
                    href={href}
                    aria-label={label}
                    target="_blank"
                    rel="noopener noreferrer"
                    className={classes}
                    onClick={onClick}
                    {...props}
                >
                    {Icon ? <Icon className="h-4 w-4" aria-hidden /> : null}
                </a>
            </Tooltip>
        );
    }

    return (
        <Tooltip label={label}>
            <Link
                href={href}
                prefetch={prefetch}
                aria-label={label}
                className={classes}
                onClick={
                    onClick as
                        | ((e: ReactMouseEvent) => void)
                        | undefined
                }
            >
                {Icon ? <Icon className="h-4 w-4" aria-hidden /> : null}
            </Link>
        </Tooltip>
    );
}

export function TableActions({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'inline-flex flex-nowrap items-center justify-start gap-1.5',
                className,
            )}
        >
            {children}
        </div>
    );
}

export const tableActionIcons = {
    view: Eye,
    edit: Pencil,
    delete: Trash2,
    audit: ClipboardCheck,
    assign: ClipboardPlus,
    markPaid: Wallet,
    download: Download,
    cancel: Ban,
    approve: Check,
    reject: X,
    suspend: PauseCircle,
    reinstate: RotateCcw,
    buy: ShoppingCart,
    pay: CreditCard,
    location: MapPin,
    package: Package,
} as const;
