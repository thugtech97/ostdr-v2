import PageHeader from '@/Components/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import RoleForm from './Partials/RoleForm';

export default function Create() {
    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    breadcrumbs={[
                        { label: 'Maintenance' },
                        { label: 'Roles', href: route('roles.index') },
                        { label: 'Create' },
                    ]}
                    title="New Role"
                />
            }
        >
            <Head title="New Role" />

            <div className="max-w-2xl p-4 sm:p-6 lg:p-8">
                <RoleForm />
            </div>
        </AuthenticatedLayout>
    );
}
