import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

/**
 * Server-side list state (search, sort, paging, extra filters) kept in the URL query string.
 * `defaultSort` is what the server sorts by when no sort is given.
 */
export default function useListQuery(routeName, filters, { defaultSort = 'id', defaultDirection = 'desc' } = {}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const firstRender = useRef(true);

    const sort = filters.sort ?? defaultSort;
    const direction = filters.direction ?? defaultDirection;

    const reload = (params) =>
        router.get(
            route(routeName),
            Object.fromEntries(
                Object.entries({ ...filters, ...params }).filter(([, value]) => value !== undefined && value !== null && value !== ''),
            ),
            { preserveState: true, preserveScroll: true, replace: true },
        );

    // Debounced search; the first render already has its results.
    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;
            return;
        }

        const timer = setTimeout(() => reload({ search, page: undefined }), 350);

        return () => clearTimeout(timer);
    }, [search]);

    const toggleSort = (column) =>
        reload({
            sort: column,
            direction: sort === column && direction === 'asc' ? 'desc' : 'asc',
            page: undefined,
        });

    return { search, setSearch, sort, direction, reload, toggleSort };
}
