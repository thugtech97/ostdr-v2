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

export default function Index({ requests, filters }) {
    const list = useListQuery('mcdrecords.index', filters);
    const [batchStatus, setBatchStatus] = useState(filters.batch_status ?? '');

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    breadcrumbs={[{ label: 'MCD Records' }, { label: 'Dashboard' }]}
                    title="Completed Stock Transfer Requests"
                    subtitle={
                        requests.total
                            ? `${requests.total} completed request${requests.total === 1 ? '' : 's'} in the selected period`
                            : 'Completed requests for the selected period.'
                    }
                    actions={
                        requests.total > 0 && (
                            <ButtonLink href={route('mcdrecords.export', filters)} variant="secondary" plain>
                                <DownloadIcon />
                                Export
                            </ButtonLink>
                        )
                    }
                />
            }
        >
            <Head title="Completed Transactions" />

            <div className="p-4 sm:p-6 lg:p-8">
                <Card>
                    <ListToolbar
                        routeName="mcdrecords.index"
                        filters={filters}
                        list={list}
                        perPage={requests.per_page}
                        placeholder="Search transaction #, item, department..."
                        extraFilters={{ batch_status: batchStatus }}
                    >
                        <label className="text-xs font-medium text-gray-600 dark:text-gray-400">
                            Batch Status
                            <SelectInput
                                value={batchStatus}
                                onChange={(e) => {
                                    setBatchStatus(e.target.value);
                                    list.reload({ batch_status: e.target.value || undefined, page: undefined });
                                }}
                                className="mt-1 block py-1.5 text-sm"
                            >
                                <option value="">*</option>
                                <option value="all-batched">ALL-BATCHED</option>
                            </SelectInput>
                        </label>
                    </ListToolbar>

                    <DataTable
                        headers={[
                            { column: 'transaction_no', label: 'Transaction #' },
                            { column: 'date_filed', label: 'Date Requested' },
                            { column: 'dept', label: 'Department' },
                            { column: 'origin', label: 'Origin' },
                            { label: 'Batch No.' },
                            { label: 'Status' },
                            { label: 'Actions', align: 'right' },
                        ]}
                        list={list}
                        count={requests.data.length}
                        empty="No completed requests for this period."
                    >
                        {requests.data.map((row) => (
                            <DataTable.Row key={row.id}>
                                <DataTable.Cell strong>{row.transaction_no}</DataTable.Cell>
                                <DataTable.Cell>{row.date_filed}</DataTable.Cell>
                                <DataTable.Cell>{row.dept}</DataTable.Cell>
                                <DataTable.Cell>{row.origin}</DataTable.Cell>
                                <DataTable.Cell className="text-gray-500 dark:text-gray-400">{row.batch_numbers || '—'}</DataTable.Cell>
                                <DataTable.Cell>
                                    <RequestStatusBadge badge={row.badge} />
                                </DataTable.Cell>
                                <DataTable.Actions>
                                    <IconButton label="View Stock Transfer Request" href={route('mcdrecords.request', row.id)}>
                                        <EyeIcon />
                                    </IconButton>
                                    {row.isReceived && (
                                        <IconButton label="Generate Stock Request Form" href={route('mcdrecords.print', row.id)} download>
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
