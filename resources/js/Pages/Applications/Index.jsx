import ButtonLink from '@/Components/ButtonLink';
import Card from '@/Components/Card';
import ConfirmDialog from '@/Components/ConfirmDialog';
import DataTable from '@/Components/DataTable';
import IconButton from '@/Components/IconButton';
import { PencilIcon, PlusIcon, TrashIcon } from '@/Components/Icons';
import ListToolbar from '@/Components/ListToolbar';
import PageHeader from '@/Components/PageHeader';
import Pagination from '@/Components/Pagination';
import RequestStatusBadge from '@/Components/RequestStatusBadge';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useListQuery from '@/lib/useListQuery';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

const ACTIONS = {
    delete: {
        title: 'Delete Confirmation',
        message: (row) => `Do you want to delete the ${row.scheduled_date} ${row.scheduled_time} shutdown schedule?`,
        confirm: 'Delete',
        danger: true,
        run: (row, options) => router.delete(route('applications.destroy', row.id), options),
    },
    reindex: {
        title: 'Confirmation',
        message: () => 'Do you want to initiate re-indexing?',
        confirm: 'Re-index',
        run: (row, options) => router.post(route('applications.reindex'), {}, options),
    },
    down: {
        title: 'Stop the application?',
        message: () => 'Everyone else is signed out and sees the maintenance page until the app is started again. You keep access from this browser.',
        confirm: 'Stop',
        danger: true,
        run: (row, options) => router.post(route('applications.down'), {}, options),
    },
    up: {
        title: 'Confirmation',
        message: () => 'Do you want to start the application?',
        confirm: 'Start',
        run: (row, options) => router.post(route('applications.up'), {}, options),
    },
};

export default function Index({ schedules, filters, can }) {
    const list = useListQuery('applications.index', filters);
    const [pending, setPending] = useState(null);
    const [processing, setProcessing] = useState(false);

    const action = pending ? ACTIONS[pending.action] : null;

    const confirm = () =>
        action.run(pending.row, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setPending(null);
            },
        });

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    breadcrumbs={[{ label: 'Maintenance' }, { label: 'Application Maintenance' }]}
                    title="Manage Scheduled Shutdown"
                    subtitle="Users see a countdown for shutdowns scheduled in the next five days."
                    actions={
                        can.edit || can.create ? (
                            <div className="flex flex-wrap gap-2">
                                {can.edit && (
                                    <>
                                        <SecondaryButton onClick={() => setPending({ action: 'reindex' })}>Re-index</SecondaryButton>
                                        <SecondaryButton onClick={() => setPending({ action: 'up' })}>Start</SecondaryButton>
                                        <SecondaryButton onClick={() => setPending({ action: 'down' })} className="!text-red-700 dark:!text-red-300">
                                            Stop
                                        </SecondaryButton>
                                    </>
                                )}
                                {can.create && (
                                    <ButtonLink href={route('applications.create')}>
                                        <PlusIcon />
                                        New Schedule
                                    </ButtonLink>
                                )}
                            </div>
                        ) : null
                    }
                />
            }
        >
            <Head title="Application Maintenance" />

            <div className="p-4 sm:p-6 lg:p-8">
                <Card>
                    <ListToolbar
                        routeName="applications.index"
                        filters={filters}
                        list={list}
                        perPage={schedules.per_page}
                        dates={false}
                        placeholder="Search reason..."
                    />

                    <DataTable
                        headers={[
                            { column: 'scheduled_date', label: 'Scheduled Date' },
                            { column: 'scheduled_time', label: 'Time' },
                            { column: 'reason', label: 'Reason' },
                            { label: 'Status' },
                            ...(can.edit || can.delete ? [{ label: 'Actions', align: 'right' }] : []),
                        ]}
                        list={list}
                        count={schedules.data.length}
                        empty="No shutdowns scheduled."
                    >
                        {schedules.data.map((row) => (
                            <DataTable.Row key={row.id}>
                                <DataTable.Cell strong>{row.scheduled_date}</DataTable.Cell>
                                <DataTable.Cell>{row.scheduled_time}</DataTable.Cell>
                                <DataTable.Cell wrap>{row.reason}</DataTable.Cell>
                                <DataTable.Cell>
                                    <RequestStatusBadge badge={{ label: row.status, variant: row.status === 'Upcoming' ? 'warning' : 'muted' }} />
                                </DataTable.Cell>
                                {(can.edit || can.delete) && (
                                    <DataTable.Actions>
                                        {can.edit && (
                                            <IconButton label="Edit Shutdown Schedule" href={route('applications.edit', row.id)}>
                                                <PencilIcon />
                                            </IconButton>
                                        )}
                                        {can.delete && (
                                            <IconButton label="Delete Shutdown Schedule" tone="danger" onClick={() => setPending({ action: 'delete', row })}>
                                                <TrashIcon />
                                            </IconButton>
                                        )}
                                    </DataTable.Actions>
                                )}
                            </DataTable.Row>
                        ))}
                    </DataTable>

                    <Pagination paginator={schedules} />
                </Card>
            </div>

            <ConfirmDialog
                show={pending !== null}
                title={action?.title}
                message={action?.message(pending?.row)}
                confirmLabel={action?.confirm}
                danger={action?.danger}
                processing={processing}
                onConfirm={confirm}
                onClose={() => !processing && setPending(null)}
            />
        </AuthenticatedLayout>
    );
}
