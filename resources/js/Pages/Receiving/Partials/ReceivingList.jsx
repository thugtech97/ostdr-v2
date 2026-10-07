import ButtonLink from '@/Components/ButtonLink';
import Card from '@/Components/Card';
import ConfirmDialog from '@/Components/ConfirmDialog';
import DataTable from '@/Components/DataTable';
import IconButton from '@/Components/IconButton';
import { ArrowDownCircleIcon, DownloadIcon, EyeIcon, PencilIcon, PrinterIcon } from '@/Components/Icons';
import ListToolbar from '@/Components/ListToolbar';
import PageHeader from '@/Components/PageHeader';
import Pagination from '@/Components/Pagination';
import RequestStatusBadge from '@/Components/RequestStatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useListQuery from '@/lib/useListQuery';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

/**
 * Manage Receiving and Completed Stock share this list; `completed` drops the status
 * column and the receive / issue actions, as legacy does.
 */
export default function ReceivingList({ requests, filters, can, completed = false }) {
    const routeName = completed ? 'mcds.completed' : 'mcds.index';
    const list = useListQuery(routeName, filters);
    const [receiving, setReceiving] = useState(null);
    const [processing, setProcessing] = useState(false);

    const isFiltered = Boolean(filters.date_from || filters.date_to || filters.search);
    const title = completed ? 'Completed Requests' : 'MCD Receiving';

    const headers = [
        { column: 'transaction_no', label: 'Transaction #' },
        { column: 'date_filed', label: 'Date Requested' },
        { column: 'time_filed', label: 'Time Requested' },
        { column: 'dept', label: 'Department' },
        { column: 'date_needed', label: 'Date Needed' },
        ...(completed ? [] : [{ column: 'status', label: 'Status' }]),
        { label: 'Actions', align: 'right' },
    ];

    const receive = () =>
        router.patch(route('mcds.receive', receiving.id), {}, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setReceiving(null);
            },
        });

    const subtitle = requests.total
        ? `${requests.total} request${requests.total === 1 ? '' : 's'} ${completed ? 'fully issued or closed' : 'still to be issued'}`
        : completed
          ? 'Requests fully issued or closed.'
          : 'Approved requests awaiting issuance.';

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    breadcrumbs={[{ label: 'MCD Receiving' }, { label: completed ? 'Dashboard - Completed' : 'Dashboard' }]}
                    title={title}
                    subtitle={subtitle}
                    actions={
                        requests.total > 0 && (
                            <ButtonLink href={route('mcds.export', { ...filters, completed: completed ? 1 : undefined })} variant="secondary" plain>
                                <DownloadIcon />
                                Export
                            </ButtonLink>
                        )
                    }
                />
            }
        >
            <Head title={title} />

            <div className="p-4 sm:p-6 lg:p-8">
                <Card>
                    <ListToolbar
                        routeName={routeName}
                        filters={filters}
                        list={list}
                        perPage={requests.per_page}
                        placeholder="Search transaction #, origin, department..."
                    />

                    <DataTable
                        headers={headers}
                        list={list}
                        count={requests.data.length}
                        empty={isFiltered ? 'No requests match your filters.' : completed ? 'No completed requests.' : 'Nothing awaiting issuance.'}
                    >
                        {requests.data.map((row) => (
                            <DataTable.Row key={row.id}>
                                <DataTable.Cell strong>{row.transaction_no}</DataTable.Cell>
                                <DataTable.Cell>{row.date_filed}</DataTable.Cell>
                                <DataTable.Cell className="text-gray-500 dark:text-gray-400">{row.time_filed}</DataTable.Cell>
                                <DataTable.Cell>{row.dept}</DataTable.Cell>
                                <DataTable.Cell>{row.date_needed}</DataTable.Cell>
                                {!completed && (
                                    <DataTable.Cell>
                                        <RequestStatusBadge badge={row.badge} />
                                    </DataTable.Cell>
                                )}
                                <DataTable.Actions>
                                    <IconButton
                                        label="View Stock Transfer Request"
                                        href={route('mcds.show', { stockRequest: row.id, from: completed ? 'completed' : undefined })}
                                    >
                                        <EyeIcon />
                                    </IconButton>
                                    {!completed && can.edit && !row.isReceived && (
                                        <IconButton label="Receive Stock Transfer Request" tone="success" onClick={() => setReceiving(row)}>
                                            <ArrowDownCircleIcon />
                                        </IconButton>
                                    )}
                                    {!completed && can.edit && row.isReceived && (
                                        <IconButton label="Update Stock Transfer Request" href={route('mcds.edit', row.id)}>
                                            <PencilIcon />
                                        </IconButton>
                                    )}
                                    {row.isReceived && (
                                        <IconButton label="Generate Stock Request Form" href={route('mcds.print', row.id)} download>
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

            <ConfirmDialog
                show={receiving !== null}
                title="Receive Confirmation"
                message={`Do you want to receive ${receiving?.transaction_no ?? 'this request'}?`}
                confirmLabel="Receive"
                processing={processing}
                onConfirm={receive}
                onClose={() => !processing && setReceiving(null)}
            />
        </AuthenticatedLayout>
    );
}
