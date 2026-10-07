import PageHeader from '@/Components/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import PermissionForm from './Partials/PermissionForm';

export default function Create({ modules }) {
    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    breadcrumbs={[{ label: 'Maintenance' }, { label: 'Permissions', href: route('permissions.index') }, { label: 'Create' }]}
                    title="Add New Permission"
                />
            }
        >
            <Head title="New Permission" />

            <div className="max-w-2xl p-4 sm:p-6 lg:p-8">
                <PermissionForm modules={modules} />
            </div>
        </AuthenticatedLayout>
    );
}
