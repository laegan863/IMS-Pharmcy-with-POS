import type { ReactNode } from 'react';

/** Laravel's LengthAwarePaginator as JSON. */
export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

/** What App\Support\TableQuery applied, sent back with every page. */
export type TableState = {
    search: string;
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
    filters: Record<string, string>;
};

export type Column<T> = {
    /** Unique key; also the sort key when sortable. */
    key: string;
    header: string;
    sortable?: boolean;
    align?: 'left' | 'right' | 'center';
    className?: string;
    cell: (row: T) => ReactNode;
};

export type TableFilter = {
    name: string;
    label: string;
    options: { value: string; label: string }[];
};
