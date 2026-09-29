import Card from '@/Components/Card';
import ConfirmDialog from '@/Components/ConfirmDialog';
import IconButton from '@/Components/IconButton';
import { EyeIcon, PencilIcon, PrinterIcon, SendIcon, TrashIcon } from '@/Components/Icons';
import Pagination from '@/Components/Pagination';
import RequestStatusBadge from '@/Components/RequestStatusBadge';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import SortableHeader from '@/Components/SortableHeader';
import TextInput from '@/Components/TextInput';
import useListQuery from '@/lib/useListQuery';
import { router } from '@inertiajs/react';
import { useState } from 'react';

/**
 * The requestor's request table, shared by Manage Stock Request and Unsaved Stock Request.
 * `unsaved` switches the columns/actions to the drafts variant.
 */
export default function RequestList({ requests, filters, can, routeName, unsaved = false }) {
    const { search, setSearch, sort, direction, reload, toggleSort } = useListQuery(routeName, filters);
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const [pending, setPending] = useState(null);
    const [processing, setProcessing] = useState(false);

    const isFiltered = Boolean(filters.date_from || filters.date_to || filters.search);

    const applyDates = () => reload({ date_from: dateFrom || undefined, date_to: dateTo || undefined, page: undefined });

    const resetFilters = () => {
        setDateFrom('');
        setDateTo('');
        setSearch('');
        router.get(route(routeName), {}, { preserveScroll: true, replace: true });
    };

    const confirmAction = () => {
        const { action, row } = pending;
        const options = {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setPending(null);
            },
        };

        action === 'submit'
            ? router.patch(route('stockrequests.submit', row.id), {}, options)
            : router.delete(route('stockrequests.destroy', row.id), options);
    };

    const headers = [
        { column: 'transaction_no', label: 'Transaction #' },
        { column: 'origin', label: 'Origin' },
        { column: 'date_filed', label: unsaved ? 'Date Requested' : 'Date Created' },
        { column: 'dept', label: 'Department' },
        { column: 'date_needed', label: 'Date Needed' },
        { column: 'status', label: 'Status' },
    ];

    return (
        <>
            <Card>
                <div className="flex flex-col gap-3 border-b border-gray-200 p-4 lg:flex-row lg:items-end lg:justify-between dark:border-gray-800">
                    <div className="flex flex-wrap items-end gap-3">
                        <label className="text-xs font-medium text-gray-600 dark:text-gray-400">
                            Date From
                            <TextInput type="date" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} className="mt-1 block py-1.5 text-sm" />
                        </label>
                        <label className="text-xs font-medium text-gray-600 dark:text-gray-400">
                            Date To
                            <TextInput type="date" value={dateTo} onChange={(e) => setDateTo(e.target.value)} className="mt-1 block py-1.5 text-sm" />
                        </label>
                        <SecondaryButton onClick={applyDates}>Apply</SecondaryButton>
                        <button
                            type="button"
                            onClick={resetFilters}
                            disabled={!isFiltered && !dateFrom && !dateTo}
                            className="px-2 py-2 text-xs font-semibold uppercase tracking-widest text-gray-500 hover:text-gray-800 disabled:opacity-40 dark:text-gray-400 dark:hover:text-gray-100"
                        >
                            Reset
                        </button>
                    </div>

                    <div className="flex items-end gap-3">
                        <TextInput
                            type="search"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder={unsaved ? 'Search transaction #, origin, department...' : 'Search transaction #, item, department...'}
                            className="w-full lg:w-80"
                            aria-label="Search requests"
                        />
                        <SelectInput
                            value={requests.per_page}
                            onChange={(e) => reload({ per_page: e.target.value, page: undefined })}
                            className="py-2 text-sm"
                            aria-label="Rows per page"
                        >
                            {[10, 20, 50].map((size) => (
                                <option key={size} value={size}>
                                    {size} rows
                                </option>
                            ))}
                        </SelectInput>
                    </div>
                </div>

                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                        <thead className="bg-gray-50 dark:bg-gray-800/50">
                            <tr>
                                {headers.map((header) => (
                                    <SortableHeader key={header.column} {...header} sort={sort} direction={direction} onSort={toggleSort} />
                                ))}
                                <th scope="col" className="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                            {requests.data.length === 0 && (
                                <tr>
                                    <td colSpan={headers.length + 1} className="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                                        {isFiltered
                                            ? unsaved
                                                ? 'No drafts match your filters.'
                                                : 'No requests match your filters.'
                                            : unsaved
                                              ? 'No unsaved requests.'
                                              : 'No stock requests yet.'}
                                    </td>
                                </tr>
                            )}

                            {requests.data.map((row) => (
                                <tr key={row.id} className="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                    <td className="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{row.transaction_no}</td>
                                    <td className="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-300">{row.origin}</td>
                                    <td className="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-300">
                                        {row.date_filed}
                                        <span className="block text-xs text-gray-500 dark:text-gray-400">{row.time_filed}</span>
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-300">{row.dept}</td>
                                    <td className="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-300">{row.date_needed}</td>
                                    <td className="whitespace-nowrap px-4 py-3">
                                        <RequestStatusBadge badge={unsaved ? { label: 'Pending', variant: 'danger' } : row.badge} />
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3 text-right">
                                        <div className="inline-flex items-center gap-1">
                                            {!unsaved && row.pending && can.edit && (
                                                <IconButton label="Submit for approval" tone="success" onClick={() => setPending({ action: 'submit', row })}>
                                                    <SendIcon />
                                                </IconButton>
                                            )}
                                            {can.view && (
                                                <IconButton label="View request" href={route('stockrequests.show', row.id)}>
                                                    <EyeIcon />
                                                </IconButton>
                                            )}
                                            {can.edit && row.editable && (
                                                <IconButton label="Edit request" href={route('stockrequests.edit', row.id)}>
                                                    <PencilIcon />
                                                </IconButton>
                                            )}
                                            {can.delete && row.editable && (
                                                <IconButton label="Delete request" tone="danger" onClick={() => setPending({ action: 'delete', row })}>
                                                    <TrashIcon />
                                                </IconButton>
                                            )}
                                            {!unsaved && can.view && (
                                                <IconButton label="Print stock request form" href={route('stockrequests.print', row.id)} download>
                                                    <PrinterIcon />
                                                </IconButton>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={requests} />
            </Card>

            <ConfirmDialog
                show={pending !== null}
                title={pending?.action === 'submit' ? 'Submit request?' : 'Delete request?'}
                message={
                    pending?.action === 'submit'
                        ? `Submit ${pending?.row.transaction_no} for approval? You will not be able to edit it afterwards.`
                        : `Do you want to delete ${pending?.row.transaction_no || 'this request'}?`
                }
                confirmLabel={pending?.action === 'submit' ? 'Submit' : 'Delete'}
                danger={pending?.action === 'delete'}
                processing={processing}
                onConfirm={confirmAction}
                onClose={() => !processing && setPending(null)}
            />
        </>
    );
}
