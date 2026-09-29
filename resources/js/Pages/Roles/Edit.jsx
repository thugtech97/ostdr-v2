import PageHeader from '@/Components/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import RoleForm from './Partials/RoleForm';

export default function Edit({ role, usersCount }) {
    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    breadcrumbs={[
                        { label: 'Maintenance' },
                        { label: 'Roles', href: route('roles.index') },
                        { label: 'Edit' },
                    ]}
                    title={role.name}
                />
            }
        >
            <Head title={`Edit ${role.name}`} />

            <div className="max-w-2xl p-4 sm:p-6 lg:p-8">
                <RoleForm role={role} usersCount={usersCount} />
            </div>
        </AuthenticatedLayout>
    );
}
