/**
 * Table header cell that toggles server-side sorting.
 */
export default function SortableHeader({ column, label, sort, direction, onSort, className = '' }) {
    const active = sort === column;

    return (
        <th
            scope="col"
            aria-sort={active ? (direction === 'asc' ? 'ascending' : 'descending') : undefined}
            className={'whitespace-nowrap px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300 ' + className}
        >
            <button
                type="button"
                onClick={() => onSort(column)}
                className="inline-flex items-center gap-1 hover:text-gray-900 dark:hover:text-white"
            >
                {label}
                <span className="text-xs text-gray-400" aria-hidden="true">
                    {active ? (direction === 'asc' ? '▲' : '▼') : '↕'}
                </span>
            </button>
        </th>
    );
}
