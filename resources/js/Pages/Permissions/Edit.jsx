import PageHeader from '@/Components/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import PermissionForm from './Partials/PermissionForm';

export default function Edit({ permission, modules }) {
    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    breadcrumbs={[{ label: 'Maintenance' }, { label: 'Permissions', href: route('permissions.index') }, { label: 'Edit' }]}
                    title="Edit Permission"
                    subtitle={permission.description}
                />
            }
        >
            <Head title={`Edit ${permission.description}`} />

            <div className="max-w-2xl p-4 sm:p-6 lg:p-8">
                <PermissionForm permission={permission} modules={modules} />
            </div>
        </AuthenticatedLayout>
    );
}
