import ButtonLink from '@/Components/ButtonLink';
import DataTable from '@/Components/DataTable';
import IconButton from '@/Components/IconButton';
import { PencilIcon, PlusIcon } from '@/Components/Icons';
import PageHeader from '@/Components/PageHeader';
import SearchableList from '@/Components/SearchableList';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

const FIELDS = ['module_type', 'description'];

export default function Index({ permissions, can }) {
    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    breadcrumbs={[{ label: 'Maintenance' }, { label: 'Permissions' }]}
                    title="Manage Permissions"
                    subtitle={`${permissions.length} permission${permissions.length === 1 ? '' : 's'}`}
                    actions={
                        can.create && (
                            <ButtonLink href={route('permissions.create')}>
                                <PlusIcon />
                                New Permission
                            </ButtonLink>
                        )
                    }
                />
            }
        >
            <Head title="Permissions" />

            <div className="p-4 sm:p-6 lg:p-8">
                <SearchableList
                    rows={permissions}
                    fields={FIELDS}
                    noun="permissions"
                    headers={[{ label: 'Module Type' }, { label: 'Description' }, { label: 'Status' }, ...(can.edit ? [{ label: 'Actions', align: 'right' }] : [])]}
                >
                    {(rows) =>
                        rows.map((row) => (
                            <DataTable.Row key={row.id}>
                                <DataTable.Cell>{row.module_type}</DataTable.Cell>
                                <DataTable.Cell strong>{row.description}</DataTable.Cell>
                                <DataTable.Cell>
                                    <StatusBadge active={row.active} />
                                </DataTable.Cell>
                                {can.edit && (
                                    <DataTable.Actions>
                                        <IconButton label="Edit permission" href={route('permissions.edit', row.id)}>
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
