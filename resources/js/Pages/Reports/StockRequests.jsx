import ButtonLink from '@/Components/ButtonLink';
import Card from '@/Components/Card';
import DataTable from '@/Components/DataTable';
import IconButton from '@/Components/IconButton';
import { DownloadIcon, EyeIcon, PrinterIcon } from '@/Components/Icons';
import ListToolbar from '@/Components/ListToolbar';
import PageHeader from '@/Components/PageHeader';
import Pagination from '@/Components/Pagination';
import RequestStatusBadge from '@/Components/RequestStatusBadge';
import SelectInput from '@/Components/SelectInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useListQuery from '@/lib/useListQuery';
import { Head } from '@inertiajs/react';
import { useState } from 'react';

const STATUSES = [
    { value: '', label: '*' },
    { value: 'pending', label: 'PENDING' },
    { value: 'submitted', label: 'SUBMITTED' },
    { value: 'approved', label: 'APPROVED' },
    { value: 'received', label: 'RECEIVED' },
    { value: 'completed', label: 'COMPLETED' },
    { value: 'cancelled', label: 'CANCELLED' },
];

export default function StockRequests({ requests, filters }) {
    const list = useListQuery('reports.stock-requests', filters);
    const [status, setStatus] = useState(filters.status ?? '');

    const isFiltered = Boolean(filters.date_from || filters.date_to || filters.search || filters.status);

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    breadcrumbs={[{ label: 'Reports' }, { label: 'Stock Transfer Requests' }]}
                    title="Stock Transfer Requests"
                    subtitle={
                        requests.total
                            ? `${requests.total} request${requests.total === 1 ? '' : 's'} from your department${isFiltered ? ' matching your filters' : ''}`
                            : 'Stock transfer requests from your department.'
                    }
                    actions={
                        requests.total > 0 && (
                            <ButtonLink href={route('reports.stock-requests.export', filters)} variant="secondary" plain>
                                <DownloadIcon />
                                Export
                            </ButtonLink>
                        )
                    }
                />
            }
        >
            <Head title="Stock Transfer Requests" />

            <div className="p-4 sm:p-6 lg:p-8">
                <Card>
                    <ListToolbar
                        routeName="reports.stock-requests"
                        filters={filters}
                        list={list}
                        perPage={requests.per_page}
                        placeholder="Search transaction #, origin, department..."
                        extraFilters={{ status }}
                    >
                        <label className="text-xs font-medium text-gray-600 dark:text-gray-400">
                            Status
                            <SelectInput
                                value={status}
                                onChange={(e) => {
                                    setStatus(e.target.value);
                                    list.reload({ status: e.target.value || undefined, page: undefined });
                                }}
                                className="mt-1 block py-1.5 text-sm"
                            >
                                {STATUSES.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </SelectInput>
                        </label>
                    </ListToolbar>

                    <DataTable
                        headers={[
                            { column: 'transaction_no', label: 'Transaction #' },
                            { column: 'origin', label: 'Origin' },
                            { column: 'date_filed', label: 'Date Requested' },
                            { column: 'dept', label: 'Department' },
                            { column: 'date_needed', label: 'Date Needed' },
                            { column: 'status', label: 'Status' },
                            { label: 'Actions', align: 'right' },
                        ]}
                        list={list}
                        count={requests.data.length}
                        empty={isFiltered ? 'No requests match your filters.' : 'No stock transfer requests.'}
                    >
                        {requests.data.map((row) => (
                            <DataTable.Row key={row.id}>
                                <DataTable.Cell strong>{row.transaction_no}</DataTable.Cell>
                                <DataTable.Cell>{row.origin}</DataTable.Cell>
                                <DataTable.Cell>
                                    {row.date_filed}
                                    <span className="block text-xs text-gray-500 dark:text-gray-400">{row.time_filed}</span>
                                </DataTable.Cell>
                                <DataTable.Cell>{row.dept}</DataTable.Cell>
                                <DataTable.Cell>{row.date_needed}</DataTable.Cell>
                                <DataTable.Cell>
                                    <RequestStatusBadge badge={row.badge} />
                                </DataTable.Cell>
                                <DataTable.Actions>
                                    <IconButton label="View Stock Transfer Request" href={route('reports.stock-requests.show', row.id)}>
                                        <EyeIcon />
                                    </IconButton>
                                    {row.isReceived && (
                                        <IconButton label="Generate Stock Request Form" href={route('reports.stock-requests.print', row.id)} download>
                                            <PrinterIcon />
                                        </IconButton>
                                    )}
                                </DataTable.Actions>
                            </DataTable.Row>
                        ))}
                    </DataTable>

                    <Pagination paginator={requests} />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
