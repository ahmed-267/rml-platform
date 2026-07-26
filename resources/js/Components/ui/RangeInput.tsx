import { cn } from '@/lib/cn';

export type RangeInputProps = {
    label: string;
    minName: string;
    maxName: string;
    minValue: string;
    maxValue: string;
    onMinChange: (value: string) => void;
    onMaxChange: (value: string) => void;
    minPlaceholder?: string;
    maxPlaceholder?: string;
    unit?: string;
    className?: string;
};

/** Compact min–max pair in one control (range bar). */
export function RangeInput({
    label,
    minName,
    maxName,
    minValue,
    maxValue,
    onMinChange,
    onMaxChange,
    minPlaceholder = '0',
    maxPlaceholder = '∞',
    unit,
    className,
}: RangeInputProps) {
    return (
        <div className={cn('w-full space-y-1.5', className)}>
            <div className="flex items-center justify-between gap-2">
                <label className="block text-sm font-medium text-rml-text">
                    {label}
                </label>
                {unit ? (
                    <span className="text-xs text-rml-muted">{unit}</span>
                ) : null}
            </div>
            <div className="flex items-center overflow-hidden rounded-lg border border-rml-border bg-white shadow-sm focus-within:border-rml-primary focus-within:ring-2 focus-within:ring-rml-primary/20">
                <input
                    id={minName}
                    name={minName}
                    type="number"
                    min={0}
                    inputMode="decimal"
                    value={minValue}
                    placeholder={minPlaceholder}
                    aria-label={`${label} min`}
                    onChange={(e) => onMinChange(e.target.value)}
                    className="min-w-0 flex-1 border-0 bg-transparent px-3 py-2 text-sm text-rml-text placeholder:text-rml-muted/70 focus:outline-none focus:ring-0"
                />
                <span
                    className="shrink-0 px-1 text-xs font-medium text-rml-muted"
                    aria-hidden
                >
                    –
                </span>
                <input
                    id={maxName}
                    name={maxName}
                    type="number"
                    min={0}
                    inputMode="decimal"
                    value={maxValue}
                    placeholder={maxPlaceholder}
                    aria-label={`${label} max`}
                    onChange={(e) => onMaxChange(e.target.value)}
                    className="min-w-0 flex-1 border-0 bg-transparent px-3 py-2 text-sm text-rml-text placeholder:text-rml-muted/70 focus:outline-none focus:ring-0"
                />
            </div>
        </div>
    );
}
