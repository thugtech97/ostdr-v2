import SortableHeader from '@/Components/SortableHeader';

/**
 * Plain table shell used by the list pages. `headers` items are { label, column?, align? };
 * a header with a column sorts through `list.toggleSort` (from useListQuery).
 * Render rows with <DataTable.Cell>; `empty` shows when `count` is 0.
 */
export default function DataTable({ headers, list, count, empty = 'No record found.', children }) {
    return (
        <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                <thead className="bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        {headers.map((header, index) =>
                            header.column && list ? (
                                <SortableHeader
                                    key={index}
                                    column={header.column}
                                    label={header.label}
                                    sort={list.sort}
                                    direction={list.direction}
                                    onSort={list.toggleSort}
                                    className={header.align === 'right' ? 'text-right' : ''}
                                />
                            ) : (
                                <th
                                    key={index}
                                    scope="col"
                                    className={
                                        'whitespace-nowrap px-4 py-3 font-semibold text-gray-700 dark:text-gray-300 ' +
                                        (header.align === 'right' ? 'text-right' : 'text-left')
                                    }
                                >
                                    {header.label}
                                </th>
                            ),
                        )}
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                    {count === 0 && (
                        <tr>
                            <td colSpan={headers.length} className="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                                {empty}
                            </td>
                        </tr>
                    )}
                    {children}
                </tbody>
            </table>
        </div>
    );
}

DataTable.Row = function Row({ className = '', children, ...props }) {
    return (
        <tr className={'hover:bg-gray-50 dark:hover:bg-gray-800/40 ' + className} {...props}>
            {children}
        </tr>
    );
};

DataTable.Cell = function Cell({ strong = false, wrap = false, align, className = '', children, ...props }) {
    return (
        <td
            className={
                'px-4 py-3 ' +
                (wrap ? '' : 'whitespace-nowrap ') +
                (strong ? 'font-medium text-gray-900 dark:text-gray-100 ' : 'text-gray-700 dark:text-gray-300 ') +
                (align === 'right' ? 'text-right ' : '') +
                className
            }
            {...props}
        >
            {children}
        </td>
    );
};

DataTable.Actions = function Actions({ children }) {
    return (
        <td className="whitespace-nowrap px-4 py-3 text-right">
            <div className="inline-flex items-center gap-1">{children}</div>
        </td>
    );
};
