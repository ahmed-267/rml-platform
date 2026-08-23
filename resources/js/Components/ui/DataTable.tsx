import { ReactNode } from 'react';
import { cn } from '@/lib/cn';

export interface DataTableColumn<T> {
    id: string;
    header: ReactNode;
    cell: (row: T) => ReactNode;
    className?: string;
    hideOnMobile?: boolean;
}

export interface DataTableProps<T> {
    columns: DataTableColumn<T>[];
    data: T[];
    getRowId: (row: T) => string;
    emptyMessage?: string;
    className?: string;
    tableClassName?: string;
    dense?: boolean;
    fixedLayout?: boolean;
    onRowClick?: (row: T) => void;
}

export function DataTable<T>({
    columns,
    data,
    getRowId,
    emptyMessage = 'No records found.',
    className,
    tableClassName,
    dense = false,
    fixedLayout = false,
    onRowClick,
}: DataTableProps<T>) {
    const cellPad = dense ? 'px-2.5 py-2' : 'px-4 py-3';

    return (
        <div
            className={cn(
                'overflow-hidden rounded-xl border border-rml-border bg-white',
                className,
            )}
        >
            <div className="overflow-x-auto overscroll-x-contain">
                <table
                    className={cn(
                        'border-collapse divide-y divide-rml-border text-left text-sm',
                        fixedLayout
                            ? 'w-full table-fixed'
                            : 'w-max min-w-full',
                        tableClassName,
                    )}
                >
                    <thead className="bg-rml-background">
                        <tr>
                            {columns.map((column) => (
                                    <th
                                        key={column.id}
                                        scope="col"
                                        className={cn(
                                            cellPad,
                                            'text-xs font-semibold uppercase tracking-wide text-rml-muted',
                                            !fixedLayout && 'whitespace-nowrap',
                                            column.className,
                                        )}
                                    >
                                        {column.header}
                                    </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-rml-border">
                        {data.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={columns.length}
                                    className={cn(
                                        cellPad,
                                        'py-10 text-center text-rml-muted',
                                    )}
                                >
                                    {emptyMessage}
                                </td>
                            </tr>
                        ) : (
                            data.map((row) => (
                                <tr
                                    key={getRowId(row)}
                                    className={cn(
                                        'bg-white hover:bg-rml-background/80',
                                        onRowClick && 'cursor-pointer',
                                    )}
                                    onClick={() => onRowClick?.(row)}
                                >
                                    {columns.map((column) => (
                                            <td
                                                key={column.id}
                                                className={cn(
                                                    cellPad,
                                                    'text-rml-text',
                                                    !fixedLayout &&
                                                        'whitespace-nowrap',
                                                    column.className,
                                                )}
                                            >
                                                {column.cell(row)}
                                            </td>
                                    ))}
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
