import ButtonLink from '@/Components/ButtonLink';
import Card from '@/Components/Card';
import ConfirmDialog from '@/Components/ConfirmDialog';
import DataTable from '@/Components/DataTable';
import IconButton from '@/Components/IconButton';
import { DownloadIcon, EyeIcon, PencilIcon, PlusIcon, TrashIcon } from '@/Components/Icons';
import ListToolbar from '@/Components/ListToolbar';
import PageHeader from '@/Components/PageHeader';
import Pagination from '@/Components/Pagination';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useListQuery from '@/lib/useListQuery';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Batches({ batches, filters, can }) {
    const list = useListQuery('mcdrecords.batches', filters);
    const [deleting, setDeleting] = useState(null);
    const [processing, setProcessing] = useState(false);

    const isFiltered = Boolean(filters.date_from || filters.date_to || filters.search);

    const destroy = () =>
        router.delete(route('mcdrecords.destroy', deleting.id), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setDeleting(null);
            },
        });

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    breadcrumbs={[{ label: 'MCD Records' }, { label: 'Batches' }]}
                    title="Manage Batches"
                    subtitle={
                        batches.total
                            ? `${batches.total} batch${batches.total === 1 ? '' : 'es'}${isFiltered ? ' matching your filters' : ''}`
                            : 'Group issued items into batches.'
                    }
                    actions={
                        <>
                            {batches.total > 0 && (
                                <ButtonLink href={route('mcdrecords.batches.export', filters)} variant="secondary" plain>
                                    <DownloadIcon />
                                    Export
                                </ButtonLink>
                            )}
                            {can.create && (
                                <ButtonLink href={route('mcdrecords.create')}>
                                    <PlusIcon />
                                    Create a Batch
                                </ButtonLink>
                            )}
                        </>
                    }
                />
            }
        >
            <Head title="Manage Batches" />

            <div className="p-4 sm:p-6 lg:p-8">
                <Card>
                    <ListToolbar
                        routeName="mcdrecords.batches"
                        filters={filters}
                        list={list}
                        perPage={batches.per_page}
                        placeholder="Search batch # or description..."
                    />

                    <DataTable
                        headers={[
                            { column: 'batchno', label: 'Batch #' },
                            { column: 'description', label: 'Description' },
                            { column: 'created_at', label: 'Date Created' },
                            { label: 'Actions', align: 'right' },
                        ]}
                        list={list}
                        count={batches.data.length}
                        empty={isFiltered ? 'No batches match your filters.' : 'No batches yet.'}
                    >
                        {batches.data.map((batch) => (
                            <DataTable.Row key={batch.id}>
                                <DataTable.Cell strong>{batch.batchno}</DataTable.Cell>
                                <DataTable.Cell wrap>{batch.description}</DataTable.Cell>
                                <DataTable.Cell>{batch.created_at}</DataTable.Cell>
                                <DataTable.Actions>
                                    <IconButton label="View batch" href={route('mcdrecords.show', batch.id)}>
                                        <EyeIcon />
                                    </IconButton>
                                    {can.edit && (
                                        <IconButton label="Edit batch" href={route('mcdrecords.edit', batch.id)}>
                                            <PencilIcon />
                                        </IconButton>
                                    )}
                                    {can.delete && (
                                        <IconButton label="Delete batch" tone="danger" onClick={() => setDeleting(batch)}>
                                            <TrashIcon />
                                        </IconButton>
                                    )}
                                </DataTable.Actions>
                            </DataTable.Row>
                        ))}
                    </DataTable>

                    <Pagination paginator={batches} />
                </Card>
            </div>

            <ConfirmDialog
                show={deleting !== null}
                title="Delete Confirmation"
                message={`Do you want to delete batch ${deleting?.batchno ?? ''}? Its items become unbatched again.`}
                confirmLabel="Delete"
                danger
                processing={processing}
                onConfirm={destroy}
                onClose={() => !processing && setDeleting(null)}
            />
        </AuthenticatedLayout>
    );
}
