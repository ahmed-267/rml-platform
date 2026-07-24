import {
    ChevronsLeft,
    ChevronLeft,
    ChevronRight,
    ChevronsRight,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { cn } from '@/lib/cn';
import { Button } from '@/Components/ui/Button';

export interface PaginationProps {
    page: number;
    pageCount: number;
    onPageChange: (page: number) => void;
    className?: string;
    totalLabel?: string;
    perPage?: number;
    perPageOptions?: number[];
    onPerPageChange?: (perPage: number) => void;
    labels?: {
        itemsPerPage: string;
        page: string;
        of: string;
        first: string;
        previous: string;
        next: string;
        last: string;
    };
}

export function Pagination({
    page,
    pageCount,
    onPageChange,
    className,
    totalLabel,
    perPage,
    perPageOptions = [10, 25, 50, 100],
    onPerPageChange,
    labels,
}: PaginationProps) {
    const advanced = Boolean(labels && onPerPageChange);
    const [draftPage, setDraftPage] = useState(String(page));

    useEffect(() => {
        setDraftPage(String(page));
    }, [page]);

    if (!advanced && pageCount <= 1) {
        return null;
    }

    const commitPage = () => {
        const parsed = Number.parseInt(draftPage, 10);
        if (!Number.isFinite(parsed)) {
            setDraftPage(String(page));
            return;
        }
        const next = Math.min(Math.max(parsed, 1), Math.max(pageCount, 1));
        setDraftPage(String(next));
        if (next !== page) {
            onPageChange(next);
        }
    };

    if (!advanced) {
        return (
            <div
                className={cn(
                    'flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between',
                    className,
                )}
            >
                <p className="text-sm text-rml-muted">
                    {totalLabel ?? `Page ${page} of ${pageCount}`}
                </p>
                <div className="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={page <= 1}
                        onClick={() => onPageChange(page - 1)}
                    >
                        <ChevronLeft className="h-4 w-4" />
                        Previous
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={page >= pageCount}
                        onClick={() => onPageChange(page + 1)}
                    >
                        Next
                        <ChevronRight className="h-4 w-4" />
                    </Button>
                </div>
            </div>
        );
    }

    const safePageCount = Math.max(pageCount, 1);

    return (
        <div
            className={cn(
                'flex flex-col gap-3 sm:grid sm:grid-cols-[1fr_auto_1fr] sm:items-center',
                className,
            )}
        >
            <div className="flex items-center gap-2 sm:justify-start">
                <label className="whitespace-nowrap text-sm text-rml-muted">
                    {labels!.itemsPerPage}
                </label>
                {/* Native select arrow only — no custom chevron (avoids double arrow). */}
                <select
                    value={perPage ?? 10}
                    onChange={(e) =>
                        onPerPageChange?.(
                            Number.parseInt(e.target.value, 10),
                        )
                    }
                    className="h-9 w-[4.75rem] rounded-lg border border-rml-border bg-white py-1.5 pl-2.5 pr-7 text-sm text-rml-text shadow-sm rml-focus-ring focus:border-rml-primary"
                    aria-label={labels!.itemsPerPage}
                >
                    {perPageOptions.map((option) => (
                        <option key={option} value={option}>
                            {option}
                        </option>
                    ))}
                </select>
            </div>

            <div className="flex items-center justify-center gap-1.5">
                <Button
                    variant="outline"
                    size="sm"
                    className="!px-2"
                    disabled={page <= 1}
                    onClick={() => onPageChange(1)}
                    aria-label={labels!.first}
                >
                    <ChevronsLeft className="h-4 w-4" />
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    className="!px-2"
                    disabled={page <= 1}
                    onClick={() => onPageChange(page - 1)}
                    aria-label={labels!.previous}
                >
                    <ChevronLeft className="h-4 w-4" />
                </Button>
                <div className="flex items-center gap-1.5 px-1 text-sm text-rml-text">
                    <span>{labels!.page}</span>
                    <input
                        type="number"
                        min={1}
                        max={safePageCount}
                        value={draftPage}
                        onChange={(e) => setDraftPage(e.target.value)}
                        onBlur={commitPage}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                commitPage();
                            }
                        }}
                        className="w-14 rounded-lg border border-rml-border bg-white px-2 py-1 text-center text-sm shadow-sm rml-focus-ring focus:border-rml-primary"
                        aria-label={labels!.page}
                    />
                    <span>
                        {labels!.of} {safePageCount}
                    </span>
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    className="!px-2"
                    disabled={page >= safePageCount}
                    onClick={() => onPageChange(page + 1)}
                    aria-label={labels!.next}
                >
                    <ChevronRight className="h-4 w-4" />
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    className="!px-2"
                    disabled={page >= safePageCount}
                    onClick={() => onPageChange(safePageCount)}
                    aria-label={labels!.last}
                >
                    <ChevronsRight className="h-4 w-4" />
                </Button>
            </div>

            <div className="hidden sm:block" />
        </div>
    );
}
