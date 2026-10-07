import ButtonLink from '@/Components/ButtonLink';
import Card from '@/Components/Card';
import DataTable from '@/Components/DataTable';
import { DownloadIcon } from '@/Components/Icons';
import ListToolbar from '@/Components/ListToolbar';
import PageHeader from '@/Components/PageHeader';
import Pagination from '@/Components/Pagination';
import RequestStatusBadge from '@/Components/RequestStatusBadge';
import SelectInput from '@/Components/SelectInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useListQuery from '@/lib/useListQuery';
import { Head } from '@inertiajs/react';
import { useState } from 'react';

const SERVED = [
    { value: 'unserved', label: 'Unserved only' },
    { value: 'partial', label: 'Partially served' },
    { value: 'all', label: 'All outstanding' },
];

const ITEM_STATES = [
    { value: 'all', label: 'All items' },
    { value: 'open', label: 'Open only' },
    { value: 'closed', label: 'Closed only' },
];

function Filter({ label, value, options, onChange }) {
    return (
        <label className="text-xs font-medium text-gray-600 dark:text-gray-400">
            {label}
            <SelectInput value={value} onChange={(e) => onChange(e.target.value)} className="mt-1 block py-1.5 text-sm">
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </SelectInput>
        </label>
    );
}

/**
 * Unserved Transactions: every line still owed, for the requesting and the supplying department.
 */
export default function Unserved({ items, filters }) {
    const list = useListQuery('reports.unserved', filters, { defaultSort: null });
    const [served, setServed] = useState(filters.served ?? 'unserved');
    const [itemState, setItemState] = useState(filters.item_state ?? 'all');

    const choose = (setter, key) => (value) => {
        setter(value);
        list.reload({ [key]: value, page: undefined });
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    breadcrumbs={[{ label: 'Reports' }, { label: 'Unserved Transactions' }]}
                    title="Unserved Transactions"
                    subtitle={items.total ? `${items.total} line${items.total === 1 ? '' : 's'} still owed` : 'Requested items still owed.'}
                    actions={
                        items.total > 0 && (
                            <ButtonLink href={route('reports.unserved.export', filters)} variant="secondary" plain>
                                <DownloadIcon />
                                Export
                            </ButtonLink>
                        )
                    }
                />
            }
        >
            <Head title="Unserved Transactions" />

            <div className="p-4 sm:p-6 lg:p-8">
                <Card>
                    <ListToolbar
                        routeName="reports.unserved"
                        filters={filters}
                        list={list}
                        perPage={items.per_page}
                        placeholder="Search transaction #, item, department..."
                        extraFilters={{ served, item_state: itemState }}
                    >
                        <Filter label="Served" value={served} options={SERVED} onChange={choose(setServed, 'served')} />
                        <Filter label="Item" value={itemState} options={ITEM_STATES} onChange={choose(setItemState, 'item_state')} />
                    </ListToolbar>

                    <DataTable
                        headers={[
                            { column: 'transaction_no', label: 'Transaction #' },
                            { column: 'date_filed', label: 'Date Requested' },
                            { column: 'date_needed', label: 'Date Needed' },
                            { column: 'dept', label: 'Department' },
                            { column: 'origin', label: 'Origin' },
                            { column: 'stock_code', label: 'Stock Code' },
                            { column: 'description', label: 'Description' },
                            { label: 'UOM' },
                            { column: 'requested_qty', label: 'Requested' },
                            { column: 'issued_total', label: 'Issued' },
                            { label: 'Unserved Qty' },
                            { column: 'item_state', label: 'Item' },
                            { column: 'days_outstanding', label: 'Age' },
                        ]}
                        list={list}
                        count={items.data.length}
                        empty="Nothing outstanding for these filters."
                    >
                        {items.data.map((item) => (
                            <DataTable.Row key={item.id}>
                                <DataTable.Cell strong>{item.transaction_no}</DataTable.Cell>
                                <DataTable.Cell>{item.date_filed}</DataTable.Cell>
                                <DataTable.Cell>{item.date_needed}</DataTable.Cell>
                                <DataTable.Cell>{item.dept}</DataTable.Cell>
                                <DataTable.Cell>{item.origin}</DataTable.Cell>
                                <DataTable.Cell className="font-mono text-xs">{item.stock_code}</DataTable.Cell>
                                <DataTable.Cell wrap className="min-w-[14rem]">{item.description}</DataTable.Cell>
                                <DataTable.Cell>{item.uom}</DataTable.Cell>
                                <DataTable.Cell className="tabular-nums">{item.requested_qty}</DataTable.Cell>
                                <DataTable.Cell className="tabular-nums">{item.issued_total}</DataTable.Cell>
                                <DataTable.Cell>
                                    <RequestStatusBadge badge={{ label: String(item.unserved_qty), variant: 'danger' }} />
                                </DataTable.Cell>
                                <DataTable.Cell wrap>
                                    <RequestStatusBadge badge={item.isClosed ? { label: 'Closed', variant: 'muted' } : { label: 'Open', variant: 'warning' }} />
                                    {item.isClosed_remarks && (
                                        <span className="mt-1 block text-xs text-gray-500 dark:text-gray-400">{item.isClosed_remarks}</span>
                                    )}
                                </DataTable.Cell>
                                <DataTable.Cell className="text-gray-500 dark:text-gray-400">
                                    {item.days_outstanding} day{item.days_outstanding === 1 ? '' : 's'}
                                </DataTable.Cell>
                            </DataTable.Row>
                        ))}
                    </DataTable>

                    <Pagination paginator={items} />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
