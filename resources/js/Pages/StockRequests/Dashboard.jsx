import Card from '@/Components/Card';
import PageHeader from '@/Components/PageHeader';
import RequestStatusBadge from '@/Components/RequestStatusBadge';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const SEARCH_FIELDS = ['transaction_no', 'dept', 'date_filed', 'time_filed', 'date_needed', 'status', 'cost_code'];

const ACCENTS = {
    danger: { bar: 'bg-red-500', tile: 'text-red-600 dark:text-red-400' },
    warning: { bar: 'bg-amber-500', tile: 'text-amber-600 dark:text-amber-400' },
    success: { bar: 'bg-green-500', tile: 'text-green-600 dark:text-green-400' },
    brand: { bar: 'bg-brand-600', tile: 'text-brand-900 dark:text-brand-200' },
};

function Tile({ label, value, accent }) {
    return (
        <Card className="relative p-5">
            <span className={`absolute inset-y-0 left-0 w-1 ${ACCENTS[accent].bar}`} aria-hidden="true" />
            <p className="text-sm font-medium text-gray-500 dark:text-gray-400">{label}</p>
            <p className={`mt-1 text-3xl font-semibold tabular-nums ${ACCENTS[accent].tile}`}>{value}</p>
        </Card>
    );
}

/**
 * One dashboard panel: its own search box and paging, filtered in the browser like legacy.
 */
function Panel({ title, rows, accent, emptyText, badgeFor }) {
    const [search, setSearch] = useState('');
    const [perPage, setPerPage] = useState(10);
    const [page, setPage] = useState(1);

    const filtered = useMemo(() => {
        const term = search.trim().toLowerCase();

        return term
            ? rows.filter((row) => SEARCH_FIELDS.some((field) => String(row[field] ?? '').toLowerCase().includes(term)))
            : rows;
    }, [rows, search]);

    const pages = Math.max(1, Math.ceil(filtered.length / perPage));
    const current = Math.min(page, pages);
    const visible = filtered.slice((current - 1) * perPage, current * perPage);

    return (
        <Card>
            <div className="flex flex-col gap-3 border-b border-gray-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800">
                <div className="flex items-center gap-2">
                    <span className={`h-2.5 w-2.5 rounded-full ${ACCENTS[accent].bar}`} aria-hidden="true" />
                    <h2 className="font-semibold text-gray-900 dark:text-gray-100">{title}</h2>
                    <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                        {rows.length}
                    </span>
                </div>
                <TextInput
                    type="search"
                    value={search}
                    onChange={(e) => {
                        setSearch(e.target.value);
                        setPage(1);
                    }}
                    placeholder={`Search ${title.toLowerCase()}...`}
                    className="w-full py-1.5 text-sm sm:w-64"
                    aria-label={`Search ${title}`}
                />
            </div>

            <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead className="bg-gray-50 dark:bg-gray-800/50">
                        <tr>
                            {['Transaction #', 'Cost Code', 'Date Requested', 'Time Requested', 'Department', 'Date Needed', 'Status'].map((heading) => (
                                <th key={heading} scope="col" className="whitespace-nowrap px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">
                                    {heading}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                        {visible.length === 0 && (
                            <tr>
                                <td colSpan={7} className="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                    {search ? 'Nothing matches your search.' : emptyText}
                                </td>
                            </tr>
                        )}
                        {visible.map((row) => (
                            <tr key={row.id} className="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                <td className="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{row.transaction_no}</td>
                                <td className="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-300">{row.cost_code}</td>
                                <td className="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-300">{row.date_filed}</td>
                                <td className="whitespace-nowrap px-4 py-3 text-gray-500 dark:text-gray-400">{row.time_filed}</td>
                                <td className="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-300">{row.dept}</td>
                                <td className="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-300">{row.date_needed}</td>
                                <td className="whitespace-nowrap px-4 py-3">
                                    <RequestStatusBadge badge={badgeFor(row)} />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {filtered.length > 0 && (
                <div className="flex flex-col items-center justify-between gap-3 border-t border-gray-200 px-4 py-3 text-sm sm:flex-row dark:border-gray-800">
                    <p className="text-gray-600 dark:text-gray-400">
                        Showing {(current - 1) * perPage + 1} to {Math.min(current * perPage, filtered.length)} of {filtered.length}
                    </p>
                    <div className="flex items-center gap-2">
                        <SelectInput
                            value={perPage}
                            onChange={(e) => {
                                setPerPage(Number(e.target.value));
                                setPage(1);
                            }}
                            className="py-1 text-sm"
                            aria-label="Rows per page"
                        >
                            {[10, 20, 50].map((size) => (
                                <option key={size} value={size}>
                                    {size}
                                </option>
                            ))}
                        </SelectInput>
                        <button
                            type="button"
                            onClick={() => setPage(current - 1)}
                            disabled={current === 1}
                            className="rounded-md px-2.5 py-1 text-gray-700 hover:bg-gray-100 disabled:opacity-40 dark:text-gray-300 dark:hover:bg-gray-800"
                            aria-label="Previous page"
                        >
                            ‹
                        </button>
                        <span className="tabular-nums text-gray-600 dark:text-gray-400">
                            {current} / {pages}
                        </span>
                        <button
                            type="button"
                            onClick={() => setPage(current + 1)}
                            disabled={current === pages}
                            className="rounded-md px-2.5 py-1 text-gray-700 hover:bg-gray-100 disabled:opacity-40 dark:text-gray-300 dark:hover:bg-gray-800"
                            aria-label="Next page"
                        >
                            ›
                        </button>
                    </div>
                </div>
            )}
        </Card>
    );
}

export default function Dashboard({ pending, inProgress, completed, total }) {
    const user = usePage().props.auth.user;

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    breadcrumbs={[{ label: 'Stock Request' }, { label: 'Dashboard' }]}
                    title={`Welcome ${String(user.username).toUpperCase()}!`}
                    subtitle="Here is where your department's stock requests stand today."
                />
            }
        >
            <Head title="Dashboard" />

            <div className="space-y-6 p-4 sm:p-6 lg:p-8">
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <Tile label="Total Requests" value={total} accent="brand" />
                    <Tile label="Pending" value={pending.length} accent="danger" />
                    <Tile label="In Progress" value={inProgress.length} accent="warning" />
                    <Tile label="Completed" value={completed.length} accent="success" />
                </div>

                <Panel
                    title="Pending Request"
                    rows={pending}
                    accent="danger"
                    emptyText="No pending requests."
                    badgeFor={() => ({ label: 'Pending', variant: 'danger' })}
                />
                <Panel
                    title="In-progress Request"
                    rows={inProgress}
                    accent="warning"
                    emptyText="Nothing in progress."
                    badgeFor={(row) =>
                        row.isReceived
                            ? { label: 'Received', variant: 'info', note: row.unserved ? 'Unserved' : 'Partially Served', alert: row.unserved }
                            : { label: 'Approved', variant: 'warning' }
                    }
                />
                <Panel
                    title="Completed Request"
                    rows={completed}
                    accent="success"
                    emptyText="No completed requests yet."
                    badgeFor={() => ({ label: 'Completed', variant: 'success' })}
                />
            </div>
        </AuthenticatedLayout>
    );
}
