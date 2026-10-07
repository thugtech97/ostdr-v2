import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import { router } from '@inertiajs/react';
import { useState } from 'react';

/**
 * The legacy list filter bar: optional Date From / Date To with Apply / Reset, a debounced
 * keyword search and rows per page. Pass `list` from useListQuery(routeName, filters).
 * `children` adds extra filter controls before the Apply button.
 */
export default function ListToolbar({
    routeName,
    routeParams = {},
    filters,
    list,
    perPage,
    placeholder = 'Search...',
    dates = true,
    extraFilters = {},
    children,
}) {
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');

    const isFiltered = Boolean(
        filters.date_from || filters.date_to || filters.search || Object.values(extraFilters).some(Boolean),
    );

    const apply = () =>
        list.reload({
            date_from: dateFrom || undefined,
            date_to: dateTo || undefined,
            ...Object.fromEntries(Object.entries(extraFilters).map(([key, value]) => [key, value || undefined])),
            page: undefined,
        });

    const reset = () => {
        setDateFrom('');
        setDateTo('');
        list.setSearch('');
        router.get(route(routeName, routeParams), {}, { preserveScroll: true, replace: true });
    };

    return (
        <div className="flex flex-col gap-3 border-b border-gray-200 p-4 lg:flex-row lg:items-end lg:justify-between dark:border-gray-800">
            <div className="flex flex-wrap items-end gap-3">
                {dates && (
                    <>
                        <label className="text-xs font-medium text-gray-600 dark:text-gray-400">
                            Date From
                            <TextInput type="date" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} className="mt-1 block py-1.5 text-sm" />
                        </label>
                        <label className="text-xs font-medium text-gray-600 dark:text-gray-400">
                            Date To
                            <TextInput type="date" value={dateTo} onChange={(e) => setDateTo(e.target.value)} className="mt-1 block py-1.5 text-sm" />
                        </label>
                    </>
                )}
                {children}
                {(dates || children) && (
                    <>
                        <SecondaryButton onClick={apply}>Apply</SecondaryButton>
                        <button
                            type="button"
                            onClick={reset}
                            disabled={!isFiltered && !dateFrom && !dateTo}
                            className="px-2 py-2 text-xs font-semibold uppercase tracking-widest text-gray-500 hover:text-gray-800 disabled:opacity-40 dark:text-gray-400 dark:hover:text-gray-100"
                        >
                            Reset
                        </button>
                    </>
                )}
            </div>

            <div className="flex items-end gap-3">
                <TextInput
                    type="search"
                    value={list.search}
                    onChange={(e) => list.setSearch(e.target.value)}
                    placeholder={placeholder}
                    className="w-full lg:w-80"
                    aria-label="Search"
                />
                {perPage !== undefined && (
                    <SelectInput
                        value={perPage}
                        onChange={(e) => list.reload({ per_page: e.target.value, page: undefined })}
                        className="py-2 text-sm"
                        aria-label="Rows per page"
                    >
                        {[10, 20, 50].map((size) => (
                            <option key={size} value={size}>
                                {size} rows
                            </option>
                        ))}
                    </SelectInput>
                )}
            </div>
        </div>
    );
}
