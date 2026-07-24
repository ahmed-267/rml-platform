import { ChangeEvent, useRef } from 'react';
import { FileUp, X } from 'lucide-react';
import { usePage } from '@inertiajs/react';
import { cn } from '@/lib/cn';
import { Button } from '@/Components/ui/Button';
import type { PageProps } from '@/types';

export interface UploadedFileMeta {
    id: string;
    name: string;
    sizeLabel?: string;
}

export interface FileUploadProps {
    label?: string;
    hint?: string;
    error?: string;
    accept?: string;
    multiple?: boolean;
    required?: boolean;
    files?: UploadedFileMeta[];
    onFilesSelected?: (files: FileList) => void;
    onRemove?: (id: string) => void;
    className?: string;
    disabled?: boolean;
}

export function FileUpload({
    label,
    hint,
    error,
    accept,
    multiple = true,
    required = false,
    files = [],
    onFilesSelected,
    onRemove,
    className,
    disabled = false,
}: FileUploadProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const { translations } = usePage<PageProps>().props;
    const common = translations.common ?? {};
    const resolvedLabel = label ?? common.upload_files ?? 'Upload files';
    const resolvedHint = hint ?? common.upload_hint ?? 'Photos, video, PDF documents';
    const tapLabel = common.tap_to_upload ?? 'Tap to upload';
    const closeLabel = common.close ?? 'Close';

    const handleChange = (event: ChangeEvent<HTMLInputElement>) => {
        if (event.target.files && event.target.files.length > 0) {
            onFilesSelected?.(event.target.files);
            event.target.value = '';
        }
    };

    return (
        <div className={cn('space-y-3', className)}>
            {resolvedLabel && (
                <p className="text-sm font-medium text-rml-text">
                    {resolvedLabel}
                    {required && (
                        <span className="ml-0.5 text-rml-red" aria-hidden>
                            *
                        </span>
                    )}
                </p>
            )}
            <button
                type="button"
                disabled={disabled}
                onClick={() => inputRef.current?.click()}
                className={cn(
                    'flex w-full flex-col items-center justify-center rounded-xl border border-dashed border-rml-border bg-rml-background px-4 py-8 text-center transition hover:border-rml-primary hover:bg-rml-primary-lighter/40 disabled:cursor-not-allowed disabled:opacity-50',
                    error && 'border-rml-red',
                )}
            >
                <FileUp className="mb-2 h-6 w-6 text-rml-primary" />
                <span className="text-sm font-semibold text-rml-text">
                    {tapLabel}
                </span>
                <span className="mt-1 text-xs text-rml-muted">{resolvedHint}</span>
            </button>
            <input
                ref={inputRef}
                type="file"
                className="hidden"
                accept={accept}
                multiple={multiple}
                disabled={disabled}
                onChange={handleChange}
            />
            {error && <p className="text-sm text-rml-red">{error}</p>}
            {files.length > 0 && (
                <ul className="space-y-2">
                    {files.map((file) => (
                        <li
                            key={file.id}
                            className="flex items-center justify-between gap-3 rounded-lg border border-rml-border bg-white px-3 py-2"
                        >
                            <div className="min-w-0">
                                <p className="truncate text-sm font-medium text-rml-text">
                                    {file.name}
                                </p>
                                {file.sizeLabel && (
                                    <p className="text-xs text-rml-muted">
                                        {file.sizeLabel}
                                    </p>
                                )}
                            </div>
                            {onRemove && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="!px-2"
                                    onClick={() => onRemove(file.id)}
                                    aria-label={closeLabel}
                                >
                                    <X className="h-4 w-4" />
                                </Button>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
