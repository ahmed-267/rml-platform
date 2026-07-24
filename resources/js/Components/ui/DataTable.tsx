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
    onRowClick?: (row: T) => void;
}

export function DataTable<T>({
    columns,
    data,
    getRowId,
    emptyMessage = 'No records found.',
    className,
    onRowClick,
}: DataTableProps<T>) {
    return (
        <div
            className={cn(
                'overflow-hidden rounded-xl border border-rml-border bg-white',
                className,
            )}
        >
            <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-rml-border text-left text-sm">
                    <thead className="bg-rml-background">
                        <tr>
                            {columns.map((column) => (
                                <th
                                    key={column.id}
                                    scope="col"
                                    className={cn(
                                        'px-4 py-3 text-xs font-semibold uppercase tracking-wide text-rml-muted',
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
                                    className="px-4 py-10 text-center text-rml-muted"
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
                                                'whitespace-nowrap px-4 py-3 text-rml-text',
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
