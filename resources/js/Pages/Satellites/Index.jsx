import ButtonLink from '@/Components/ButtonLink';
import DataTable from '@/Components/DataTable';
import IconButton from '@/Components/IconButton';
import { PencilIcon, PlusIcon } from '@/Components/Icons';
import PageHeader from '@/Components/PageHeader';
import SearchableList from '@/Components/SearchableList';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

const FIELDS = ['name', 'description'];

export default function Index({ satellites, can }) {
    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    breadcrumbs={[{ label: 'Maintenance' }, { label: 'Satellites' }]}
                    title="Manage Satellites"
                    subtitle={`${satellites.length} satellite${satellites.length === 1 ? '' : 's'}`}
                    actions={
                        can.create && (
                            <ButtonLink href={route('satellites.create')}>
                                <PlusIcon />
                                New Satellite
                            </ButtonLink>
                        )
                    }
                />
            }
        >
            <Head title="Satellites" />

            <div className="p-4 sm:p-6 lg:p-8">
                <SearchableList
                    rows={satellites}
                    fields={FIELDS}
                    noun="satellites"
                    headers={[{ label: 'Name' }, { label: 'Description' }, { label: 'Status' }, ...(can.edit ? [{ label: 'Actions', align: 'right' }] : [])]}
                >
                    {(rows) =>
                        rows.map((row) => (
                            <DataTable.Row key={row.id}>
                                <DataTable.Cell strong>{row.name}</DataTable.Cell>
                                <DataTable.Cell wrap>{row.description}</DataTable.Cell>
                                <DataTable.Cell>
                                    <StatusBadge active={row.active} />
                                </DataTable.Cell>
                                {can.edit && (
                                    <DataTable.Actions>
                                        <IconButton label="Edit satellite" href={route('satellites.edit', row.id)}>
                                            <PencilIcon />
                                        </IconButton>
                                    </DataTable.Actions>
                                )}
                            </DataTable.Row>
                        ))
                    }
                </SearchableList>
            </div>
        </AuthenticatedLayout>
    );
}
